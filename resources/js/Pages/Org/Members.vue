<template>
  <AdminLayout title="Membres & rôles">
    <div v-if="flash?.success" class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ flash.success }}</div>
    <div v-if="errors.role" class="mb-4 px-4 py-2.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-sm">{{ errors.role }}</div>

    <section v-if="role === 'owner' && available.length" class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-200 dark:border-zinc-700 p-5 mb-5">
      <h2 class="font-bold mb-3">➕ Ajouter un membre</h2>
      <form @submit.prevent="add" class="grid grid-cols-1 md:grid-cols-3 gap-2">
        <select v-model="newMember.mail_account_id" required class="px-3 py-2 text-sm bg-white dark:bg-zinc-800 border rounded-xl md:col-span-2">
          <option value="" disabled>Compte mail à promouvoir…</option>
          <option v-for="a in available" :key="a.id" :value="a.id">{{ a.display_name || a.email }} ({{ a.email }})</option>
        </select>
        <select v-model="newMember.role" class="px-3 py-2 text-sm bg-white dark:bg-zinc-800 border rounded-xl">
          <option value="member">Member</option>
          <option value="support">Support</option>
          <option value="admin">Admin</option>
          <option value="owner">Owner</option>
        </select>
        <button type="submit" class="md:col-span-3 px-4 py-2 bg-[#FF4D2E] text-white rounded-xl text-sm font-bold">Ajouter au workspace</button>
      </form>
    </section>

    <section class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-200 dark:border-zinc-700 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="text-[10px] uppercase tracking-wider text-gray-500 bg-white dark:bg-zinc-800">
          <tr>
            <th class="px-4 py-2 text-left">Membre</th>
            <th class="px-4 py-2 text-left">Rôle</th>
            <th class="px-4 py-2 text-left">2FA</th>
            <th class="px-4 py-2"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="m in members" :key="m.id" class="border-t border-gray-100 dark:border-zinc-700">
            <td class="px-4 py-2.5">
              <div class="flex items-center gap-2">
                <img :src="`/webmail/avatar/${m.avatar_hash}?n=${encodeURIComponent(m.display_name || m.email)}`"
                  class="w-8 h-8 rounded-full bg-gray-200" alt=""/>
                <div>
                  <div class="font-semibold">{{ m.display_name || '—' }} <span v-if="m.email === me_email" class="text-[10px] text-emerald-600">(moi)</span></div>
                  <code class="text-xs text-gray-500 font-mono">{{ m.email }}</code>
                </div>
              </div>
            </td>
            <td class="px-4 py-2.5">
              <select v-if="role === 'owner' && m.email !== me_email" :value="m.role" @change="setRole(m, $event.target.value)"
                class="px-2 py-1 text-xs bg-white dark:bg-zinc-800 border rounded">
                <option value="member">Member</option>
                <option value="support">Support</option>
                <option value="admin">Admin</option>
                <option value="owner">Owner</option>
              </select>
              <span v-else :class="['text-[11px] uppercase font-bold px-2 py-0.5 rounded',
                m.role === 'owner' ? 'bg-purple-100 text-purple-700' :
                m.role === 'admin' ? 'bg-red-100 text-red-700' :
                m.role === 'support' ? 'bg-blue-100 text-blue-700' :
                'bg-gray-100 text-gray-600']">{{ m.role }}</span>
            </td>
            <td class="px-4 py-2.5">
              <span v-if="m.totp_enabled" class="text-emerald-600">✓ activée</span>
              <span v-else-if="isAdminRole(m.role)" class="text-rose-600 text-xs">⚠ requise</span>
              <span v-else class="text-gray-400 text-xs">—</span>
            </td>
            <td class="px-4 py-2.5 text-right">
              <button v-if="role === 'owner' && m.email !== me_email" @click="remove(m)" class="text-xs text-gray-400 hover:text-rose-600">Retirer</button>
            </td>
          </tr>
          <tr v-if="!members.length"><td colspan="4" class="text-center text-gray-400 py-6 text-sm">Aucun membre.</td></tr>
        </tbody>
      </table>
    </section>

    <p class="text-[11px] text-gray-500 mt-4">
      <strong>RBAC</strong> :
      <em>owner</em> tout · <em>admin</em> gère domaines/comptes/settings ·
      <em>support</em> lecture admin + reset password ·
      <em>member</em> mail standard (annuaire + carnet).
      <br/><strong>2FA TOTP obligatoire</strong> pour owner/admin/support — sans elle l'accès à <code>/admin</code> est bloqué.
    </p>
  </AdminLayout>
</template>
<script setup>
import { reactive, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminLayout from '../Admin/Layout.vue';
const props = defineProps({ org: Object, role: String, members: Array, available: Array, me_email: String });
const newMember = reactive({ mail_account_id: '', role: 'member' });
const flash = computed(() => usePage().props.flash);
const errors = computed(() => usePage().props.errors || {});
function add() { router.post('/org/members', newMember, { preserveScroll: true, onSuccess: () => { newMember.mail_account_id = ''; newMember.role = 'member'; } }); }
function setRole(m, role) { router.patch(`/org/members/${m.id}`, { role }, { preserveScroll: true }); }
function remove(m) { if (confirm(`Retirer ${m.email} de l'organisation ?`)) router.delete(`/org/members/${m.id}`, { preserveScroll: true }); }
function isAdminRole(r) { return ['owner','admin','support'].includes(r); }
</script>
