<template>
  <div class="min-h-screen bg-gray-50 dark:bg-zinc-900 text-gray-900 dark:text-gray-100">
    <!-- Header -->
    <header class="bg-white dark:bg-zinc-800 border-b border-gray-200 dark:border-zinc-700 sticky top-0 z-10">
      <div class="max-w-3xl mx-auto px-5 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <svg viewBox="0 0 140 100" class="h-6"><rect x="12" y="35" width="10" height="30" rx="5" fill="currentColor"/><rect x="30" y="25" width="10" height="50" rx="5" fill="currentColor"/><rect x="48" y="15" width="10" height="70" rx="5" fill="currentColor"/><rect x="66" y="22" width="10" height="56" rx="5" fill="#FF4D2E"/><rect x="84" y="20" width="10" height="60" rx="5" fill="currentColor"/><rect x="102" y="30" width="10" height="40" rx="5" fill="currentColor"/><rect x="120" y="35" width="10" height="30" rx="5" fill="currentColor"/></svg>
          <span class="font-bold">Mon compte</span>
        </div>
        <div class="text-xs text-gray-500 flex items-center gap-4">
          <span class="font-mono">{{ email }}</span>
          <a href="/webmail" class="text-gray-600 hover:text-[#FF4D2E]">Webmail →</a>
        </div>
      </div>
    </header>

    <main class="max-w-3xl mx-auto px-5 py-8 space-y-5">
      <!-- Flash -->
      <div v-if="flash?.success" class="px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ flash.success }}</div>

      <!-- Profil -->
      <section class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 p-5 shadow-sm">
        <h2 class="font-bold mb-1 flex items-center gap-2">
          <span class="w-7 h-7 rounded-lg bg-[#FF4D2E]/15 text-[#FF4D2E] flex items-center justify-center text-sm">👤</span>
          Profil
        </h2>
        <p class="text-[11px] text-gray-500 mb-3">Tes infos visibles dans les mails envoyés.</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
          <div>
            <div class="text-[10px] uppercase tracking-wider text-gray-500">Adresse</div>
            <code class="font-mono">{{ email }}</code>
          </div>
          <div>
            <div class="text-[10px] uppercase tracking-wider text-gray-500">Nom affiché</div>
            <span>{{ display_name || '—' }}</span>
          </div>
        </div>
      </section>

      <!-- Password -->
      <section class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 p-5 shadow-sm">
        <h2 class="font-bold mb-1 flex items-center gap-2">
          <span class="w-7 h-7 rounded-lg bg-blue-500/15 text-blue-600 flex items-center justify-center text-sm">🔑</span>
          Mot de passe
        </h2>
        <p class="text-[11px] text-gray-500 mb-3">Change ton mot de passe principal (login web + IMAP).</p>
        <form @submit.prevent="changePwd" class="grid grid-cols-1 md:grid-cols-2 gap-2">
          <input v-model="pwd.current_password" type="password" required placeholder="Mot de passe actuel"
                 class="px-3 py-2 text-sm bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl focus:outline-none focus:border-blue-500"/>
          <input v-model="pwd.new_password" type="password" required minlength="10" placeholder="Nouveau (10 car. min)"
                 class="px-3 py-2 text-sm bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl focus:outline-none focus:border-blue-500"/>
          <p v-if="errors.current_password" class="text-rose-600 text-xs md:col-span-2">{{ errors.current_password }}</p>
          <button type="submit" class="md:col-span-2 px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-bold hover:bg-blue-700">
            Changer le mot de passe
          </button>
        </form>
      </section>

      <!-- 2FA -->
      <section class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 p-5 shadow-sm">
        <h2 class="font-bold mb-1 flex items-center gap-2">
          <span class="w-7 h-7 rounded-lg bg-emerald-500/15 text-emerald-600 flex items-center justify-center text-sm">🔒</span>
          Double authentification (2FA)
          <span v-if="totp_enabled" class="ml-1 inline-block px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[10px] uppercase tracking-wider font-bold">activée</span>
        </h2>
        <p class="text-[11px] text-gray-500 mb-3">Ajoute une couche de sécurité avec une app comme Authy, Google Authenticator, 1Password ou Bitwarden.</p>

        <!-- Pas activé : bouton démarrer -->
        <button v-if="!totp_enabled && !totp_setup" @click="start2fa"
                class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-sm font-bold hover:bg-emerald-700">
          🚀 Activer le 2FA
        </button>

        <!-- En cours d'activation : QR + code -->
        <div v-if="totp_setup" class="border border-emerald-200 dark:border-emerald-800 rounded-xl p-4 bg-emerald-50/30 dark:bg-emerald-950/30">
          <p class="text-sm mb-3">📷 Scanne ce QR code dans ton app d'authentification :</p>
          <div class="flex flex-col md:flex-row gap-4 items-center">
            <img :src="`https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(totp_setup.uri)}`"
                 alt="QR code 2FA" class="w-48 h-48 rounded-lg border border-gray-200"/>
            <div class="flex-1 text-xs">
              <p class="mb-1 text-gray-500">Ou entre manuellement ce secret :</p>
              <code class="block px-3 py-2 bg-white dark:bg-zinc-900 rounded font-mono break-all text-[11px]">{{ totp_setup.secret }}</code>
              <form @submit.prevent="confirm2fa" class="mt-4">
                <p class="mb-1 text-gray-500">Puis entre le code à 6 chiffres :</p>
                <div class="flex gap-2">
                  <input v-model="confirmCode" type="text" inputmode="numeric" required maxlength="6"
                         class="flex-1 px-3 py-2 text-center font-mono tracking-widest bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl focus:outline-none focus:border-emerald-500"
                         placeholder="000000" />
                  <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-sm font-bold hover:bg-emerald-700">Confirmer</button>
                </div>
                <p v-if="errors.code" class="text-rose-600 text-xs mt-1">{{ errors.code }}</p>
              </form>
            </div>
          </div>
        </div>

        <!-- Codes de récupération (one-shot après activation) -->
        <div v-if="totp_activated" class="border border-amber-200 dark:border-amber-800 rounded-xl p-4 bg-amber-50/40 dark:bg-amber-950/40 mt-3">
          <p class="font-bold text-amber-700 dark:text-amber-300 mb-2">⚠ {{ totp_activated.message }}</p>
          <ul class="grid grid-cols-2 gap-1 font-mono text-xs">
            <li v-for="c in totp_activated.recovery_codes" :key="c" class="px-2 py-1 bg-white dark:bg-zinc-900 rounded">{{ c }}</li>
          </ul>
          <button @click="copyRecovery" class="mt-2 text-xs text-amber-700 hover:underline">📋 Copier les 8 codes</button>
        </div>

        <!-- Déjà activé : formulaire de désactivation -->
        <form v-if="totp_enabled && !totp_setup && !totp_activated" @submit.prevent="disable2fa" class="flex gap-2">
          <input v-model="disablePwd" type="password" required placeholder="Mot de passe pour confirmer désactivation"
                 class="flex-1 px-3 py-2 text-sm bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl"/>
          <button type="submit" class="px-4 py-2 bg-rose-600 text-white rounded-xl text-sm font-bold hover:bg-rose-700">Désactiver 2FA</button>
        </form>
        <p v-if="errors.password" class="text-rose-600 text-xs mt-1">{{ errors.password }}</p>
      </section>

      <!-- SMTP Tokens -->
      <section class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 p-5 shadow-sm">
        <h2 class="font-bold mb-1 flex items-center gap-2">
          <span class="w-7 h-7 rounded-lg bg-violet-500/15 text-violet-600 flex items-center justify-center text-sm">📨</span>
          Tokens SMTP (apps)
        </h2>
        <p class="text-[11px] text-gray-500 mb-3">Mots de passe dédiés pour Thunderbird, iPhone Mail, scripts… Révocables à tout moment.</p>

        <form @submit.prevent="createToken" class="flex gap-2 mb-3">
          <input v-model="newTokenLabel" required placeholder="Ex: iPhone Mail, Thunderbird laptop…"
                 class="flex-1 px-3 py-2 text-sm bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl focus:outline-none focus:border-violet-500"/>
          <button type="submit" class="px-4 py-2 bg-violet-600 text-white rounded-xl text-sm font-bold hover:bg-violet-700">+ Générer</button>
        </form>

        <!-- Nouveau token affiché (one-shot) -->
        <div v-if="new_token" class="border border-violet-200 dark:border-violet-800 rounded-xl p-4 bg-violet-50/40 dark:bg-violet-950/40 mb-3">
          <p class="font-bold text-violet-700 dark:text-violet-300 mb-2">⚠ {{ new_token.warning }}</p>
          <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1.5 text-xs font-mono">
            <dt class="text-gray-500">Server :</dt><dd>{{ new_token.server }}</dd>
            <dt class="text-gray-500">Port :</dt><dd>{{ new_token.port }} (STARTTLS)</dd>
            <dt class="text-gray-500">Username :</dt><dd class="break-all">{{ new_token.username }}</dd>
            <dt class="text-gray-500">Password :</dt>
            <dd class="break-all">
              <code class="px-2 py-1 bg-white dark:bg-zinc-900 rounded">{{ new_token.password }}</code>
              <button @click="copy(new_token.password)" class="ml-2 text-violet-600 hover:underline">📋</button>
            </dd>
          </dl>
        </div>

        <!-- Liste -->
        <ul v-if="tokens.length" class="divide-y divide-gray-100 dark:divide-zinc-700 -mx-5">
          <li v-for="t in tokens" :key="t.id" class="px-5 py-2.5 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-violet-400 to-fuchsia-500 text-white flex items-center justify-center text-xs font-bold">
              {{ t.label.charAt(0).toUpperCase() }}
            </div>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-semibold truncate">{{ t.label }}</div>
              <p class="text-[10px] text-gray-500">
                Créé {{ rel(t.created_at) }}
                <span v-if="t.last_used_at"> · Utilisé {{ rel(t.last_used_at) }}</span>
                <span v-else> · Jamais utilisé</span>
              </p>
            </div>
            <button @click="revoke(t)" class="text-xs text-rose-600 hover:underline">Révoquer</button>
          </li>
        </ul>
        <p v-else class="text-xs text-gray-400 text-center py-4">Aucun token. Crée le premier pour configurer un client mail externe.</p>
      </section>
    </main>
  </div>
</template>
<script setup>
import { reactive, ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
const props = defineProps({
  email: String, display_name: String, totp_enabled: Boolean,
  tokens: Array, mail_domain: String,
});
const flash = computed(() => usePage().props.flash);
const errors = computed(() => usePage().props.errors || {});
const totp_setup = computed(() => usePage().props.flash?.totp_setup);
const totp_activated = computed(() => usePage().props.flash?.totp_activated);
const new_token = computed(() => usePage().props.flash?.new_token);

const pwd = reactive({ current_password: '', new_password: '' });
const confirmCode = ref('');
const disablePwd = ref('');
const newTokenLabel = ref('');

function changePwd() { router.post('/account/password', pwd, { preserveScroll: true, onSuccess: () => { pwd.current_password=''; pwd.new_password=''; } }); }
function start2fa() { router.post('/account/2fa/start', {}, { preserveScroll: true }); }
function confirm2fa() { router.post('/account/2fa/confirm', { code: confirmCode.value }, { preserveScroll: true, onSuccess: () => confirmCode.value = '' }); }
function disable2fa() { router.post('/account/2fa/disable', { password: disablePwd.value }, { preserveScroll: true, onSuccess: () => disablePwd.value = '' }); }
function createToken() { router.post('/account/tokens', { label: newTokenLabel.value }, { preserveScroll: true, onSuccess: () => newTokenLabel.value = '' }); }
function revoke(t) { if (confirm(`Révoquer le token « ${t.label} » ?`)) router.delete(`/account/tokens/${t.id}`, { preserveScroll: true }); }
function copy(text) { navigator.clipboard?.writeText(text); }
function copyRecovery() { copy(totp_activated.value?.recovery_codes.join('\n')); }
function rel(iso) {
  if (!iso) return '?';
  const d = (Date.now() - new Date(iso)) / 1000;
  if (d < 60) return `il y a ${Math.round(d)}s`;
  if (d < 3600) return `il y a ${Math.round(d/60)} min`;
  if (d < 86400) return `il y a ${Math.round(d/3600)} h`;
  return `il y a ${Math.round(d/86400)} j`;
}
</script>
