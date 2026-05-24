<template>
  <AdminLayout title="Anti-spam (rspamd)">
    <div v-if="!available" class="bg-amber-50 dark:bg-amber-950 border border-amber-200 dark:border-amber-800 rounded-2xl p-4 mb-4 text-sm text-amber-900 dark:text-amber-200">
      ⚠ rspamd n'est pas joignable à <code class="font-mono text-xs">{{ rspamd_url }}</code>.
      Vérifie que le service tourne et que <code class="font-mono">RSPAMD_URL</code> + <code class="font-mono">RSPAMD_PASSWORD</code> sont dans le <code class="font-mono">.env</code> du web container.
    </div>

    <template v-else-if="stat">
      <!-- Compteurs top -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 p-4">
          <div class="text-3xl font-black text-[#FF4D2E]">{{ fmt(stat.scanned) }}</div>
          <div class="text-[10px] uppercase tracking-wider text-gray-500 mt-1">Mails scannés</div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 p-4">
          <div class="text-3xl font-black text-rose-600">{{ fmt(stat.actions?.reject || 0) }}</div>
          <div class="text-[10px] uppercase tracking-wider text-gray-500 mt-1">Rejetés (spam)</div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 p-4">
          <div class="text-3xl font-black text-amber-500">{{ fmt(stat.actions?.greylist || 0) }}</div>
          <div class="text-[10px] uppercase tracking-wider text-gray-500 mt-1">Greylistés</div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 p-4">
          <div class="text-3xl font-black text-emerald-600">{{ fmt(stat.actions?.['no action'] || 0) }}</div>
          <div class="text-[10px] uppercase tracking-wider text-gray-500 mt-1">Sains</div>
        </div>
      </div>

      <!-- Détail actions : scale visuelle -->
      <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 p-5 mb-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-bold">Seuils d'action</h2>
          <span class="text-[11px] text-gray-500">Plus le score d'un mail est élevé, plus l'action est sévère</span>
        </div>

        <!-- Scale visuelle -->
        <div class="relative h-2 rounded-full bg-gradient-to-r from-emerald-400 via-amber-400 to-rose-500 mb-8">
          <template v-for="a in sortedActions" :key="a.action">
            <div v-if="a.value !== null" class="absolute -top-1 -translate-x-1/2"
                 :style="{ left: `${scalePosition(a.value)}%` }">
              <div :class="['w-4 h-4 rounded-full border-2 border-white shadow-md', actionDot(a.action)]"></div>
              <div class="absolute top-5 left-1/2 -translate-x-1/2 whitespace-nowrap text-[10px] font-bold text-gray-700 dark:text-gray-300">
                {{ a.value }}
              </div>
              <div class="absolute top-10 left-1/2 -translate-x-1/2 whitespace-nowrap text-[9px] uppercase tracking-wider text-gray-500">
                {{ actionShortLabel(a.action) }}
              </div>
            </div>
          </template>
        </div>

        <!-- Liste cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mt-12">
          <div v-for="a in sortedActions" :key="`c-${a.action}`"
               :class="['flex items-start gap-3 p-3 rounded-xl border', actionCardClass(a.action)]">
            <div :class="['w-8 h-8 rounded-lg flex items-center justify-center shrink-0 text-sm', actionIconBg(a.action)]">
              {{ actionIcon(a.action) }}
            </div>
            <div class="flex-1 min-w-0">
              <div class="font-semibold text-sm">{{ actionLabel(a.action) }}</div>
              <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">{{ actionDesc(a.action) }}</p>
              <div class="mt-1.5 flex items-baseline gap-1">
                <span v-if="a.value !== null" class="font-mono font-bold text-sm">{{ a.value }}</span>
                <span v-else class="text-xs text-gray-400 italic">par défaut</span>
                <span v-if="a.value !== null" class="text-[10px] text-gray-400 uppercase tracking-wider">pts</span>
              </div>
            </div>
          </div>
        </div>

        <p class="text-[11px] text-gray-500 mt-4 pt-3 border-t border-gray-100 dark:border-zinc-700">
          ⚙ Modifier les seuils : <code class="font-mono">/etc/rspamd/local.d/actions.conf</code> puis <code class="font-mono">systemctl reload rspamd</code>.
        </p>
      </div>

      <!-- Apprentissage (bayes) -->
      <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 p-5 mb-6">
        <h2 class="font-bold mb-3">Apprentissage bayésien</h2>
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <div class="text-2xl font-bold">{{ fmt(stat.learned || 0) }}</div>
            <div class="text-[11px] text-gray-500">Mails appris (total)</div>
          </div>
          <div>
            <div class="text-xl font-mono text-gray-600">{{ formatBytes(stat.bytes_allocated || 0) }}</div>
            <div class="text-[11px] text-gray-500">Mémoire allouée</div>
          </div>
        </div>
        <p class="text-[11px] text-gray-500 mt-3">
          Apprends un mail en spam : <code class="font-mono text-xs">rspamc learn_spam &lt; mail.eml</code> · en ham : <code class="font-mono text-xs">learn_ham</code>.
        </p>
      </div>

      <!-- History -->
      <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 overflow-hidden mb-6">
        <div class="p-5 border-b border-gray-100 dark:border-zinc-700 flex items-center justify-between">
          <h2 class="font-bold">Derniers mails scannés ({{ (history || []).length }})</h2>
          <button @click="refresh" class="text-xs text-[#FF4D2E] hover:underline">↻ Rafraîchir</button>
        </div>
        <div v-if="(history || []).length" class="overflow-x-auto">
          <table class="w-full text-xs">
            <thead class="bg-gray-50 dark:bg-zinc-900 text-left text-[10px] uppercase tracking-wider text-gray-500">
              <tr>
                <th class="px-3 py-2">Quand</th>
                <th class="px-3 py-2">From</th>
                <th class="px-3 py-2">To</th>
                <th class="px-3 py-2">Sujet</th>
                <th class="px-3 py-2 text-right">Score</th>
                <th class="px-3 py-2">Action</th>
                <th class="px-3 py-2 text-right">Taille</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(h, i) in history.slice(0, 30)" :key="i" class="border-t border-gray-100 dark:border-zinc-700">
                <td class="px-3 py-1.5 text-gray-500 whitespace-nowrap">{{ time(h.unix_time) }}</td>
                <td class="px-3 py-1.5 font-mono truncate max-w-[160px]">{{ h.from_mime || h.from_smtp || '—' }}</td>
                <td class="px-3 py-1.5 font-mono truncate max-w-[160px]">{{ (h.rcpt_mime || h.rcpt_smtp || [])[0] || '—' }}</td>
                <td class="px-3 py-1.5 truncate max-w-[200px]">{{ h.subject || '—' }}</td>
                <td class="px-3 py-1.5 text-right font-mono" :class="scoreColor(h.score)">{{ h.score?.toFixed(1) }}</td>
                <td class="px-3 py-1.5"><span :class="actionBadge(h.action)">{{ h.action }}</span></td>
                <td class="px-3 py-1.5 text-right text-gray-400">{{ formatBytes(h.size || 0) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="px-5 py-8 text-center text-sm text-gray-400">Aucun mail scanné encore — l'historique apparaîtra dès qu'un mail entrant transite par rspamd.</p>
      </div>

      <!-- Top symbols -->
      <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 p-5">
        <h2 class="font-bold mb-3">Symboles les plus impactants (top 30)</h2>
        <p class="text-[11px] text-gray-500 mb-3">Règles de scoring rspamd avec leur poids. Les négatifs réduisent le score (= légitime), les positifs l'augmentent (= spam).</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
          <div v-for="s in symbols" :key="s.name"
               class="flex items-start gap-2 p-2 bg-gray-50 dark:bg-zinc-900 rounded-lg">
            <span :class="['inline-block px-1.5 py-0.5 rounded text-[10px] font-bold font-mono shrink-0',
                          s.weight > 0 ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700']">
              {{ s.weight > 0 ? '+' : '' }}{{ s.weight.toFixed(1) }}
            </span>
            <div class="flex-1 min-w-0">
              <code class="text-xs font-bold font-mono">{{ s.name }}</code>
              <p class="text-[10px] text-gray-500 mt-0.5 truncate">{{ s.desc || s.group }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Errors -->
      <div v-if="errors && errors.length" class="mt-6 bg-rose-50 dark:bg-rose-950 border border-rose-200 dark:border-rose-800 rounded-2xl p-4">
        <h2 class="font-bold text-rose-700 dark:text-rose-300 mb-2">Erreurs récentes ({{ errors.length }})</h2>
        <ul class="space-y-1 text-xs font-mono text-rose-800 dark:text-rose-300">
          <li v-for="(e, i) in errors.slice(0, 10)" :key="i">{{ e.ts }} — {{ e.module }} — {{ e.message }}</li>
        </ul>
      </div>
    </template>
  </AdminLayout>
</template>
<script setup>
import AdminLayout from './Layout.vue';
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
const props = defineProps({ available: Boolean, stat: Object, actions: [Object, Array], history: Array, errors: Array, symbols: Array, rspamd_url: String });
function fmt(n) { return new Intl.NumberFormat('fr-FR').format(n); }
function formatBytes(b) { if (b < 1024) return b + ' B'; if (b < 1048576) return (b/1024).toFixed(1) + ' KB'; return (b/1048576).toFixed(1) + ' MB'; }
function time(unix) { return new Date(unix * 1000).toLocaleString('fr-FR'); }
function scoreColor(s) { if (s == null) return 'text-gray-400'; if (s >= 15) return 'text-rose-600 font-bold'; if (s >= 5) return 'text-amber-600 font-bold'; return 'text-emerald-600'; }
function actionBadge(a) {
  const base = 'inline-block px-1.5 py-0.5 rounded-full text-[10px] font-bold uppercase';
  if (a === 'reject') return base + ' bg-rose-100 text-rose-700';
  if (a === 'add header' || a === 'rewrite subject') return base + ' bg-amber-100 text-amber-700';
  if (a === 'greylist') return base + ' bg-blue-100 text-blue-700';
  return base + ' bg-emerald-100 text-emerald-700';
}
function actionDesc(name) {
  return {
    'reject':           'Refus définitif (réponse 5xx au sender).',
    'soft reject':      'Refus temporaire (4xx), le sender réessaie plus tard.',
    'add header':       'Mail accepté avec en-tête X-Spam ajouté pour filtre client.',
    'rewrite subject':  'Sujet préfixé "[SPAM]" pour avertir le destinataire.',
    'greylist':         'Demande de re-envoi 5 min plus tard (bloque les bots).',
    'no action':        'Mail propre, livré sans modification.',
  }[name] || '';
}
function actionLabel(name) {
  return {
    'reject': 'Reject', 'soft reject': 'Soft reject', 'add header': 'Add header',
    'rewrite subject': 'Rewrite subject', 'greylist': 'Greylist', 'no action': 'No action',
  }[name] || name;
}
function actionShortLabel(name) {
  return { 'reject': 'reject', 'soft reject': 'soft', 'add header': 'header',
           'rewrite subject': 'subject', 'greylist': 'grey', 'no action': 'pass' }[name] || name;
}
function actionIcon(name) {
  return { 'reject': '🚫', 'soft reject': '⏸', 'add header': '🏷',
           'rewrite subject': '✏️', 'greylist': '⏳', 'no action': '✓' }[name] || '·';
}
function actionIconBg(name) {
  return {
    'reject':          'bg-rose-100 text-rose-700 dark:bg-rose-950',
    'soft reject':     'bg-orange-100 text-orange-700 dark:bg-orange-950',
    'add header':      'bg-amber-100 text-amber-700 dark:bg-amber-950',
    'rewrite subject': 'bg-yellow-100 text-yellow-700 dark:bg-yellow-950',
    'greylist':        'bg-blue-100 text-blue-700 dark:bg-blue-950',
    'no action':       'bg-emerald-100 text-emerald-700 dark:bg-emerald-950',
  }[name] || 'bg-gray-100 text-gray-600';
}
function actionDot(name) {
  return {
    'reject':          'bg-rose-500',
    'soft reject':     'bg-orange-500',
    'add header':      'bg-amber-500',
    'rewrite subject': 'bg-yellow-500',
    'greylist':        'bg-blue-500',
    'no action':       'bg-emerald-500',
  }[name] || 'bg-gray-400';
}
function actionCardClass(name) {
  if (name === 'reject')      return 'border-rose-200 dark:border-rose-900 bg-rose-50/30 dark:bg-rose-950/30';
  if (name === 'soft reject') return 'border-orange-200 dark:border-orange-900 bg-orange-50/30 dark:bg-orange-950/30';
  if (name === 'no action')   return 'border-emerald-200 dark:border-emerald-900 bg-emerald-50/30 dark:bg-emerald-950/30';
  return 'border-gray-200 dark:border-zinc-700 bg-gray-50/50 dark:bg-zinc-900/30';
}

const sortedActions = computed(() => {
  const list = (Array.isArray(props.actions) ? props.actions : Object.entries(props.actions || {}).map(([action, value]) => ({ action, value })));
  return list.sort((a, b) => (a.value ?? -999) - (b.value ?? -999));
});
function scalePosition(value) {
  // Map 0-20 → 0-100% pour la scale (au-delà de 20 = 100%)
  const v = Math.max(0, Math.min(20, value));
  return (v / 20) * 100;
}
function refresh() { router.reload({ preserveScroll: true }); }
</script>
