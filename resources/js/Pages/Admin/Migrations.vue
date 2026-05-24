<template>
  <AdminLayout title="Migrations IMAP">
    <!-- Bannière info -->
    <div class="flex items-start gap-4 mb-6 p-4 bg-gradient-to-br from-cyan-500/5 to-transparent border border-cyan-500/15 rounded-2xl">
      <div class="w-10 h-10 rounded-xl bg-cyan-500/15 flex items-center justify-center shrink-0">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#06b6d4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="7 10 12 5 17 10"/><polyline points="7 14 12 19 17 14"/>
        </svg>
      </div>
      <div class="flex-1">
        <p class="text-sm text-gray-700 dark:text-gray-200 leading-relaxed">
          Importe tous les mails d'un compte existant (Gmail, Outlook, OVH, autre serveur Noliae…) vers une boîte de ce serveur.
          Utilise <code class="px-1.5 py-0.5 bg-white dark:bg-zinc-900 rounded text-[11px] font-mono border border-gray-300 dark:border-zinc-600">imapsync</code> sous le capot — idempotent (relançable sans dupliquer).
        </p>
        <p v-if="!imapsync_available" class="text-xs text-rose-600 font-bold mt-2">
          ⚠ <code>imapsync</code> n'est pas installé dans le container web — ajoute-le au Dockerfile (`apt install imapsync`) puis rebuild.
        </p>
      </div>
    </div>

    <!-- Form nouveau import -->
    <form @submit.prevent="start" class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-200 dark:border-zinc-700 p-5 mb-6 shadow-sm">
      <h2 class="text-[11px] font-bold uppercase tracking-wider text-gray-500 mb-3">Nouvelle migration</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="space-y-2">
          <div class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Compte SOURCE (à importer)</div>
          <input v-model="form.source_user" type="email" required placeholder="utilisateur@ancien-serveur.com"
                 class="w-full px-3 py-2 text-sm bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl font-mono focus:outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/15"/>
          <input v-model="form.source_pass" type="password" required placeholder="Mot de passe IMAP"
                 class="w-full px-3 py-2 text-sm bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl focus:outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/15"/>
          <div class="grid grid-cols-3 gap-2">
            <input v-model="form.source_host" required placeholder="imap.ancien.com"
                   class="col-span-2 px-3 py-2 text-sm bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl font-mono focus:outline-none focus:border-cyan-500"/>
            <input v-model.number="form.source_port" type="number" required placeholder="993"
                   class="px-3 py-2 text-sm bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl font-mono focus:outline-none focus:border-cyan-500"/>
          </div>
          <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
            <input v-model="form.source_ssl" type="checkbox" class="w-4 h-4 accent-cyan-500"/>
            SSL/TLS (port 993 par défaut, sinon 143 sans SSL)
          </label>
          <p class="text-[11px] text-gray-500">
            Astuce raccourcis :
            <button type="button" @click="preset('gmail')" class="text-cyan-600 hover:underline mr-2">Gmail</button>·
            <button type="button" @click="preset('o365')" class="text-cyan-600 hover:underline mx-2">Outlook 365</button>·
            <button type="button" @click="preset('ovh')" class="text-cyan-600 hover:underline ml-2">OVH</button>
          </p>
        </div>

        <div class="space-y-2">
          <div class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Compte DESTINATION (sur ce serveur)</div>
          <select v-model="form.dest_account_id" required
                  class="w-full px-3 py-2 text-sm bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl font-mono focus:outline-none focus:border-cyan-500">
            <option :value="null">— Choisir un compte —</option>
            <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.email }}</option>
          </select>
          <p class="text-[11px] text-gray-500">
            Le compte doit déjà exister (voir l'onglet Comptes). La migration utilise le master password Dovecot, donc tu n'as pas besoin du mot de passe du compte destination.
          </p>
          <p v-if="errors.source_user" class="text-rose-600 text-xs">{{ errors.source_user }}</p>
          <p v-if="errors.dest_account_id" class="text-rose-600 text-xs">{{ errors.dest_account_id }}</p>
          <button type="submit" :disabled="!imapsync_available"
                  class="w-full mt-2 px-5 py-2.5 bg-cyan-600 text-white rounded-xl text-sm font-bold hover:bg-cyan-700 active:scale-[0.98] transition shadow-sm disabled:opacity-40 disabled:cursor-not-allowed">
            🚀 Démarrer la migration
          </button>
        </div>
      </div>
    </form>

    <!-- Liste -->
    <div v-if="migrations.length" class="space-y-2">
      <div v-for="m in migrations" :key="m.id" class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-200 dark:border-zinc-700 p-4 shadow-sm">
        <div class="flex items-center gap-3">
          <div :class="['w-10 h-10 rounded-xl flex items-center justify-center shrink-0', statusBg(m.status)]">
            <component :is="statusIcon(m.status)" />
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <code class="text-sm font-mono font-semibold">{{ m.source_user }}</code>
              <span class="text-gray-300">→</span>
              <code class="text-sm font-mono font-semibold">{{ m.dest_email }}</code>
              <span :class="statusBadge(m.status)">{{ statusLabel(m.status) }}</span>
            </div>
            <div class="text-[11px] text-gray-500 mt-1">
              {{ m.source_host }}:{{ m.source_port }} ·
              <span v-if="m.total_messages">{{ m.copied_messages }} / {{ m.total_messages }} messages</span>
              <span v-else-if="m.copied_messages">{{ m.copied_messages }} messages copiés</span>
              · {{ relTime(m.created_at) }}
            </div>
            <!-- Progress bar -->
            <div v-if="m.status === 'running' && m.total_messages" class="mt-2 h-1.5 bg-gray-100 dark:bg-zinc-700 rounded-full overflow-hidden">
              <div class="h-full bg-cyan-500 transition-all" :style="{ width: `${Math.round(100 * m.copied_messages / m.total_messages)}%` }"></div>
            </div>
            <!-- Error -->
            <p v-if="m.error" class="mt-2 text-xs text-rose-600">{{ m.error }}</p>
          </div>
          <div class="flex flex-col items-end gap-1 shrink-0">
            <button v-if="m.status === 'running'" @click="cancel(m)" class="text-xs text-rose-600 hover:underline">Annuler</button>
            <button @click="toggle(m)" class="text-xs text-gray-500 hover:text-cyan-600">{{ open[m.id] ? 'Masquer log' : 'Voir log' }}</button>
          </div>
        </div>
        <pre v-if="open[m.id] && m.log_tail" class="mt-3 p-3 bg-gray-900 text-gray-100 rounded-lg text-[11px] overflow-x-auto max-h-64 font-mono whitespace-pre-wrap">{{ m.log_tail }}</pre>
      </div>
    </div>

    <div v-else class="bg-white dark:bg-zinc-900 rounded-2xl border-2 border-dashed border-gray-200 dark:border-zinc-700 p-12 text-center">
      <p class="text-sm font-semibold mb-1">Aucune migration lancée</p>
      <p class="text-xs text-gray-500">Importe ta première boîte mail dans le formulaire ci-dessus.</p>
    </div>
  </AdminLayout>
</template>
<script setup>
import AdminLayout from './Layout.vue';
import { reactive, computed, h, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
const props = defineProps({ migrations: Array, accounts: Array, imapsync_available: Boolean });
const form = reactive({
  source_host: '', source_port: 993, source_ssl: true,
  source_user: '', source_pass: '',
  dest_account_id: null,
});
const errors = computed(() => usePage().props.errors || {});
const open = reactive({});
function toggle(m) { open[m.id] = !open[m.id]; }
function preset(p) {
  if (p === 'gmail')  { form.source_host = 'imap.gmail.com';        form.source_port = 993; form.source_ssl = true; }
  if (p === 'o365')   { form.source_host = 'outlook.office365.com'; form.source_port = 993; form.source_ssl = true; }
  if (p === 'ovh')    { form.source_host = 'ssl0.ovh.net';          form.source_port = 993; form.source_ssl = true; }
}
function start() {
  router.post('/admin/migrations', form, { preserveScroll: true, onSuccess: () => { form.source_pass = ''; } });
}
function cancel(m) {
  if (confirm(`Annuler la migration #${m.id} (${m.source_user}) ?`)) router.post(`/admin/migrations/${m.id}/cancel`);
}
function relTime(iso) {
  const d = new Date(iso); const diff = (Date.now() - d) / 1000;
  if (diff < 60) return Math.round(diff) + 's';
  if (diff < 3600) return Math.round(diff / 60) + 'min';
  if (diff < 86400) return Math.round(diff / 3600) + 'h';
  return Math.round(diff / 86400) + 'j';
}
function statusLabel(s) { return { pending: 'En attente', running: 'En cours', success: 'Terminé', failed: 'Échec', cancelled: 'Annulé' }[s] || s; }
function statusBg(s) {
  return { pending: 'bg-gray-100 text-gray-500', running: 'bg-cyan-100 text-cyan-600',
           success: 'bg-emerald-100 text-emerald-600', failed: 'bg-rose-100 text-rose-600',
           cancelled: 'bg-gray-100 text-gray-500' }[s];
}
function statusBadge(s) {
  const base = 'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider';
  if (s === 'running') return base + ' bg-cyan-50 text-cyan-700';
  if (s === 'success') return base + ' bg-emerald-50 text-emerald-700';
  if (s === 'failed')  return base + ' bg-rose-50 text-rose-700';
  return base + ' bg-gray-100 text-gray-500';
}
function statusIcon(s) {
  if (s === 'running') return () => h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2.5, class: 'animate-spin' }, [
    h('path', { strokeLinecap: 'round', d: 'M21 12a9 9 0 1 1-6.219-8.56' })
  ]);
  if (s === 'success') return () => h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2.5 }, [
    h('polyline', { points: '20 6 9 17 4 12' })
  ]);
  if (s === 'failed' || s === 'cancelled') return () => h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2.5 }, [
    h('line', { x1: 18, y1: 6, x2: 6, y2: 18 }),
    h('line', { x1: 6, y1: 6, x2: 18, y2: 18 })
  ]);
  return () => h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2 }, [
    h('circle', { cx: 12, cy: 12, r: 10 }), h('polyline', { points: '12 6 12 12 16 14' })
  ]);
}

// Polling toutes les 5s tant qu'au moins une migration tourne
let poll;
onMounted(() => {
  poll = setInterval(() => {
    if (props.migrations.some(m => ['pending', 'running'].includes(m.status))) {
      router.reload({ only: ['migrations'], preserveScroll: true });
    }
  }, 5000);
});
onUnmounted(() => clearInterval(poll));
</script>
