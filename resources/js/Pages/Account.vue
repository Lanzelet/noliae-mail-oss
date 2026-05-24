<template>
  <div class="min-h-screen bg-gray-50 dark:bg-zinc-900 text-gray-900 dark:text-gray-100 flex">
    <!-- ── Sidebar OWA-style "Options" ── -->
    <aside :class="['fixed lg:sticky top-0 inset-y-0 z-30 bg-white dark:bg-zinc-950 border-r border-gray-200 dark:border-zinc-800 transition-transform duration-200 ease-in-out',
                    'w-64 flex flex-col h-screen overflow-y-auto', sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0']">
      <div class="px-5 py-4 border-b border-gray-100 dark:border-zinc-800">
        <Link href="/webmail" class="flex items-center gap-2 text-[#FF4D2E] text-sm font-semibold hover:text-[#df3c1f]">
          <span>←</span> Webmail
        </Link>
        <h2 class="font-black text-lg mt-2">Options</h2>
      </div>
      <nav class="flex-1 py-3 px-2 space-y-4 text-sm">
        <div v-for="section in sections" :key="section.label">
          <div class="px-3 mb-1 text-[10px] uppercase tracking-wider text-gray-400 font-bold">{{ section.label }}</div>
          <button v-for="t in section.items" :key="t.id" @click="active = t.id; sidebarOpen = false"
            :class="['w-full text-left flex items-center gap-2 px-3 py-2 rounded-lg transition mb-0.5',
                     active === t.id
                       ? 'bg-[#FF4D2E]/10 text-[#FF4D2E] font-semibold'
                       : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-zinc-900']">
            <span class="w-4 text-center">{{ t.emoji }}</span>
            <span class="flex-1 truncate">{{ t.label }}</span>
          </button>
        </div>
      </nav>
    </aside>

    <div v-if="sidebarOpen" @click="sidebarOpen=false" class="lg:hidden fixed inset-0 bg-black/40 z-20"/>

    <!-- ── Main ── -->
    <div class="flex-1 min-w-0 flex flex-col">
      <header class="bg-white dark:bg-zinc-800 border-b border-gray-200 dark:border-zinc-700 sticky top-0 z-10">
        <div class="px-5 py-3 flex items-center justify-between gap-3">
          <div class="flex items-center gap-3 min-w-0">
            <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden text-gray-500">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
            </button>
            <h1 class="text-lg font-bold truncate">{{ activeLabel }}</h1>
          </div>
          <div class="flex items-center gap-3 text-xs text-gray-500">
            <code class="hidden sm:inline font-mono">{{ email }}</code>
          </div>
        </div>
      </header>

      <main class="flex-1 px-5 py-6 max-w-3xl w-full mx-auto space-y-5">
        <div v-if="flash?.success" class="px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ flash.success }}</div>
        <div v-if="force_2fa && !totp_enabled" class="px-4 py-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm">
          ⚠ <strong>2FA obligatoire :</strong> ton rôle admin/support exige TOTP pour accéder à l'admin panel.
        </div>

        <!-- ═════ MON COMPTE (profil étendu) ═════ -->
        <section v-if="active === 'profile'" class="space-y-5">
          <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 p-5">
            <div class="flex items-start gap-5">
              <div class="flex flex-col items-center gap-2">
                <img :src="avatarSrc" class="w-24 h-24 rounded-full object-cover bg-gray-200 dark:bg-zinc-700 border border-gray-200 dark:border-zinc-700"/>
                <div class="flex items-center gap-1">
                  <label class="px-2 py-1 text-[11px] font-semibold bg-[#FF4D2E]/10 text-[#FF4D2E] rounded-lg cursor-pointer hover:bg-[#FF4D2E]/20">
                    Changer
                    <input ref="fileInput" type="file" accept="image/png,image/jpeg,image/webp,image/gif" class="hidden" @change="uploadAvatar"/>
                  </label>
                  <button v-if="avatar_url" @click="deleteAvatar" type="button" class="px-2 py-1 text-[11px] text-gray-500 hover:text-rose-600">Retirer</button>
                </div>
                <p v-if="errors.avatar" class="text-rose-600 text-[10px] text-center max-w-[120px]">{{ errors.avatar }}</p>
              </div>
              <div class="flex-1 min-w-0">
                <h2 class="font-bold">Mon compte</h2>
                <p class="text-[11px] text-gray-500 mb-3">Tes infos personnelles + signature des mails sortants.</p>
                <div class="text-sm">
                  <div class="text-[10px] uppercase tracking-wider text-gray-500">Adresse mail</div>
                  <code class="font-mono">{{ email }}</code>
                </div>
              </div>
            </div>
          </div>

          <form @submit.prevent="saveProfile" class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 p-5 space-y-4">
            <h3 class="font-bold text-sm">État civil</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <Field label="Prénom"        v-model="form.first_name"   placeholder="Jean"/>
              <Field label="Nom"           v-model="form.last_name"    placeholder="Dupont"/>
              <Field label="Initiales"     v-model="form.initials"     placeholder="JD" maxlength="8"/>
              <Field label="Nom affiché*"  v-model="form.display_name" placeholder="Jean Dupont"/>
            </div>

            <h3 class="font-bold text-sm pt-3">Coordonnées</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <Field label="Téléphone fixe" v-model="form.phone"      type="tel" placeholder="+33 1 23 45 67 89"/>
              <Field label="Mobile"         v-model="form.mobile"     type="tel" placeholder="+33 6 12 34 56 78"/>
              <Field label="Fonction"       v-model="form.job_title"  placeholder="Responsable IT"/>
              <Field label="Société"        v-model="form.company"    placeholder="Acme SAS"/>
            </div>

            <h3 class="font-bold text-sm pt-3">Adresse postale</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <Field label="Rue"             v-model="form.street"      class="md:col-span-2"/>
              <Field label="Ville"           v-model="form.city"/>
              <Field label="Région / État"   v-model="form.state"/>
              <Field label="Code postal"     v-model="form.postal_code"/>
              <Field label="Pays"            v-model="form.country"/>
            </div>

            <h3 class="font-bold text-sm pt-3">Préférences</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <label class="block">
                <span class="block text-[10px] uppercase tracking-wider text-gray-500">Fuseau horaire</span>
                <select v-model="form.timezone" class="w-full mt-1 px-3 py-2 text-sm bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-lg">
                  <option v-for="tz in TIMEZONES" :key="tz" :value="tz">{{ tz }}</option>
                </select>
              </label>
              <label class="block">
                <span class="block text-[10px] uppercase tracking-wider text-gray-500">Langue</span>
                <select v-model="form.language" class="w-full mt-1 px-3 py-2 text-sm bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-lg">
                  <option value="fr">Français</option>
                  <option value="en">English</option>
                </select>
              </label>
            </div>

            <button type="submit" class="px-5 py-2 bg-[#FF4D2E] text-white rounded-lg text-sm font-bold hover:bg-[#df3c1f]">
              💾 Sauvegarder
            </button>
          </form>
        </section>

        <!-- ═════ SÉCURITÉ (password + 2FA) ═════ -->
        <section v-if="active === 'security'" class="space-y-5">
          <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 p-5">
            <h2 class="font-bold mb-1 flex items-center gap-2"><span>🔑</span>Mot de passe</h2>
            <p class="text-[11px] text-gray-500 mb-3">Login web + IMAP + SMTP.</p>
            <form @submit.prevent="changePwd" class="grid grid-cols-1 md:grid-cols-2 gap-2">
              <input v-model="pwd.current_password" type="password" required placeholder="Mot de passe actuel" class="px-3 py-2 text-sm bg-gray-50 dark:bg-zinc-900 border rounded-lg"/>
              <input v-model="pwd.new_password" type="password" required minlength="10" placeholder="Nouveau (10+ car.)" class="px-3 py-2 text-sm bg-gray-50 dark:bg-zinc-900 border rounded-lg"/>
              <p v-if="errors.current_password" class="text-rose-600 text-xs md:col-span-2">{{ errors.current_password }}</p>
              <button type="submit" class="md:col-span-2 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-bold">Changer le mot de passe</button>
            </form>
          </div>

          <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 p-5">
            <h2 class="font-bold mb-1 flex items-center gap-2"><span>🔐</span>Double authentification (TOTP)
              <span v-if="totp_enabled" class="text-[10px] text-emerald-600 font-bold uppercase">activée</span>
              <span v-else class="text-[10px] text-rose-600 font-bold uppercase">désactivée</span>
            </h2>
            <p class="text-[11px] text-gray-500 mb-3">Google Authenticator, 1Password, Authy, Bitwarden, etc.</p>

            <div v-if="!totp_enabled && !totp_setup">
              <button @click="start2fa" class="px-4 py-2 bg-[#FF4D2E] text-white rounded-lg text-sm font-bold">Activer la 2FA</button>
            </div>
            <div v-if="totp_setup && !totp_activated" class="space-y-3">
              <p class="text-sm text-gray-700 dark:text-gray-300">Ajoute ce secret dans ton authenticator, puis confirme avec un code à 6 chiffres :</p>
              <div class="flex items-center gap-2">
                <code class="px-3 py-2 bg-gray-100 dark:bg-zinc-900 rounded-lg font-mono text-xs flex-1">{{ totp_setup.secret }}</code>
                <button @click="copy(totp_setup.secret)" class="text-xs px-2 py-1 bg-gray-200 rounded hover:bg-gray-300">📋</button>
              </div>
              <p class="text-[11px] text-gray-500 font-mono break-all">{{ totp_setup.uri }}</p>
              <form @submit.prevent="confirm2fa" class="flex gap-2">
                <input v-model="confirmCode" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required placeholder="123456" class="flex-1 px-3 py-2 text-center text-xl font-mono bg-gray-50 dark:bg-zinc-900 border rounded-lg"/>
                <button type="submit" class="px-4 py-2 bg-[#FF4D2E] text-white rounded-lg text-sm font-bold">Confirmer</button>
              </form>
              <p v-if="errors.code" class="text-rose-600 text-xs">{{ errors.code }}</p>
            </div>
            <div v-if="totp_activated" class="space-y-2 mt-3">
              <p class="text-sm text-emerald-700 font-semibold">{{ totp_activated.message }}</p>
              <pre class="px-3 py-2 bg-amber-50 border border-amber-200 rounded-lg text-xs font-mono whitespace-pre-wrap">{{ totp_activated.recovery_codes.join('\n') }}</pre>
              <button @click="copyRecovery" class="text-xs px-2 py-1 bg-gray-200 rounded">Copier les codes</button>
            </div>
            <form v-if="totp_enabled && !totp_setup" @submit.prevent="disable2fa" class="flex gap-2 items-end mt-3">
              <label class="block flex-1">
                <span class="block text-[10px] uppercase tracking-wider text-gray-500">Mot de passe pour confirmer</span>
                <input v-model="disablePwd" type="password" required class="w-full mt-1 px-3 py-2 text-sm bg-gray-50 dark:bg-zinc-900 border rounded-lg"/>
              </label>
              <button type="submit" class="px-4 py-2 bg-rose-600 text-white rounded-lg text-sm font-bold">Désactiver</button>
            </form>
            <p v-if="errors.password" class="text-rose-600 text-xs mt-2">{{ errors.password }}</p>
          </div>
        </section>

        <!-- ═════ TOKENS SMTP ═════ -->
        <section v-if="active === 'tokens'" class="space-y-5">
          <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 p-5">
            <h2 class="font-bold mb-1 flex items-center gap-2"><span>🔌</span>Tokens SMTP (apps externes)</h2>
            <p class="text-[11px] text-gray-500 mb-3">Mots de passe applicatifs pour Thunderbird, Apple Mail, scripts… Révocables individuellement.</p>
            <form @submit.prevent="createToken" class="flex gap-2 mb-4">
              <input v-model="newTokenLabel" required maxlength="64" placeholder="Étiquette : iPhone, Thunderbird…" class="flex-1 px-3 py-2 text-sm bg-gray-50 dark:bg-zinc-900 border rounded-lg"/>
              <button type="submit" class="px-4 py-2 bg-[#FF4D2E] text-white rounded-lg text-sm font-bold">+ Créer</button>
            </form>
            <div v-if="new_token" class="px-3 py-2 mb-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs">
              <p class="font-bold">Token créé — copie-le maintenant, il ne sera plus jamais affiché :</p>
              <code class="block font-mono break-all mt-1 select-all">{{ new_token }}</code>
            </div>
            <ul v-if="tokens.length" class="divide-y divide-gray-100 dark:divide-zinc-700 text-sm">
              <li v-for="t in tokens" :key="t.id" class="py-2 flex items-center justify-between">
                <div>
                  <div class="font-semibold">{{ t.label }}</div>
                  <div class="text-[11px] text-gray-500">Créé {{ rel(t.created_at) }} · dernière utilisation : {{ rel(t.last_used_at) }}</div>
                </div>
                <button @click="revoke(t)" class="text-xs text-gray-400 hover:text-rose-600">Révoquer</button>
              </li>
            </ul>
            <p v-else class="text-xs text-gray-400 text-center py-4">Aucun token.</p>
          </div>
        </section>

      </main>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Field from '../Components/Field.vue';

const props = defineProps({
  email: String, display_name: String, totp_enabled: Boolean,
  tokens: Array, mail_domain: String,
  avatar_hash: String, avatar_url: String,
  profile: Object, force_2fa: Boolean,
});

const sidebarOpen = ref(false);
const active = ref('profile');
const sections = [
  { label: 'Général', items: [
    { id: 'profile',  label: 'Mon compte',   emoji: '👤' },
    { id: 'security', label: 'Sécurité & 2FA', emoji: '🔐' },
    { id: 'tokens',   label: 'Tokens SMTP',  emoji: '🔌' },
  ]},
];
const activeLabel = computed(() =>
  sections.flatMap(s => s.items).find(t => t.id === active.value)?.label || 'Options'
);

const flash = computed(() => usePage().props.flash);
const errors = computed(() => usePage().props.errors || {});
const totp_setup = computed(() => usePage().props.flash?.totp_setup);
const totp_activated = computed(() => usePage().props.flash?.totp_activated);
const new_token = computed(() => usePage().props.flash?.new_token);

// Profil
const form = reactive({
  display_name: props.display_name || '',
  first_name: props.profile?.first_name || '',
  last_name: props.profile?.last_name || '',
  initials: props.profile?.initials || '',
  phone: props.profile?.phone || '',
  mobile: props.profile?.mobile || '',
  job_title: props.profile?.job_title || '',
  company: props.profile?.company || '',
  street: props.profile?.street || '',
  city: props.profile?.city || '',
  state: props.profile?.state || '',
  postal_code: props.profile?.postal_code || '',
  country: props.profile?.country || '',
  timezone: props.profile?.timezone || 'UTC',
  language: props.profile?.language || 'fr',
});
function saveProfile() { router.post('/account/profile', form, { preserveScroll: true }); }

// Avatar
const fileInput = ref(null);
const avatarSrc = computed(() => {
  if (props.avatar_url) return props.avatar_url;
  const n = encodeURIComponent(form.display_name || props.email || '');
  return `/webmail/avatar/${props.avatar_hash}?n=${n}`;
});
function uploadAvatar(e) {
  const f = e.target.files?.[0]; if (!f) return;
  const fd = new FormData(); fd.append('avatar', f);
  router.post('/account/avatar', fd, { preserveScroll: true, forceFormData: true,
    onFinish: () => { if (fileInput.value) fileInput.value.value = ''; }});
}
function deleteAvatar() {
  if (confirm('Supprimer l\'avatar ?')) router.delete('/account/avatar', { preserveScroll: true });
}

// Password
const pwd = reactive({ current_password: '', new_password: '' });
function changePwd() { router.post('/account/password', pwd, { preserveScroll: true, onSuccess: () => { pwd.current_password=''; pwd.new_password=''; }}); }

// 2FA
const confirmCode = ref('');
const disablePwd = ref('');
function start2fa()  { router.post('/account/2fa/start', {}, { preserveScroll: true }); }
function confirm2fa(){ router.post('/account/2fa/confirm', { code: confirmCode.value }, { preserveScroll: true, onSuccess: () => confirmCode.value = '' }); }
function disable2fa(){ router.post('/account/2fa/disable', { password: disablePwd.value }, { preserveScroll: true, onSuccess: () => disablePwd.value = '' }); }
function copy(s)     { navigator.clipboard?.writeText(s); }
function copyRecovery(){ copy(totp_activated.value?.recovery_codes.join('\n')); }

// Tokens
const newTokenLabel = ref('');
function createToken(){ router.post('/account/tokens', { label: newTokenLabel.value }, { preserveScroll: true, onSuccess: () => newTokenLabel.value = '' }); }
function revoke(t){ if (confirm(`Révoquer le token « ${t.label} » ?`)) router.delete(`/account/tokens/${t.id}`, { preserveScroll: true }); }

function rel(iso) {
  if (!iso) return 'jamais';
  const d = (Date.now() - new Date(iso)) / 1000;
  if (d < 60) return `il y a ${Math.round(d)}s`;
  if (d < 3600) return `il y a ${Math.round(d/60)} min`;
  if (d < 86400) return `il y a ${Math.round(d/3600)} h`;
  return `il y a ${Math.round(d/86400)} j`;
}

// Hard-coded liste TZ — courte, courantes
const TIMEZONES = [
  'UTC', 'Europe/Paris', 'Europe/London', 'Europe/Berlin', 'Europe/Madrid', 'Europe/Rome',
  'Africa/Casablanca', 'America/New_York', 'America/Los_Angeles', 'America/Sao_Paulo',
  'Asia/Tokyo', 'Asia/Shanghai', 'Asia/Singapore', 'Australia/Sydney',
];
</script>
