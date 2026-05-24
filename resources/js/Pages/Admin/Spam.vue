<template>
  <AdminLayout title="Quarantaine spam">
    <div v-if="!available" class="bg-amber-50 dark:bg-amber-950 border border-amber-200 dark:border-amber-800 rounded-2xl p-4 mb-4 text-sm text-amber-900 dark:text-amber-200">
      ⚠ Le daemon Postfix queue n'est pas joignable. Vérifie qu'<code class="font-mono">oss-queue-daemon.service</code> tourne sur le host
      et que <code class="font-mono">POSTFIX_QUEUE_HOST/TOKEN</code> sont dans le <code class="font-mono">.env</code>.
    </div>

    <div class="flex items-start gap-4 mb-6 p-4 bg-gradient-to-br from-rose-500/5 to-transparent border border-rose-500/15 rounded-2xl">
      <div class="w-10 h-10 rounded-xl bg-rose-500/15 flex items-center justify-center shrink-0">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#e11d48" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
          <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
      </div>
      <div class="flex-1">
        <p class="text-sm text-gray-700 dark:text-gray-200 leading-relaxed">
          Mails marqués comme spam par rspamd (score &gt; seuil reject) sont automatiquement placés en
          file <code class="font-mono text-xs">HOLD</code> de Postfix au lieu d'être rejetés. Tu peux les inspecter ici, puis les
          <strong>libérer</strong> (delivery forcée) si faux positif, ou les <strong>supprimer</strong> définitivement.
        </p>
      </div>
      <button v-if="held.length" @click="releaseAll"
              class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-xs font-bold hover:bg-emerald-700 transition shrink-0">
        ✓ Tout libérer ({{ held.length }})
      </button>
    </div>

    <div v-if="held.length" class="space-y-2">
      <div v-for="m in held" :key="m.queue_id"
           class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 overflow-hidden shadow-sm">
        <div class="p-4 flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-rose-100 dark:bg-rose-950 text-rose-600 flex items-center justify-center shrink-0">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <code class="text-xs font-mono font-bold text-gray-900 dark:text-gray-100">{{ m.queue_id }}</code>
              <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-full bg-rose-100 text-rose-700">
                HOLD
              </span>
              <span class="text-[10px] text-gray-400">{{ relTime(m.arrival_time) }}</span>
              <span class="text-[10px] text-gray-400">· {{ formatBytes(m.size) }}</span>
            </div>
            <p class="text-sm mt-1">
              <span class="text-gray-500">De</span>
              <code class="font-mono ml-1">{{ m.sender || '(sender vide)' }}</code>
              <span class="text-gray-500 mx-2">→</span>
              <code class="font-mono">{{ (m.recipients || []).join(', ') }}</code>
            </p>
          </div>
          <div class="flex items-center gap-1 shrink-0">
            <button @click="toggle(m)" class="px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-100 dark:hover:bg-zinc-700 rounded-lg transition">
              {{ open[m.queue_id] ? '▲ Masquer' : '▼ Aperçu' }}
            </button>
            <button @click="release(m)" class="px-3 py-1.5 text-xs font-bold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 rounded-lg transition">
              ✓ Livrer
            </button>
            <button @click="del(m)" class="px-3 py-1.5 text-xs font-bold bg-rose-50 text-rose-700 hover:bg-rose-100 rounded-lg transition">
              🗑 Supprimer
            </button>
          </div>
        </div>
        <div v-if="open[m.queue_id]" class="border-t border-gray-100 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-900 p-3">
          <pre v-if="previews[m.queue_id]" class="text-[11px] font-mono whitespace-pre-wrap text-gray-700 dark:text-gray-300 max-h-96 overflow-y-auto">{{ previews[m.queue_id] }}</pre>
          <p v-else class="text-xs text-gray-400 italic">Chargement…</p>
        </div>
      </div>
    </div>

    <div v-else-if="available" class="bg-white dark:bg-zinc-800 rounded-2xl border-2 border-dashed border-emerald-200 dark:border-emerald-800 p-12 text-center">
      <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-emerald-50 dark:bg-emerald-950 flex items-center justify-center">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
      </div>
      <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300 mb-1">Aucun spam en quarantaine</p>
      <p class="text-xs text-gray-500">Les mails marqués spam apparaîtront ici pour validation manuelle.</p>
    </div>
  </AdminLayout>
</template>
<script setup>
import AdminLayout from './Layout.vue';
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
const props = defineProps({ available: Boolean, held: Array });
const open = reactive({});
const previews = reactive({});

async function toggle(m) {
  open[m.queue_id] = !open[m.queue_id];
  if (open[m.queue_id] && !previews[m.queue_id]) {
    const r = await fetch(`/admin/spam/${m.queue_id}/show`);
    previews[m.queue_id] = (await r.text()).slice(0, 8192);
  }
}
function release(m) {
  if (confirm(`Libérer le mail ${m.queue_id} vers ${m.recipients?.join(', ')} ?`)) {
    router.post(`/admin/spam/${m.queue_id}/release`);
  }
}
function del(m) {
  if (confirm(`Supprimer définitivement le mail ${m.queue_id} ?`)) {
    router.delete(`/admin/spam/${m.queue_id}`);
  }
}
function releaseAll() {
  if (confirm(`Libérer TOUS les mails HOLD (${props.held.length}) ?`)) {
    router.post(`/admin/spam/release-all`);
  }
}
function relTime(unix) {
  if (!unix) return '?';
  const d = (Date.now() / 1000 - unix);
  if (d < 60) return Math.round(d) + 's';
  if (d < 3600) return Math.round(d / 60) + 'min';
  if (d < 86400) return Math.round(d / 3600) + 'h';
  return Math.round(d / 86400) + 'j';
}
function formatBytes(b) { if (b < 1024) return b + ' B'; if (b < 1048576) return (b/1024).toFixed(1) + ' KB'; return (b/1048576).toFixed(1) + ' MB'; }
</script>
