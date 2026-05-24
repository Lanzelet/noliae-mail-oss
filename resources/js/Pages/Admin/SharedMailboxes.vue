<template>
  <AdminLayout title="Boîtes mail partagées">
    <div class="flex items-start gap-4 mb-6 p-4 bg-gradient-to-br from-blue-500/5 to-transparent border border-blue-500/15 rounded-2xl">
      <div class="w-10 h-10 rounded-xl bg-blue-500/15 flex items-center justify-center shrink-0">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
          <polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>
        </svg>
      </div>
      <div class="flex-1">
        <p class="text-sm text-gray-700 dark:text-gray-200 leading-relaxed">
          Une boîte mail partagée (comme <code class="px-1.5 py-0.5 bg-white dark:bg-zinc-900 rounded text-[11px] font-mono border border-gray-200 dark:border-zinc-700">support@</code>) à laquelle plusieurs utilisateurs accèdent depuis leur propre compte — exactement comme dans Outlook Microsoft 365.
        </p>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">
          Une fois l'accès donné, l'utilisateur trouve un sélecteur d'identité en haut du webmail pour basculer entre sa boîte perso et chaque boîte partagée.
        </p>
      </div>
    </div>

    <form @submit.prevent="create" class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 p-5 mb-6 shadow-sm">
      <h2 class="text-[11px] font-bold uppercase tracking-wider text-gray-500 mb-3">Nouvelle boîte partagée</h2>
      <div class="grid grid-cols-1 md:grid-cols-12 gap-2">
        <div class="md:col-span-4 flex items-center bg-gray-50 dark:bg-zinc-900 rounded-xl border border-gray-200 dark:border-zinc-700 focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/15 overflow-hidden">
          <input v-model="form.local" required placeholder="support" class="flex-1 min-w-0 px-3 py-2.5 text-sm bg-transparent focus:outline-none font-mono"/>
          <span class="px-1.5 text-gray-300">@</span>
          <select v-model="form.domain_id" required class="px-2 py-2.5 text-sm bg-transparent border-l border-gray-200 dark:border-zinc-700 font-mono focus:outline-none">
            <option v-for="d in domains" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
        </div>
        <input v-model="form.display_name" required placeholder="Nom affiché (« Support Client »)"
               class="md:col-span-4 px-3 py-2.5 text-sm bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/15"/>
        <input v-model.number="form.quota_mb" type="number" min="100" placeholder="Quota Mo (10240)"
               class="md:col-span-2 px-3 py-2.5 text-sm bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl focus:outline-none focus:border-blue-500"/>
        <button type="submit" class="md:col-span-2 px-5 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-bold hover:bg-blue-700 active:scale-[0.98] transition shadow-sm">
          + Créer
        </button>
      </div>
      <p v-if="errors.local" class="mt-2 text-rose-600 text-xs">{{ errors.local }}</p>
    </form>

    <div v-if="mailboxes.length" class="space-y-2">
      <Link v-for="m in mailboxes" :key="m.id" :href="`/admin/shared/${m.id}`"
            class="block group bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 p-4 hover:shadow-md hover:border-blue-500/30 transition">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-950 text-blue-600 flex items-center justify-center shrink-0 font-bold">
            {{ (m.display_name || m.email).charAt(0).toUpperCase() }}
          </div>
          <div class="flex-1 min-w-0">
            <div class="font-semibold text-sm">{{ m.display_name }}</div>
            <code class="text-xs text-gray-500 font-mono">{{ m.email }}</code>
          </div>
          <div class="text-right">
            <div class="text-sm font-bold">{{ m.members_count }}</div>
            <div class="text-[10px] text-gray-400 uppercase">membre{{ m.members_count !== 1 ? 's' : '' }}</div>
          </div>
          <div class="text-right">
            <div class="text-sm font-bold">{{ Math.round(m.quota_bytes/1024/1024/1024) }} Go</div>
            <div class="text-[10px] text-gray-400 uppercase">quota</div>
          </div>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-gray-300 group-hover:text-blue-500 ml-2"><path d="M9 6l6 6-6 6"/></svg>
        </div>
      </Link>
    </div>

    <div v-else class="bg-white dark:bg-zinc-800 rounded-2xl border-2 border-dashed border-gray-200 dark:border-zinc-700 p-12 text-center">
      <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-blue-50 dark:bg-blue-950 flex items-center justify-center">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="1.8">
          <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
        </svg>
      </div>
      <p class="text-sm font-semibold mb-1">Aucune boîte partagée</p>
      <p class="text-xs text-gray-500">Commence par <code class="text-[11px]">support@{{ domains[0]?.name || 'domain' }}</code> et donne accès à ton équipe.</p>
    </div>
  </AdminLayout>
</template>
<script setup>
import AdminLayout from './Layout.vue';
import { reactive, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
const props = defineProps({ mailboxes: Array, domains: Array });
const form = reactive({ local: '', domain_id: props.domains?.[0]?.id || null, display_name: '', quota_mb: 10240 });
const errors = computed(() => usePage().props.errors || {});
function create() { router.post('/admin/shared', form, { preserveScroll: true, onSuccess: () => { form.local=''; form.display_name=''; } }); }
</script>
