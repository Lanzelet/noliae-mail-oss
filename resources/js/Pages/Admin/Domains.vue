<template>
  <AdminLayout title="Domaines">
    <form @submit.prevent="add" class="flex gap-2 mb-6 bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 p-4">
      <input v-model="form.name" type="text" required placeholder="exemple.fr"
             class="flex-1 px-3 py-2 border border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 rounded-lg text-sm focus:outline-none focus:border-[#FF4D2E]" />
      <button type="submit" class="px-5 py-2 bg-[#FF4D2E] text-white rounded-lg text-sm font-semibold hover:bg-[#df3c1f]">Ajouter</button>
    </form>
    <p v-if="errors.name" class="text-rose-600 text-sm mb-3">{{ errors.name }}</p>

    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-zinc-900 text-left text-[11px] uppercase tracking-wider text-gray-500">
          <tr><th class="px-4 py-3">Domaine</th><th class="px-4 py-3">Comptes</th><th class="px-4 py-3">Statut</th><th class="px-4 py-3 text-right">Actions</th></tr>
        </thead>
        <tbody>
          <tr v-for="d in domains" :key="d.id" class="border-t border-gray-100 dark:border-zinc-700">
            <td class="px-4 py-2.5 font-mono">
              {{ d.name }}
              <span v-if="d.is_primary" class="ml-2 text-[10px] uppercase font-bold bg-[#FF4D2E]/15 text-[#FF4D2E] px-1.5 py-0.5 rounded">primaire</span>
            </td>
            <td class="px-4 py-2.5">{{ d.accounts_count }}</td>
            <td class="px-4 py-2.5"><span :class="d.active ? 'text-emerald-600' : 'text-gray-400'">● {{ d.active ? 'actif' : 'désactivé' }}</span></td>
            <td class="px-4 py-2.5 text-right">
              <button v-if="!d.is_primary" @click="setPrimary(d)" class="text-xs text-[#FF4D2E] hover:underline mr-3" title="Domaine affiché par défaut sur /login">⭐ Définir primaire</button>
              <Link :href="`/admin/domains/${d.id}/dns`" class="text-xs text-gray-600 hover:text-[#FF4D2E] mr-3">📋 Records DNS</Link>
              <button @click="del(d)" class="text-xs text-rose-600 hover:underline">Supprimer</button>
            </td>
          </tr>
          <tr v-if="!domains.length"><td colspan="4" class="px-4 py-8 text-center text-gray-400">Aucun domaine. Ajoutes-en un ci-dessus.</td></tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
<script setup>
import AdminLayout from './Layout.vue';
import { reactive, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
defineProps({ domains: Array });
const form = reactive({ name: '' });
const errors = computed(() => usePage().props.errors || {});
function add() { router.post('/admin/domains', form, { preserveScroll: true, onSuccess: () => form.name = '' }); }
function del(d) { if (confirm(`Supprimer ${d.name} ?`)) router.delete(`/admin/domains/${d.id}`, { preserveScroll: true }); }
function setPrimary(d) { router.patch(`/admin/domains/${d.id}/primary`, {}, { preserveScroll: true }); }
</script>
