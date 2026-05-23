<?php

/*
 * Noliae Mail OSS — configuration unifiée.
 * Toutes les valeurs sont surchargeables via .env (voir .env.example).
 */
return [
    /* IMAP/SMTP — pointe vers tes services internes (dovecot, postfix) */
    'imap_host'   => env('MAIL_IMAP_HOST', 'dovecot'),
    'imap_port'   => (int) env('MAIL_IMAP_PORT', 143),
    'imap_encryption' => env('MAIL_IMAP_ENCRYPTION', 'starttls'),
    'imap_validate_cert' => (bool) env('MAIL_IMAP_VALIDATE_CERT', false),
    'smtp_host'   => env('MAIL_SMTP_HOST', 'postfix'),
    'smtp_port'   => (int) env('MAIL_SMTP_PORT', 25),
    'master_user' => env('MAIL_MASTER_USER', 'masteruser'),
    'master_pass' => env('MAIL_MASTER_PASS', ''),
    /* Quota par boîte par défaut : 5 Go */
    'quota_bytes' => (int) env('MAIL_QUOTA_BYTES', 5 * 1024 * 1024 * 1024),
    /* Domaine principal servi (utilisé pour suggestions de comptes) */
    'primary_domain' => env('MAIL_DOMAIN', 'example.com'),
    /* URL publique du webmail (utilisée pour les liens dans les mails sortants) */
    'app_url' => env('APP_URL', 'http://localhost'),
];
