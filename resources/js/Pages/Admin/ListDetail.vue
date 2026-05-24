<template>
  <AdminLayout :title="list.address">
    <Link href="/admin/lists" class="text-sm text-gray-500 hover:text-violet-600 mb-4 inline-flex items-center gap-1.5">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 6l-6 6 6 6"/></svg>
      Toutes les listes
    </Link>

    <!-- Carte info liste -->
    <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 p-5 mb-5 shadow-sm">
      <div class="flex items-start gap-4">
        <div class="w-12 h-12 rounded-2xl bg-violet-100 dark:bg-violet-950 text-violet-600 flex items-center justify-center shrink-0">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        </div>
        <div class="flex-1">
          <h2 class="text-lg font-bold">{{ list.display_name || list.address }}</h2>
          <code class="text-xs text-gray-500 font-mono">{{ list.address }}</code>
        </div>
        <button @click="toggleList" :class="['px-3 py-1.5 rounded-lg text-xs font-bold transition', list.active ? 'bg-amber-50 text-amber-700 hover:bg-amber-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100']">
          {{ list.active ? 'Désactiver' : 'Activer' }}
        </button>
        <button @click="del" class="px-3 py-1.5 rounded-lg text-xs font-bold bg-rose-50 text-rose-700 hover:bg-rose-100 transition">Supprimer</button>
      </div>

      <!-- Sélecteur scope -->
      <div class="mt-5 pt-5 border-t border-gray-100 dark:border-zinc-700">
        <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500 mb-2">Mode d'accès</p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
          <button v-for="opt in scopes" :key="opt.value" @click="setScope(opt.value)"
                  :class="['p-3 rounded-xl border text-left transition',
                          list.scope === opt.value
                            ? 'bg-violet-50 border-violet-300 dark:bg-violet-950 dark:border-violet-700 ring-2 ring-violet-500/20'
                            : 'bg-white border-gray-200 hover:border-violet-300 dark:bg-zinc-900 dark:border-zinc-700']">
            <div class="flex items-center gap-2 mb-1">
              <span class="text-base">{{ opt.icon }}</span>
              <span class="font-semibold text-sm">{{ opt.label }}</span>
            </div>
            <p class="text-[11px] text-gray-500">{{ opt.desc }}</p>
          </button>
        </div>
      </div>
    </div>

    <!-- Membres -->
    <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 overflow-hidden shadow-sm">
      <div class="p-5 border-b border-gray-100 dark:border-zinc-700">
        <h2 class="font-bold mb-3">Membres de la liste ({{ members.length }})</h2>
        <form @submit.prevent="addMember" class="flex gap-2">
          <input v-model="newMember" type="email" required placeholder="email@exemple.com"
                 class="flex-1 px-3.5 py-2 text-sm bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl font-mono focus:outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/15"/>
          <button type="submit" class="px-5 py-2 bg-violet-600 text-white rounded-xl text-sm font-bold hover:bg-violet-700">+ Ajouter</button>
        </form>
        <p v-if="errors.member_email" class="mt-2 text-rose-600 text-xs">{{ errors.member_email }}</p>
      </div>
      <ul v-if="members.length" class="divide-y divide-gray-100 dark:divide-zinc-700">
        <li v-for="m in members" :key="m.id" class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 dark:hover:bg-zinc-900">
          <div class="w-8 h-8 rounded-full bg-gradient-to-br from-violet-400 to-fuchsia-500 text-white flex items-center justify-center text-xs font-bold">
            {{ m.member_email.charAt(0).toUpperCase() }}
          </div>
          <code class="flex-1 text-sm font-mono">{{ m.member_email }}</code>
          <button @click="removeMember(m)" class="text-xs text-rose-600 hover:underline">Retirer</button>
        </li>
      </ul>
      <p v-else class="px-5 py-8 text-center text-sm text-gray-400">Aucun membre. Ajoute le premier au-dessus.</p>
    </div>
  </AdminLayout>
</template>
<script setup>
import AdminLayout from './Layout.vue';
import { ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
const props = defineProps({ list: Object, members: Array });
const newMember = ref('');
const errors = computed(() => usePage().props.errors || {});
const scopes = [
  { value: 'internal',          icon: '🔒', label: 'Interne',          desc: 'Seuls les membres du même domaine peuvent envoyer.' },
  { value: 'internal_domains',  icon: '🏢', label: 'Domaines internes',desc: 'Tous les domaines servis par ce serveur.' },
  { value: 'any',               icon: '🌍', label: 'Tout le monde',    desc: 'Public — n\'importe qui peut envoyer.' },
];
function setScope(s) { router.patch(`/admin/lists/${props.list.id}/scope`, { scope: s }, { preserveScroll: true }); }
function toggleList() { router.patch(`/admin/lists/${props.list.id}/toggle`, {}, { preserveScroll: true }); }
function del() { if (confirm(`Supprimer la liste ${props.list.address} ?`)) router.delete(`/admin/lists/${props.list.id}`); }
function addMember() { router.post(`/admin/lists/${props.list.id}/members`, { member_email: newMember.value }, { preserveScroll: true, onSuccess: () => newMember.value = '' }); }
function removeMember(m) { router.delete(`/admin/lists/${props.list.id}/members/${m.id}`, { preserveScroll: true }); }
</script>
