<template>
  <AdminLayout title="Journal d'audit">
    <p class="text-sm text-gray-500 mb-5">
      500 dernières actions administratives. Historique conservé indéfiniment en DB pour traçabilité (RGPD : <code class="text-xs">DELETE FROM audit_log</code> pour purger).
    </p>

    <div v-if="entries.length" class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 overflow-hidden shadow-sm">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-zinc-900 text-left text-[11px] uppercase tracking-wider text-gray-500">
          <tr>
            <th class="px-4 py-3">Quand</th>
            <th class="px-4 py-3">Qui</th>
            <th class="px-4 py-3">Action</th>
            <th class="px-4 py-3">Cible</th>
            <th class="px-4 py-3">IP</th>
            <th class="px-4 py-3 text-right">Détails</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="e in entries" :key="e.id" class="border-t border-gray-100 dark:border-zinc-700 hover:bg-gray-50 dark:hover:bg-zinc-900/50">
            <td class="px-4 py-2 text-xs text-gray-500 whitespace-nowrap font-mono">{{ relTime(e.created_at) }}</td>
            <td class="px-4 py-2 font-mono text-xs">{{ e.actor_email }}</td>
            <td class="px-4 py-2">
              <span :class="actionBadge(e.action)">{{ e.action }}</span>
            </td>
            <td class="px-4 py-2 font-mono text-xs text-gray-600 dark:text-gray-300 truncate max-w-xs">{{ e.target || '—' }}</td>
            <td class="px-4 py-2 text-xs text-gray-400 font-mono">{{ e.ip || '—' }}</td>
            <td class="px-4 py-2 text-right">
              <button v-if="e.metadata" @click="toggle(e.id)" class="text-[11px] text-gray-500 hover:text-[#FF4D2E]">
                {{ open[e.id] ? '▲' : '▼' }}
              </button>
            </td>
          </tr>
          <template v-for="e in entries" :key="`m-${e.id}`">
            <tr v-if="open[e.id] && e.metadata" class="border-t border-gray-100 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-900">
              <td colspan="6" class="px-4 py-2">
                <pre class="text-[11px] text-gray-600 dark:text-gray-300 overflow-x-auto">{{ JSON.stringify(JSON.parse(e.metadata), null, 2) }}</pre>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <div v-else class="bg-white dark:bg-zinc-800 rounded-2xl border-2 border-dashed border-gray-200 dark:border-zinc-700 p-12 text-center">
      <p class="text-sm text-gray-500">Aucune action admin loguée pour le moment.</p>
    </div>
  </AdminLayout>
</template>
<script setup>
import AdminLayout from './Layout.vue';
import { reactive } from 'vue';
defineProps({ entries: Array });
const open = reactive({});
function toggle(id) { open[id] = !open[id]; }
function relTime(iso) {
  const d = new Date(iso); const now = Date.now(); const diff = (now - d) / 1000;
  if (diff < 60) return Math.round(diff) + 's';
  if (diff < 3600) return Math.round(diff / 60) + 'min';
  if (diff < 86400) return Math.round(diff / 3600) + 'h';
  return Math.round(diff / 86400) + 'j';
}
function actionBadge(a) {
  const base = 'inline-block px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider';
  if (a.includes('.create') || a.includes('.grant') || a.includes('.activate')) return base + ' bg-emerald-50 text-emerald-700';
  if (a.includes('.delete') || a.includes('.revoke')) return base + ' bg-rose-50 text-rose-700';
  if (a.includes('.suspend')) return base + ' bg-amber-50 text-amber-700';
  return base + ' bg-blue-50 text-blue-700';
}
</script>
