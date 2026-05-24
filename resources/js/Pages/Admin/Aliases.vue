<template>
  <AdminLayout title="Forwards / Aliases">
    <!-- Header explicatif -->
    <div class="flex items-start gap-4 mb-6 p-4 bg-gradient-to-br from-[#FF4D2E]/5 to-transparent border border-[#FF4D2E]/15 rounded-2xl">
      <div class="w-10 h-10 rounded-xl bg-[#FF4D2E]/15 flex items-center justify-center shrink-0">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FF4D2E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 7h13l-3-3m3 3l-3 3"/>
          <path d="M21 17H8l3-3m-3 3l3 3"/>
        </svg>
      </div>
      <div class="flex-1">
        <p class="text-sm text-gray-700 dark:text-gray-200 leading-relaxed">
          Redirige automatiquement les mails reçus sur une adresse vers une ou plusieurs autres
          (interne ou externe). Aucun changement aux en-têtes — c'est un vrai forward SMTP.
        </p>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">
          Exemple : <code class="px-1.5 py-0.5 bg-white dark:bg-zinc-900 rounded text-[11px] font-mono border border-gray-200 dark:border-zinc-700">contact@{{ defaultDomain }}</code>
          <span class="mx-2 text-gray-400">→</span>
          <code class="px-1.5 py-0.5 bg-white dark:bg-zinc-900 rounded text-[11px] font-mono border border-gray-200 dark:border-zinc-700">jean@gmail.com</code>
        </p>
      </div>
    </div>

    <!-- Formulaire création -->
    <form @submit.prevent="add" class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 p-5 mb-6 shadow-sm">
      <h2 class="text-[11px] font-bold uppercase tracking-wider text-gray-500 mb-3">Nouveau forward</h2>

      <div class="flex flex-col md:flex-row items-stretch md:items-center gap-2">
        <!-- Source : nom + domaine -->
        <div class="flex flex-1 items-center bg-gray-50 dark:bg-zinc-900 rounded-xl border border-gray-200 dark:border-zinc-700 focus-within:border-[#FF4D2E] focus-within:ring-2 focus-within:ring-[#FF4D2E]/15 transition overflow-hidden">
          <input v-model="form.source_local" required placeholder="nom"
                 class="flex-1 min-w-0 px-3.5 py-2.5 text-sm bg-transparent focus:outline-none font-mono" />
          <span class="px-2 text-gray-300 dark:text-zinc-600">@</span>
          <select v-model="form.domain_id" required
                  class="px-2 py-2.5 text-sm bg-transparent border-l border-gray-200 dark:border-zinc-700 focus:outline-none font-mono">
            <option v-for="d in domains" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
        </div>

        <!-- Flèche -->
        <div class="hidden md:flex items-center justify-center w-10 h-10 rounded-full bg-[#FF4D2E]/10 text-[#FF4D2E] shrink-0">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 12h14M13 6l6 6-6 6"/>
          </svg>
        </div>

        <!-- Destination -->
        <input v-model="form.destination" type="email" required placeholder="destination@exemple.com"
               class="flex-1 min-w-0 px-3.5 py-2.5 text-sm bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl font-mono focus:outline-none focus:border-[#FF4D2E] focus:ring-2 focus:ring-[#FF4D2E]/15 transition" />

        <!-- Submit -->
        <button type="submit"
                class="px-5 py-2.5 bg-[#FF4D2E] text-white rounded-xl text-sm font-bold hover:bg-[#df3c1f] active:scale-[0.98] transition shadow-sm whitespace-nowrap">
          + Créer
        </button>
      </div>

      <div v-if="errors.source_local || errors.destination" class="mt-3 flex items-start gap-2 text-rose-600 text-xs">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="shrink-0 mt-0.5">
          <circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>
        </svg>
        <span>{{ errors.source_local || errors.destination }}</span>
      </div>
    </form>

    <!-- Liste -->
    <div v-if="aliases.length" class="space-y-2">
      <div v-for="a in aliases" :key="a.id"
           class="group bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 p-4 hover:shadow-md hover:border-[#FF4D2E]/30 transition">
        <div class="flex flex-col md:flex-row md:items-center gap-3">
          <!-- Source -->
          <div class="flex-1 min-w-0">
            <code class="text-sm font-mono font-semibold text-gray-900 dark:text-gray-100 truncate block">
              {{ a.source }}
            </code>
            <span class="text-[10px] text-gray-400 uppercase tracking-wider">Source</span>
          </div>

          <!-- Flèche avec statut -->
          <div class="flex items-center gap-2 shrink-0">
            <span :class="[
              'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider',
              a.active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                       : 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300']">
              <span :class="['w-1.5 h-1.5 rounded-full', a.active ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500']"></span>
              {{ a.active ? 'actif' : 'désactivé' }}
            </span>
            <div class="flex items-center justify-center w-7 h-7 rounded-full bg-gray-100 dark:bg-zinc-700 text-gray-500 group-hover:bg-[#FF4D2E]/15 group-hover:text-[#FF4D2E] transition">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14M13 6l6 6-6 6"/>
              </svg>
            </div>
          </div>

          <!-- Destination -->
          <div class="flex-1 min-w-0 text-right md:text-left">
            <code class="text-sm font-mono font-semibold text-gray-900 dark:text-gray-100 truncate block">
              {{ a.destination }}
            </code>
            <span class="text-[10px] text-gray-400 uppercase tracking-wider">Destination</span>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-1 shrink-0 md:opacity-0 md:group-hover:opacity-100 transition">
            <button @click="toggle(a)" :title="a.active ? 'Désactiver' : 'Activer'"
                    class="p-2 rounded-lg text-gray-500 hover:bg-amber-50 hover:text-amber-600 dark:hover:bg-amber-950 transition">
              <svg v-if="a.active" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/>
              </svg>
              <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="6,4 20,12 6,20"/>
              </svg>
            </button>
            <button @click="del(a)" title="Supprimer"
                    class="p-2 rounded-lg text-gray-500 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950 transition">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/>
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="bg-white dark:bg-zinc-800 rounded-2xl border-2 border-dashed border-gray-200 dark:border-zinc-700 p-12 text-center">
      <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-gray-100 dark:bg-zinc-700 flex items-center justify-center">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="text-gray-400">
          <path d="M3 7h13l-3-3m3 3l-3 3"/>
          <path d="M21 17H8l3-3m-3 3l3 3"/>
        </svg>
      </div>
      <p class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1">Aucun forward configuré</p>
      <p class="text-xs text-gray-500 dark:text-gray-400">Crée ton premier alias dans le formulaire ci-dessus.</p>
    </div>

    <!-- Astuces -->
    <details class="mt-6 group">
      <summary class="cursor-pointer text-xs text-gray-500 hover:text-[#FF4D2E] transition flex items-center gap-1.5 list-none">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="group-open:rotate-90 transition">
          <path d="M9 6l6 6-6 6"/>
        </svg>
        Comment ça marche
      </summary>
      <div class="mt-3 pl-5 text-[12px] text-gray-500 dark:text-gray-400 space-y-1.5 leading-relaxed">
        <p>• La <strong>source</strong> doit appartenir à un domaine géré ici (onglet Domaines).</p>
        <p>• La <strong>destination</strong> peut être interne (autre boîte de ce serveur) ou externe (Gmail, Outlook, etc.).</p>
        <p>• Pour rediriger vers <strong>plusieurs</strong> destinations, crée plusieurs aliases avec la même source.</p>
        <p>• <strong>Aucune réécriture</strong> de l'expéditeur : le destinataire voit le mail comme s'il avait été envoyé directement à son adresse.</p>
        <p>• Les <strong>retries SMTP</strong> sont automatiques : si le serveur destinataire greylist, les mails arrivent dans les 15-60 min.</p>
      </div>
    </details>
  </AdminLayout>
</template>
<script setup>
import AdminLayout from './Layout.vue';
import { reactive, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
const props = defineProps({ aliases: Array, domains: Array });
const form = reactive({
  source_local: '',
  domain_id:    props.domains?.[0]?.id || null,
  destination:  '',
});
const errors = computed(() => usePage().props.errors || {});
const defaultDomain = computed(() => props.domains?.[0]?.name || 'example.com');

function add() {
  router.post('/admin/aliases', form, {
    preserveScroll: true,
    onSuccess: () => { form.source_local = ''; form.destination = ''; },
  });
}
function toggle(a) {
  router.patch(`/admin/aliases/${a.id}/toggle`, {}, { preserveScroll: true });
}
function del(a) {
  if (confirm(`Supprimer le forward ${a.source} → ${a.destination} ?`)) {
    router.delete(`/admin/aliases/${a.id}`, { preserveScroll: true });
  }
}
</script>
