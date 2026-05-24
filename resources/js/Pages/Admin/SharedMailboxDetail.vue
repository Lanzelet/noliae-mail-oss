<template>
  <AdminLayout :title="mailbox.display_name">
    <Link href="/admin/shared" class="text-sm text-gray-500 hover:text-blue-600 mb-4 inline-flex items-center gap-1.5">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 6l-6 6 6 6"/></svg>
      Toutes les boîtes partagées
    </Link>

    <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 p-5 mb-5 shadow-sm">
      <div class="flex items-start gap-4">
        <div class="w-14 h-14 rounded-2xl bg-blue-100 dark:bg-blue-950 text-blue-600 flex items-center justify-center shrink-0 font-bold text-xl">
          {{ mailbox.display_name.charAt(0).toUpperCase() }}
        </div>
        <div class="flex-1">
          <h2 class="text-lg font-bold">{{ mailbox.display_name }}</h2>
          <code class="text-xs text-gray-500 font-mono">{{ mailbox.email }}</code>
          <p class="text-[11px] text-gray-400 mt-1">Quota {{ Math.round(mailbox.quota_bytes/1024/1024/1024) }} Go</p>
        </div>
        <button @click="del" class="px-3 py-1.5 rounded-lg text-xs font-bold bg-rose-50 text-rose-700 hover:bg-rose-100 transition">Supprimer</button>
      </div>
    </div>

    <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 overflow-hidden shadow-sm">
      <div class="p-5 border-b border-gray-100 dark:border-zinc-700">
        <h2 class="font-bold mb-1">Utilisateurs ayant accès ({{ acls.length }})</h2>
        <p class="text-[11px] text-gray-500 mb-3">Donne accès à un compte existant. Il pourra basculer sur cette boîte depuis son webmail.</p>
        <form @submit.prevent="grant" class="grid grid-cols-1 md:grid-cols-7 gap-2">
          <input v-model="form.user_email" type="email" required placeholder="utilisateur@domain"
                 class="md:col-span-4 px-3.5 py-2 text-sm bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl font-mono focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/15"/>
          <select v-model="form.role" class="md:col-span-2 px-3 py-2 text-sm bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl focus:outline-none focus:border-blue-500">
            <option value="read">👁 Lecture seule</option>
            <option value="send">✉️ Lecture + envoi</option>
            <option value="manage">🔑 Gestion complète</option>
          </select>
          <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-bold hover:bg-blue-700">+ Ajouter</button>
        </form>
        <p v-if="errors.user_email" class="mt-2 text-rose-600 text-xs">{{ errors.user_email }}</p>
      </div>

      <ul v-if="acls.length" class="divide-y divide-gray-100 dark:divide-zinc-700">
        <li v-for="acl in acls" :key="acl.id" class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 dark:hover:bg-zinc-900">
          <div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-400 to-cyan-500 text-white flex items-center justify-center text-xs font-bold">
            {{ acl.user_email.charAt(0).toUpperCase() }}
          </div>
          <div class="flex-1 min-w-0">
            <code class="text-sm font-mono font-semibold">{{ acl.user_email }}</code>
            <p class="text-[10px] text-gray-500 uppercase tracking-wider mt-0.5">{{ roleLabel(acl.role) }}</p>
          </div>
          <button @click="revoke(acl)" class="text-xs text-rose-600 hover:underline">Révoquer</button>
        </li>
      </ul>
      <p v-else class="px-5 py-8 text-center text-sm text-gray-400">Personne n'a encore accès. Ajoute un membre au-dessus.</p>
    </div>
  </AdminLayout>
</template>
<script setup>
import AdminLayout from './Layout.vue';
import { reactive, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
const props = defineProps({ mailbox: Object, acls: Array });
const form = reactive({ user_email: '', role: 'send' });
const errors = computed(() => usePage().props.errors || {});
function grant() { router.post(`/admin/shared/${props.mailbox.id}/acls`, form, { preserveScroll: true, onSuccess: () => form.user_email = '' }); }
function revoke(acl) { if (confirm(`Révoquer l'accès de ${acl.user_email} ?`)) router.delete(`/admin/shared/${props.mailbox.id}/acls/${acl.id}`, { preserveScroll: true }); }
function del() { if (confirm(`Supprimer la boîte ${props.mailbox.email} ? Cette action est DÉFINITIVE et perd les mails.`)) router.delete(`/admin/shared/${props.mailbox.id}`); }
function roleLabel(r) { return { read: 'Lecture seule', send: 'Lecture + envoi', manage: 'Gestion complète' }[r] || r; }
</script>
