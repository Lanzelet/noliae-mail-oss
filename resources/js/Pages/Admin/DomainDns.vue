<template>
  <AdminLayout :title="`DNS — ${domain.name}`">
    <Link href="/admin/domains" class="text-sm text-gray-500 hover:text-[#FF4D2E] mb-4 inline-block">← Retour aux domaines</Link>
    <p class="text-sm text-gray-600 dark:text-gray-300 mb-6">
      Publie ces enregistrements chez ton registrar pour que <strong>{{ domain.name }}</strong> reçoive et envoie du courrier.
    </p>
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 p-5 font-mono text-xs leading-relaxed">
      <div class="mb-3"><span class="text-gray-400"># Pointe le domaine vers ce serveur</span></div>
      <div>{{ domain.name }}. <span class="text-[#FF4D2E]">IN A</span> {{ server_ip }}</div>
      <div class="mt-4 mb-1"><span class="text-gray-400"># Endpoint S3 (MinIO) pour les pièces jointes signées</span></div>
      <div>s3.{{ domain.name }}. <span class="text-[#FF4D2E]">IN A</span> {{ server_ip }}</div>
      <div class="mt-4 mb-1"><span class="text-gray-400"># Reçoit le courrier entrant</span></div>
      <div>{{ domain.name }}. <span class="text-[#FF4D2E]">IN MX 10</span> {{ domain.name }}.</div>
      <div class="mt-4 mb-1"><span class="text-gray-400"># SPF — autorise ce serveur à envoyer</span></div>
      <div>{{ domain.name }}. <span class="text-[#FF4D2E]">IN TXT</span> "v=spf1 mx -all"</div>
      <div class="mt-4 mb-1"><span class="text-gray-400"># DKIM — signature des mails sortants</span></div>
      <div v-if="dkim_pub" class="break-all">mail._domainkey.{{ domain.name }}. <span class="text-[#FF4D2E]">IN TXT</span> "v=DKIM1; k=rsa; p={{ dkim_pub }}"</div>
      <div v-else class="text-amber-600">⚠ Clé DKIM non générée — lance <code>docker exec noliae-rspamd rspamadm dkim_keygen -s mail -d {{ domain.name }} -k /etc/rspamd/dkim/{{ domain.name }}.key</code></div>
      <div class="mt-4 mb-1"><span class="text-gray-400"># DMARC — politique anti-spoofing</span></div>
      <div>_dmarc.{{ domain.name }}. <span class="text-[#FF4D2E]">IN TXT</span> "v=DMARC1; p=quarantine; rua=mailto:postmaster@{{ domain.name }}"</div>
      <div class="mt-4 mb-1"><span class="text-gray-400"># MTA-STS — chiffrement obligatoire en transit</span></div>
      <div>_mta-sts.{{ domain.name }}. <span class="text-[#FF4D2E]">IN TXT</span> "v=STSv1; id={{ new Date().toISOString().slice(0,10).replace(/-/g,'') }}01"</div>
      <div>mta-sts.{{ domain.name }}. <span class="text-[#FF4D2E]">IN A</span> {{ server_ip }}</div>
      <div class="mt-4 mb-1"><span class="text-gray-400"># TLS-RPT — rapports d'erreur TLS</span></div>
      <div>_smtp._tls.{{ domain.name }}. <span class="text-[#FF4D2E]">IN TXT</span> "v=TLSRPTv1; rua=mailto:tls-reports@{{ domain.name }}"</div>
    </div>

    <p class="text-xs text-amber-600 mt-6">
      ⚠ Vérifie le rDNS : ton IP {{ server_ip }} doit résoudre vers {{ domain.name }} dans la console de ton hébergeur. Sans ça les gros mailers (Gmail, Outlook) refuseront tes mails.
    </p>
  </AdminLayout>
</template>
<script setup>
import AdminLayout from './Layout.vue';
import { Link } from '@inertiajs/vue3';
defineProps({ domain: Object, server_ip: String, dkim_pub: String });
</script>
