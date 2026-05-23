<?php

namespace App\Services;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\AlternativePart;
use Symfony\Component\Mime\Part\Multipart\MixedPart;
use Symfony\Component\Mime\Part\TextPart;

/**
 * Envoi d'un message via le serveur SMTP Noliae (Postfix, 149:25).
 * Les pièces jointes sont stockées sur S3 (Scaleway) et insérées sous
 * forme de liens signés valables 30 jours — limite : 10 Mo par fichier.
 */
class SendService
{
    public function __construct(
        private MailboxService $mailbox,
        private AttachmentStore $store,
    ) {
    }

    public function send(string $from, array $to, string $subject, string $html, array $opts = []): void
    {
        // HELO/EHLO propre — sinon Symfony Mailer envoie « [127.0.0.1] »
        // (le hostname du conteneur webmail) ce qui ressemble à du spam.
        $dsn = sprintf('smtp://%s:%d?local_domain=mail.noliae.com',
            config('noliae.smtp_host'), config('noliae.smtp_port'));
        $mailer = new Mailer(Transport::fromDsn($dsn));

        $fromName = trim((string) ($opts['from_name'] ?? ''));
        $sender = $fromName !== '' ? new Address($from, $fromName) : new Address($from);

        // 1. Récupère les liens S3 (mode stockage) ou les binaires (mode SMTP).
        $inlineBin = $opts['attachments_inline'] ?? [];
        $links     = $opts['s3_links'] ?? [];
        if (! $links && ! $inlineBin) {
            foreach (($opts['attachments'] ?? []) as $file) {
                if ($file && method_exists($file, 'isValid') && $file->isValid()) {
                    $links[] = $this->store->upload($file, $from);
                }
            }
        }

        // 2. Si corps PGP chiffré : on l'envoie tel quel (text/plain) sans normalisation.
        if (! empty($opts['pgp_encrypted']) && str_starts_with(trim($html), '-----BEGIN PGP MESSAGE-----')) {
            $part = new TextPart(trim($html), 'utf-8', 'plain', '8bit');
            $email = (new Email())
                ->from($sender)
                ->subject($subject !== '' ? $subject : '(chiffré)')
                ->setBody($part);
            $email->getHeaders()->addTextHeader('X-Noliae-PGP', 'inline');
            foreach ($to as $a) { $a = trim($a); if ($a && filter_var($a, FILTER_VALIDATE_EMAIL)) $email->addTo($a); }
            foreach (($opts['cc'] ?? []) as $a) { $a = trim($a); if ($a && filter_var($a, FILTER_VALIDATE_EMAIL)) $email->addCc($a); }
            if (! empty($opts['in_reply_to'])) {
                $email->getHeaders()->addTextHeader('In-Reply-To', $opts['in_reply_to']);
                $email->getHeaders()->addTextHeader('References', $opts['in_reply_to']);
            }
            $mailer->send($email);
            $this->mailbox->appendSent($from, $email->toString());
            return;
        }

        // Normalisation du HTML (nbsp / espaces multiples) + bloc liens S3.
        $html = trim($html);
        if ($html === '') {
            $html = '&nbsp;';
        }
        $html = preg_replace('/(?:&nbsp;|\xC2\xA0|&#160;|&#xA0;)+/iu', ' ', $html);
        $html = preg_replace_callback(
            '/(<[^>]+>)|([ \t]{2,})/u',
            fn ($m) => $m[1] !== '' ? $m[1] : ' ',
            $html
        );
        // Vire les paragraphes vides (<p><br></p>) — contenteditable les sème.
        $html = preg_replace('#<p>\s*(?:<br\s*/?>\s*)*</p>#i', '', $html);
        // Plus de 2 <br> à la suite → 2 max
        $html = preg_replace('#(?:<br\s*/?>\s*){3,}#i', '<br><br>', $html);
        if ($links) {
            $html .= $this->renderAttachmentsBlock($links);
        }

        // Version texte (fallback) dérivée du HTML.
        $text = trim(html_entity_decode(strip_tags(
            preg_replace('#<br\s*/?>|</p>|</div>|</li>#i', "\n", $html)
        )));
        if ($text === '') {
            $text = ' ';
        }

        // 3. Construction MANUELLE des parts en 8bit (au lieu du QP par défaut)
        //    → la source brute du mail reste lisible (UTF-8 direct, plus de =C3=A9).
        $textPart = new TextPart($text, 'utf-8', 'plain', '8bit');
        $htmlPart = new TextPart($html, 'utf-8', 'html',  '8bit');
        $alt      = new AlternativePart($textPart, $htmlPart);

        $parts = [$alt];

        // Joindre la clé PGP publique si l'utilisateur a activé l'option.
        if (! empty($opts['pgp_public_key']) && ! empty($opts['pgp_attach_public'])) {
            $parts[] = new DataPart(
                (string) $opts['pgp_public_key'],
                'noliae-pgp-public-key.asc',
                'application/pgp-keys'
            );
        }

        foreach ($inlineBin as $a) {
            $bin = base64_decode((string) ($a['b64'] ?? ''), true);
            if ($bin !== false && $bin !== '') {
                $parts[] = new DataPart($bin,
                    (string) ($a['name'] ?? 'fichier'),
                    (string) ($a['mime'] ?? 'application/octet-stream'));
            }
        }
        $body = count($parts) > 1 ? new MixedPart(...$parts) : $alt;

        $email = (new Email())
            ->from($sender)
            ->subject($subject !== '' ? $subject : '(sans objet)')
            ->setBody($body);

        foreach ($to as $addr) {
            $addr = trim($addr);
            if ($addr !== '' && filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                $email->addTo($addr);
            }
        }
        foreach (($opts['cc'] ?? []) as $addr) {
            $addr = trim($addr);
            if ($addr !== '' && filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                $email->addCc($addr);
            }
        }

        if (! empty($opts['in_reply_to'])) {
            $headers = $email->getHeaders();
            $headers->addTextHeader('In-Reply-To', $opts['in_reply_to']);
            $headers->addTextHeader('References', $opts['in_reply_to']);
        }
        if (! empty($opts['ask_receipt'])) {
            $headers = $email->getHeaders();
            $headers->addTextHeader('Disposition-Notification-To', $from);
            $headers->addTextHeader('Return-Receipt-To', $from);
            $headers->addTextHeader('X-Confirm-Reading-To', $from);
        }

        $mailer->send($email);
        $this->mailbox->appendSent($from, $email->toString());
    }

    /** Bloc HTML « pièces jointes » à coller en bas du mail. */
    private function renderAttachmentsBlock(array $links): string
    {
        $rows = '';
        $maxTtl = 0;
        foreach ($links as $l) {
            $name = htmlspecialchars($l['name'], ENT_QUOTES, 'UTF-8');
            $url  = htmlspecialchars($l['url'],  ENT_QUOTES, 'UTF-8');
            $size = $this->fmtSize((int) $l['size']);
            $ttl  = (int) ($l['ttl_days'] ?? AttachmentStore::TTL_DAYS);
            $maxTtl = max($maxTtl, $ttl);
            $rows .= '<tr>'
                . '<td style="padding:10px 14px;border-bottom:1px solid #f1f1f3">'
                .   '<div style="font-size:13px;font-weight:600;color:#15151a">📎 ' . $name . '</div>'
                .   '<div style="font-size:11px;color:#9ca3af;margin-top:2px">' . $size . ' · valable ' . $ttl . ' j</div>'
                . '</td>'
                . '<td align="right" style="padding:10px 14px;border-bottom:1px solid #f1f1f3">'
                .   '<a href="' . $url . '" '
                .     'style="display:inline-block;padding:6px 14px;border-radius:9999px;'
                .     'background:#FF4D2E;color:#fff;text-decoration:none;font-size:12px;font-weight:700">'
                .     'Télécharger</a>'
                . '</td>'
                . '</tr>';
        }
        $expiry = $maxTtl ?: AttachmentStore::TTL_DAYS;

        return '<div style="margin-top:28px;padding:14px;border:1px solid #f1f1f3;border-radius:14px;'
            . 'background:#fafaf7;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif">'
            . '<div style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;'
            .  'color:#FF4D2E;margin-bottom:8px">Pièces jointes · Noliae Mail</div>'
            . '<table cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse">'
            . $rows
            . '</table>'
            . '<div style="font-size:10.5px;color:#9ca3af;margin-top:10px">'
            .  'Liens valables ' . $expiry . ' jours · stockés sur l\'infrastructure souveraine Noliae.'
            . '</div>'
            . '</div>';
    }

    private function fmtSize(int $b): string
    {
        if ($b < 1024) {
            return $b . ' o';
        }
        if ($b < 1048576) {
            return round($b / 1024) . ' Ko';
        }
        return round($b / 1048576, 1) . ' Mo';
    }
}
