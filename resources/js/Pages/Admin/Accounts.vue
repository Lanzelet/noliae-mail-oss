<template>
  <AdminLayout title="Comptes">
    <form @submit.prevent="create" class="grid grid-cols-1 md:grid-cols-6 gap-2 mb-6 bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 p-4">
      <input v-model="form.local" required placeholder="nom" class="md:col-span-2 px-3 py-2 border border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 rounded-lg text-sm focus:outline-none focus:border-[#FF4D2E]"/>
      <select v-model="form.domain_id" required class="px-3 py-2 border border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 rounded-lg text-sm">
        <option v-for="d in domains" :key="d.id" :value="d.id">@{{ d.name }}{{ d.is_primary ? ' ⭐' : '' }}</option>
      </select>
      <input v-model="form.password" type="password" required minlength="10" placeholder="Mot de passe (10+ car.)" class="px-3 py-2 border border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 rounded-lg text-sm"/>
      <input v-model="form.display_name" placeholder="Nom affiché" class="px-3 py-2 border border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 rounded-lg text-sm"/>
      <button type="submit" class="px-4 py-2 bg-[#FF4D2E] text-white rounded-lg text-sm font-semibold hover:bg-[#df3c1f]">Créer</button>
    </form>
    <p v-if="errors.local && !editing" class="text-rose-600 text-sm mb-3">{{ errors.local }}</p>
    <p v-if="errors.password && !editing" class="text-rose-600 text-sm mb-3">{{ errors.password }}</p>

    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-zinc-900 text-left text-[11px] uppercase tracking-wider text-gray-500">
          <tr><th class="px-4 py-3">Email</th><th class="px-4 py-3">Nom</th><th class="px-4 py-3">Quota</th><th class="px-4 py-3">Statut</th><th class="px-4 py-3 text-right">Actions</th></tr>
        </thead>
        <tbody>
          <tr v-for="a in accounts" :key="a.id" class="border-t border-gray-100 dark:border-zinc-700">
            <td class="px-4 py-2.5 font-mono">{{ a.email }}</td>
            <td class="px-4 py-2.5">{{ a.display_name || '—' }}</td>
            <td class="px-4 py-2.5">
              <div class="flex items-center gap-1">
                <input type="number" min="10" :value="Math.round(a.quota_bytes/1024/1024)"
                       @blur="changeQuota(a, $event)" @keydown.enter="changeQuota(a, $event)"
                       class="w-20 px-2 py-1 text-sm border border-gray-200 dark:border-zinc-600 dark:bg-zinc-900 rounded focus:outline-none focus:border-[#FF4D2E]" />
                <span class="text-xs text-gray-500">Mo</span>
              </div>
            </td>
            <td class="px-4 py-2.5"><span :class="a.active ? 'text-emerald-600' : 'text-amber-500'">● {{ a.active ? 'actif' : 'suspendu' }}</span></td>
            <td class="px-4 py-2.5 text-right whitespace-nowrap">
              <button @click="openEdit(a)" class="text-xs text-blue-600 hover:underline mr-3">✏️ Éditer</button>
              <button @click="resetPwd(a)" class="text-xs text-gray-600 hover:text-[#FF4D2E] mr-3">🔑 Reset</button>
              <button @click="toggle(a)" class="text-xs text-amber-600 hover:underline mr-3">{{ a.active ? 'Suspendre' : 'Réactiver' }}</button>
              <button @click="del(a)" class="text-xs text-rose-600 hover:underline">Supprimer</button>
            </td>
          </tr>
          <tr v-if="!accounts.length"><td colspan="5" class="px-4 py-8 text-center text-gray-400">Aucun compte. Crée-en un ci-dessus.</td></tr>
        </tbody>
      </table>
    </div>

    <!-- ─── Modale d'édition complète ─── -->
    <Teleport to="body">
      <div v-if="editing" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @click.self="closeEdit">
        <div class="bg-white dark:bg-zinc-800 rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
          <div class="px-5 py-4 border-b border-gray-200 dark:border-zinc-700 flex items-center justify-between sticky top-0 bg-white dark:bg-zinc-800">
            <h2 class="font-bold text-lg">✏️ Éditer le compte</h2>
            <button @click="closeEdit" class="w-8 h-8 rounded-md hover:bg-gray-100 dark:hover:bg-zinc-900 text-xl leading-none">×</button>
          </div>
          <form @submit.prevent="saveEdit" class="p-5 space-y-4">
            <!-- Email = local + domaine -->
            <div>
              <label class="block text-[10px] uppercase tracking-wider text-gray-500 mb-1">Adresse email</label>
              <div class="flex items-center bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-lg overflow-hidden focus-within:border-[#FF4D2E]">
                <input v-model="edit.local" required pattern="^[a-zA-Z0-9][a-zA-Z0-9._\-]{1,63}$"
                  class="flex-1 bg-transparent px-3 py-2 text-sm focus:outline-none"/>
                <span class="px-2 text-gray-500">@</span>
                <select v-model="edit.domain_id" required class="bg-transparent pr-3 py-2 text-sm focus:outline-none">
                  <option v-for="d in domains" :key="d.id" :value="d.id">{{ d.name }}{{ d.is_primary ? ' ⭐' : '' }}</option>
                </select>
              </div>
              <p v-if="errors.local" class="text-rose-600 text-xs mt-1">{{ errors.local }}</p>
              <p class="text-[11px] text-gray-500 mt-1">⚠ Changer l'email renomme aussi la boîte mail Dovecot (le user devra reconfigurer ses clients IMAP/SMTP).</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <Field label="Nom affiché"   v-model="edit.display_name"/>
              <Field label="Prénom"        v-model="edit.first_name"/>
              <Field label="Nom de famille" v-model="edit.last_name"/>
              <Field label="Fonction"      v-model="edit.job_title"/>
              <Field label="Société"       v-model="edit.company"/>
              <Field label="Téléphone fixe" v-model="edit.phone" type="tel"/>
              <Field label="Mobile"        v-model="edit.mobile" type="tel"/>
              <div>
                <label class="block text-[10px] uppercase tracking-wider text-gray-500">Quota (Mo)</label>
                <input v-model.number="edit.quota_mb" type="number" min="10" required
                  class="w-full mt-1 px-3 py-2 text-sm bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-lg focus:border-[#FF4D2E] focus:outline-none"/>
              </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100 dark:border-zinc-700">
              <button type="button" @click="closeEdit" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900 dark:hover:text-gray-100">Annuler</button>
              <button type="submit" class="px-5 py-2 bg-[#FF4D2E] text-white rounded-lg text-sm font-bold hover:bg-[#df3c1f]">💾 Sauvegarder</button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
<script setup>
import AdminLayout from './Layout.vue';
import Field from '../../Components/Field.vue';
import { reactive, computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
const props = defineProps({ accounts: Array, domains: Array });
const primaryDomain = computed(() => props.domains?.find(d => d.is_primary) || props.domains?.[0]);
const form = reactive({ local: '', domain_id: primaryDomain.value?.id || null, password: '', display_name: '' });
const errors = computed(() => usePage().props.errors || {});

function create() { router.post('/admin/accounts', form, { preserveScroll: true, onSuccess: () => { form.local=''; form.password=''; form.display_name=''; }}); }
function toggle(a) { router.patch(`/admin/accounts/${a.id}/toggle`, {}, { preserveScroll: true }); }
function del(a) { if (confirm(`Supprimer ${a.email} ? Cette action est définitive.`)) router.delete(`/admin/accounts/${a.id}`, { preserveScroll: true }); }
function resetPwd(a) {
  const p = prompt(`Nouveau mot de passe pour ${a.email} (10 car. min) :`);
  if (p && p.length >= 10) router.patch(`/admin/accounts/${a.id}/password`, { password: p }, { preserveScroll: true });
}
function changeQuota(a, ev) {
  const mb = parseInt(ev.target.value);
  const current = Math.round(a.quota_bytes / 1024 / 1024);
  if (!mb || mb === current) return;
  router.patch(`/admin/accounts/${a.id}/quota`, { quota_mb: mb }, { preserveScroll: true });
}

// ─── Édition complète ───
const editing = ref(null);
const edit = reactive({
  local: '', domain_id: null, display_name: '', first_name: '', last_name: '',
  job_title: '', company: '', phone: '', mobile: '', quota_mb: 5120,
});
function openEdit(a) {
  editing.value = a.id;
  Object.assign(edit, {
    local: a.local || (a.email || '').split('@')[0],
    domain_id: a.domain_id,
    display_name: a.display_name || '',
    first_name: a.first_name || '',
    last_name: a.last_name || '',
    job_title: a.job_title || '',
    company: a.company || '',
    phone: a.phone || '',
    mobile: a.mobile || '',
    quota_mb: a.quota_mb || Math.round((a.quota_bytes || 0) / 1024 / 1024),
  });
}
function closeEdit() { editing.value = null; }
function saveEdit() {
  router.patch(`/admin/accounts/${editing.value}`, edit, {
    preserveScroll: true,
    onSuccess: () => closeEdit(),
  });
}
</script>
