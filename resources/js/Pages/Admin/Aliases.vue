<template>
  <AdminLayout title="Forwards / Aliases">
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">
      Redirige tout le courrier reçu sur une adresse vers une ou plusieurs autres adresses
      (interne ou externe). Exemple : <code class="px-1.5 py-0.5 bg-gray-100 dark:bg-zinc-800 rounded text-xs">contact@example.com</code> → <code class="px-1.5 py-0.5 bg-gray-100 dark:bg-zinc-800 rounded text-xs">jean@gmail.com</code>.
    </p>

    <form @submit.prevent="add" class="grid grid-cols-1 md:grid-cols-7 gap-2 mb-6 bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 p-4">
      <div class="md:col-span-3">
        <input v-model="form.source_local" required placeholder="nom"
               class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 rounded-l-lg text-sm focus:outline-none focus:border-[#FF4D2E]" />
      </div>
      <div class="md:col-span-1">
        <select v-model="form.domain_id" required
                class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 rounded-r-lg text-sm">
          <option v-for="d in domains" :key="d.id" :value="d.id">@{{ d.name }}</option>
        </select>
      </div>
      <div class="md:col-span-2 flex items-center justify-center text-gray-400 text-xl">→</div>
      <div class="md:col-span-2 -ml-4 md:-ml-32 md:col-start-5">
        <input v-model="form.destination" type="email" required placeholder="destination@example.com"
               class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 rounded-lg text-sm focus:outline-none focus:border-[#FF4D2E]" />
      </div>
      <button type="submit"
              class="md:col-span-1 px-4 py-2 bg-[#FF4D2E] text-white rounded-lg text-sm font-semibold hover:bg-[#df3c1f]">
        Créer
      </button>
    </form>
    <p v-if="errors.source_local || errors.destination" class="text-rose-600 text-sm mb-3">
      {{ errors.source_local || errors.destination }}
    </p>

    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-zinc-900 text-left text-[11px] uppercase tracking-wider text-gray-500">
          <tr>
            <th class="px-4 py-3">Adresse source</th>
            <th class="px-4 py-3 w-10"></th>
            <th class="px-4 py-3">Destination</th>
            <th class="px-4 py-3">Statut</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="a in aliases" :key="a.id" class="border-t border-gray-100 dark:border-zinc-700">
            <td class="px-4 py-2.5 font-mono">{{ a.source }}</td>
            <td class="px-4 py-2.5 text-center text-gray-400">→</td>
            <td class="px-4 py-2.5 font-mono">{{ a.destination }}</td>
            <td class="px-4 py-2.5">
              <span :class="a.active ? 'text-emerald-600' : 'text-amber-500'">● {{ a.active ? 'actif' : 'désactivé' }}</span>
            </td>
            <td class="px-4 py-2.5 text-right whitespace-nowrap">
              <button @click="toggle(a)" class="text-xs text-amber-600 hover:underline mr-3">
                {{ a.active ? 'Désactiver' : 'Activer' }}
              </button>
              <button @click="del(a)" class="text-xs text-rose-600 hover:underline">Supprimer</button>
            </td>
          </tr>
          <tr v-if="!aliases.length">
            <td colspan="5" class="px-4 py-8 text-center text-gray-400">
              Aucun forward configuré. Crées-en un ci-dessus.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <p class="mt-4 text-[11px] text-gray-500 dark:text-gray-400">
      💡 Astuce : la source doit appartenir à un domaine géré ici. La destination peut être n'importe quelle adresse mail valide (interne ou externe — Gmail, Outlook, etc.). Plusieurs destinations possibles pour une même source : crée plusieurs lignes.
    </p>
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
function add() {
  router.post('/admin/aliases', form, {
    preserveScroll: true,
    onSuccess: () => { form.source_local = ''; form.destination = ''; },
  });
}
function toggle(a) { router.patch(`/admin/aliases/${a.id}/toggle`, {}, { preserveScroll: true }); }
function del(a)    { if (confirm(`Supprimer le forward ${a.source} → ${a.destination} ?`)) router.delete(`/admin/aliases/${a.id}`, { preserveScroll: true }); }
</script>
