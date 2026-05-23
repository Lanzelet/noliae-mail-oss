<template>
  <AdminLayout title="Comptes">
    <form @submit.prevent="create" class="grid grid-cols-1 md:grid-cols-6 gap-2 mb-6 bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 p-4">
      <input v-model="form.local" required placeholder="nom" class="md:col-span-2 px-3 py-2 border border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 rounded-lg text-sm focus:outline-none focus:border-[#FF4D2E]"/>
      <select v-model="form.domain_id" required class="px-3 py-2 border border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 rounded-lg text-sm">
        <option v-for="d in domains" :key="d.id" :value="d.id">@{{ d.name }}</option>
      </select>
      <input v-model="form.password" type="password" required minlength="10" placeholder="Mot de passe (10+ car.)" class="px-3 py-2 border border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 rounded-lg text-sm"/>
      <input v-model="form.display_name" placeholder="Nom affiché" class="px-3 py-2 border border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 rounded-lg text-sm"/>
      <button type="submit" class="px-4 py-2 bg-[#FF4D2E] text-white rounded-lg text-sm font-semibold hover:bg-[#df3c1f]">Créer</button>
    </form>
    <p v-if="errors.local" class="text-rose-600 text-sm mb-3">{{ errors.local }}</p>
    <p v-if="errors.password" class="text-rose-600 text-sm mb-3">{{ errors.password }}</p>

    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-zinc-900 text-left text-[11px] uppercase tracking-wider text-gray-500">
          <tr><th class="px-4 py-3">Email</th><th class="px-4 py-3">Nom</th><th class="px-4 py-3">Quota</th><th class="px-4 py-3">Statut</th><th class="px-4 py-3 text-right">Actions</th></tr>
        </thead>
        <tbody>
          <tr v-for="a in accounts" :key="a.id" class="border-t border-gray-100 dark:border-zinc-700">
            <td class="px-4 py-2.5 font-mono">{{ a.email }}</td>
            <td class="px-4 py-2.5">{{ a.display_name || '—' }}</td>
            <td class="px-4 py-2.5 text-gray-500">{{ (a.quota_bytes/1024/1024).toFixed(0) }} Mo</td>
            <td class="px-4 py-2.5"><span :class="a.active ? 'text-emerald-600' : 'text-amber-500'">● {{ a.active ? 'actif' : 'suspendu' }}</span></td>
            <td class="px-4 py-2.5 text-right whitespace-nowrap">
              <button @click="resetPwd(a)" class="text-xs text-gray-600 hover:text-[#FF4D2E] mr-3">🔑 Reset</button>
              <button @click="toggle(a)" class="text-xs text-amber-600 hover:underline mr-3">{{ a.active ? 'Suspendre' : 'Réactiver' }}</button>
              <button @click="del(a)" class="text-xs text-rose-600 hover:underline">Supprimer</button>
            </td>
          </tr>
          <tr v-if="!accounts.length"><td colspan="5" class="px-4 py-8 text-center text-gray-400">Aucun compte. Crée-en un ci-dessus.</td></tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
<script setup>
import AdminLayout from './Layout.vue';
import { reactive, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
const props = defineProps({ accounts: Array, domains: Array });
const form = reactive({ local: '', domain_id: props.domains?.[0]?.id || null, password: '', display_name: '' });
const errors = computed(() => usePage().props.errors || {});
function create() { router.post('/admin/accounts', form, { preserveScroll: true, onSuccess: () => { form.local=''; form.password=''; form.display_name=''; }}); }
function toggle(a) { router.patch(`/admin/accounts/${a.id}/toggle`, {}, { preserveScroll: true }); }
function del(a) { if (confirm(`Supprimer ${a.email} ? Cette action est définitive.`)) router.delete(`/admin/accounts/${a.id}`, { preserveScroll: true }); }
function resetPwd(a) {
  const p = prompt(`Nouveau mot de passe pour ${a.email} (10 car. min) :`);
  if (p && p.length >= 10) router.patch(`/admin/accounts/${a.id}/password`, { password: p }, { preserveScroll: true });
}
</script>
