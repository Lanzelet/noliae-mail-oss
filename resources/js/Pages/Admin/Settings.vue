<template>
  <AdminLayout title="Paramètres">
    <form @submit.prevent="save" class="space-y-6 max-w-2xl">

      <!-- Inscriptions -->
      <div class="bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 p-5">
        <h2 class="font-bold mb-1">Inscriptions publiques</h2>
        <p class="text-xs text-gray-500 mb-4">Si désactivé, seul l'admin peut créer des comptes via cet espace.</p>
        <label class="flex items-center gap-3 cursor-pointer">
          <input v-model="form.allow_registration" type="checkbox" class="w-5 h-5 accent-[#FF4D2E]" />
          <span class="text-sm">Autoriser les visiteurs à créer un compte sur @{{ app_domain }}</span>
        </label>
      </div>

      <!-- Quotas -->
      <div class="bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 p-5">
        <h2 class="font-bold mb-1">Quotas mail</h2>
        <p class="text-xs text-gray-500 mb-4">Espace alloué par défaut à chaque nouvelle boîte, et plafond max imposable.</p>
        <div class="grid grid-cols-2 gap-4">
          <label class="block">
            <span class="block text-[11px] uppercase tracking-wider text-gray-500 mb-1">Quota par défaut (Mo)</span>
            <input v-model.number="form.default_quota_mb" type="number" min="10" :max="form.max_quota_mb"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 rounded-lg text-sm focus:outline-none focus:border-[#FF4D2E]" />
          </label>
          <label class="block">
            <span class="block text-[11px] uppercase tracking-wider text-gray-500 mb-1">Quota maximum autorisé (Mo)</span>
            <input v-model.number="form.max_quota_mb" type="number" min="100"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 rounded-lg text-sm focus:outline-none focus:border-[#FF4D2E]" />
          </label>
        </div>
      </div>

      <!-- Backup -->
      <div class="bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 p-5">
        <h2 class="font-bold mb-1">Sauvegardes automatiques</h2>
        <p class="text-xs text-gray-500 mb-4">Génère un tar.gz quotidien dans <code>volumes/backups/</code> (postgres dump + maildirs).</p>
        <label class="flex items-center gap-3 cursor-pointer mb-3">
          <input v-model="form.backup_enabled" type="checkbox" class="w-5 h-5 accent-[#FF4D2E]" />
          <span class="text-sm">Activer la sauvegarde quotidienne</span>
        </label>
        <label class="block max-w-xs" :class="!form.backup_enabled ? 'opacity-40 pointer-events-none' : ''">
          <span class="block text-[11px] uppercase tracking-wider text-gray-500 mb-1">Heure UTC (0-23)</span>
          <input v-model.number="form.backup_hour_utc" type="number" min="0" max="23"
                 class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 rounded-lg text-sm focus:outline-none focus:border-[#FF4D2E]" />
        </label>
      </div>

      <!-- IA Noliae -->
      <div class="bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 p-5">
        <h2 class="font-bold mb-1">Intégration IA Noliae (optionnelle)</h2>
        <p class="text-xs text-gray-500 mb-4">
          Branche le webmail sur l'IA hébergée par Noliae (smart-reply, traduction, résumé, anti-phishing).
          Désactivé par défaut : ton serveur reste 100% souverain. Activer envoie le contenu des mails à Noliae IA.
        </p>
        <label class="flex items-center gap-3 cursor-pointer">
          <input v-model="form.enable_noliae_ai" type="checkbox" class="w-5 h-5 accent-[#FF4D2E]" />
          <span class="text-sm">Activer l'intégration IA Noliae</span>
        </label>
      </div>

      <!-- Compte admin -->
      <div class="bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 p-5">
        <h2 class="font-bold mb-1">Compte administrateur</h2>
        <p class="text-xs text-gray-500 mb-4">L'utilisateur dont l'email correspond a accès au panneau /admin.</p>
        <label class="block">
          <span class="block text-[11px] uppercase tracking-wider text-gray-500 mb-1">Email de l'admin</span>
          <input v-model="form.admin_email" type="email" required
                 class="w-full px-3 py-2 border border-gray-300 dark:border-zinc-600 dark:bg-zinc-900 rounded-lg text-sm focus:outline-none focus:border-[#FF4D2E]" />
        </label>
        <p class="text-[11px] text-amber-600 mt-2">⚠ Si tu changes l'email, le compte mail correspondant doit déjà exister (sinon plus personne n'aura accès à /admin).</p>
      </div>

      <div class="flex justify-end">
        <button type="submit" :disabled="processing"
                class="px-6 py-2.5 bg-[#FF4D2E] text-white rounded-lg text-sm font-semibold hover:bg-[#df3c1f] disabled:opacity-50">
          {{ processing ? 'Enregistrement…' : 'Enregistrer' }}
        </button>
      </div>
    </form>
  </AdminLayout>
</template>
<script setup>
import AdminLayout from './Layout.vue';
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
const props = defineProps({ settings: Object, app_domain: String, admin_email: String });
const form = reactive({
  allow_registration: props.settings.allow_registration === '1',
  default_quota_mb:   parseInt(props.settings.default_quota_mb || 5120),
  max_quota_mb:       parseInt(props.settings.max_quota_mb || 51200),
  backup_enabled:     props.settings.backup_enabled === '1',
  backup_hour_utc:    parseInt(props.settings.backup_hour_utc || 0),
  enable_noliae_ai:   props.settings.enable_noliae_ai === '1',
  admin_email:        props.admin_email,
});
const processing = ref(false);
function save() {
  processing.value = true;
  router.post('/admin/settings', form, {
    preserveScroll: true,
    onFinish: () => processing.value = false,
  });
}
</script>
