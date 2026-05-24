<template>
  <AdminLayout title="Tableau de bord" :admin_email="admin_email" :app_domain="app_domain">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
      <div class="bg-white dark:bg-zinc-900 rounded-xl border border-gray-200 dark:border-zinc-700 p-5">
        <div class="text-3xl font-bold text-[#FF4D2E]">{{ stats.domains }}</div>
        <div class="text-xs text-gray-500 uppercase tracking-wider mt-1">Domaines</div>
      </div>
      <div class="bg-white dark:bg-zinc-900 rounded-xl border border-gray-200 dark:border-zinc-700 p-5">
        <div class="text-3xl font-bold">{{ stats.accounts }}</div>
        <div class="text-xs text-gray-500 uppercase tracking-wider mt-1">Comptes</div>
      </div>
      <div class="bg-white dark:bg-zinc-900 rounded-xl border border-gray-200 dark:border-zinc-700 p-5">
        <div class="text-3xl font-bold text-emerald-600">{{ stats.active }}</div>
        <div class="text-xs text-gray-500 uppercase tracking-wider mt-1">Actifs</div>
      </div>
      <div class="bg-white dark:bg-zinc-900 rounded-xl border border-gray-200 dark:border-zinc-700 p-5">
        <div class="text-3xl font-bold text-amber-500">{{ stats.suspended }}</div>
        <div class="text-xs text-gray-500 uppercase tracking-wider mt-1">Suspendus</div>
      </div>
    </div>

    <h2 class="text-lg font-bold mb-3">Comptes récents</h2>
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-gray-200 dark:border-zinc-700 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-white dark:bg-zinc-800 text-left text-[11px] uppercase tracking-wider text-gray-500">
          <tr>
            <th class="px-4 py-3">Email</th>
            <th class="px-4 py-3">Nom</th>
            <th class="px-4 py-3">Statut</th>
            <th class="px-4 py-3">Créé</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="a in recent" :key="a.id" class="border-t border-gray-100 dark:border-zinc-700">
            <td class="px-4 py-2.5 font-mono">{{ a.email }}</td>
            <td class="px-4 py-2.5">{{ a.display_name || '—' }}</td>
            <td class="px-4 py-2.5">
              <span :class="a.active ? 'text-emerald-600' : 'text-amber-500'">
                ● {{ a.active ? 'actif' : 'suspendu' }}
              </span>
            </td>
            <td class="px-4 py-2.5 text-gray-500">{{ new Date(a.created_at).toLocaleDateString('fr-FR') }}</td>
          </tr>
          <tr v-if="!recent.length"><td colspan="4" class="px-4 py-8 text-center text-gray-400">Aucun compte pour le moment.</td></tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
<script setup>
import AdminLayout from './Layout.vue';
defineProps({ stats: Object, recent: Array, admin_email: String, app_domain: String });
</script>
