<template>
  <AdminLayout title="Listes de diffusion">
    <div class="flex items-start gap-4 mb-6 p-4 bg-gradient-to-br from-violet-500/5 to-transparent border border-violet-500/15 rounded-2xl">
      <div class="w-10 h-10 rounded-xl bg-violet-500/15 flex items-center justify-center shrink-0">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
      </div>
      <div class="flex-1">
        <p class="text-sm text-gray-700 dark:text-gray-200 leading-relaxed">
          Un mail envoyé à une liste est redistribué à tous ses membres. Idéal pour
          <code class="px-1.5 py-0.5 bg-white dark:bg-zinc-900 rounded text-[11px] font-mono border border-gray-300 dark:border-zinc-600">team@</code>,
          <code class="px-1.5 py-0.5 bg-white dark:bg-zinc-900 rounded text-[11px] font-mono border border-gray-300 dark:border-zinc-600">support@</code>,
          <code class="px-1.5 py-0.5 bg-white dark:bg-zinc-900 rounded text-[11px] font-mono border border-gray-300 dark:border-zinc-600">annonces@</code>…
        </p>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">
          Choisis le mode d'accès : <strong>interne</strong> (membres du même domaine), <strong>domaines internes</strong> (tous les domaines servis ici), ou <strong>tout le monde</strong>.
        </p>
      </div>
    </div>

    <form @submit.prevent="create" class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-200 dark:border-zinc-700 p-5 mb-6 shadow-sm">
      <h2 class="text-[11px] font-bold uppercase tracking-wider text-gray-500 mb-3">Nouvelle liste</h2>
      <div class="grid grid-cols-1 md:grid-cols-12 gap-2">
        <div class="md:col-span-4 flex items-center bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 focus-within:border-violet-500 focus-within:ring-2 focus-within:ring-violet-500/15 overflow-hidden">
          <input v-model="form.local" required placeholder="team" class="flex-1 min-w-0 px-3 py-2.5 text-sm bg-transparent focus:outline-none font-mono"/>
          <span class="px-1.5 text-gray-300">@</span>
          <select v-model="form.domain_id" required class="px-2 py-2.5 text-sm bg-transparent border-l border-gray-200 dark:border-zinc-700 font-mono focus:outline-none">
            <option v-for="d in domains" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
        </div>
        <input v-model="form.display_name" placeholder="Nom affiché (optionnel)"
               class="md:col-span-3 px-3 py-2.5 text-sm bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl focus:outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/15"/>
        <select v-model="form.scope" required
                class="md:col-span-3 px-3 py-2.5 text-sm bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl focus:outline-none focus:border-violet-500">
          <option value="internal">🔒 Interne — membres du même domaine</option>
          <option value="internal_domains">🏢 Domaines internes — tous tes domaines</option>
          <option value="any">🌍 Tout le monde — public</option>
        </select>
        <button type="submit" class="md:col-span-2 px-5 py-2.5 bg-violet-600 text-white rounded-xl text-sm font-bold hover:bg-violet-700 active:scale-[0.98] transition shadow-sm">
          + Créer
        </button>
      </div>
      <p v-if="errors.local" class="mt-2 text-rose-600 text-xs">{{ errors.local }}</p>
    </form>

    <div v-if="lists.length" class="space-y-2">
      <Link v-for="l in lists" :key="l.id" :href="`/admin/lists/${l.id}`"
            class="block group bg-white dark:bg-zinc-900 rounded-2xl border border-gray-200 dark:border-zinc-700 p-4 hover:shadow-md hover:border-violet-500/30 transition">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-violet-100 dark:bg-violet-950 text-violet-600 flex items-center justify-center shrink-0">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <code class="text-sm font-mono font-semibold">{{ l.address }}</code>
              <span :class="scopeBadge(l.scope)">{{ scopeLabel(l.scope) }}</span>
              <span v-if="!l.active" class="text-[10px] uppercase font-bold text-amber-600">désactivée</span>
            </div>
            <p class="text-[11px] text-gray-500 mt-0.5">
              {{ l.display_name || '—' }} · {{ l.members_count }} membre{{ l.members_count !== 1 ? 's' : '' }}
            </p>
          </div>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-gray-300 group-hover:text-violet-500"><path d="M9 6l6 6-6 6"/></svg>
        </div>
      </Link>
    </div>

    <div v-else class="bg-white dark:bg-zinc-900 rounded-2xl border-2 border-dashed border-gray-200 dark:border-zinc-700 p-12 text-center">
      <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-violet-50 dark:bg-violet-950 flex items-center justify-center">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="1.8">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
        </svg>
      </div>
      <p class="text-sm font-semibold mb-1">Aucune liste créée</p>
      <p class="text-xs text-gray-500">Commence par <code class="text-[11px]">team@{{ domains[0]?.name || 'domain' }}</code> avec scope « Interne ».</p>
    </div>
  </AdminLayout>
</template>
<script setup>
import AdminLayout from './Layout.vue';
import { reactive, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
const props = defineProps({ lists: Array, domains: Array });
const form = reactive({ local: '', domain_id: props.domains?.[0]?.id || null, display_name: '', scope: 'internal' });
const errors = computed(() => usePage().props.errors || {});
function create() {
  router.post('/admin/lists', form, { preserveScroll: true, onSuccess: () => { form.local=''; form.display_name=''; } });
}
function scopeLabel(s) { return { internal: '🔒 Interne', internal_domains: '🏢 Domaines internes', any: '🌍 Public' }[s] || s; }
function scopeBadge(s) {
  const base = 'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider';
  if (s === 'internal') return base + ' bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300';
  if (s === 'internal_domains') return base + ' bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300';
  return base + ' bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300';
}
</script>
