<template>
  <div class="min-h-screen flex flex-col lg:flex-row bg-white dark:bg-zinc-900">
    <!-- ── Panneau de gauche : logo Noliae animé sur gradient orange ── -->
    <aside class="lg:w-1/3 xl:w-2/5 bg-gradient-to-br from-[#FF4D2E] to-[#cc3a1f] flex items-center justify-center relative overflow-hidden min-h-[160px] lg:min-h-screen">
      <AnimatedLogo color="white" accent="white" class="w-1/3 max-w-[260px] opacity-60"/>
    </aside>

    <!-- ── Panneau de droite : formulaire ── -->
    <main class="flex-1 flex items-center justify-center px-6 py-12 lg:py-0">
      <div class="w-full max-w-md">
        <div class="lg:hidden flex items-center gap-2 mb-8">
          <svg viewBox="0 0 140 100" class="h-8 text-[#FF4D2E]">
            <rect x="12" y="35" width="10" height="30" rx="5" fill="currentColor"/>
            <rect x="30" y="25" width="10" height="50" rx="5" fill="currentColor"/>
            <rect x="48" y="15" width="10" height="70" rx="5" fill="currentColor"/>
            <rect x="66" y="22" width="10" height="56" rx="5" fill="#FF4D2E"/>
            <rect x="84" y="20" width="10" height="60" rx="5" fill="currentColor"/>
            <rect x="102" y="30" width="10" height="40" rx="5" fill="currentColor"/>
            <rect x="120" y="35" width="10" height="30" rx="5" fill="currentColor"/>
          </svg>
          <span class="font-black text-xl">Noliae Mail</span>
        </div>

        <!-- Logo de l'organisation (uploadé par l'owner) — speak for itself, on
             ne ré-affiche pas le nom org à côté pour éviter la redondance. -->
        <div v-if="org_logo_url" class="mb-6">
          <img :src="org_logo_url" :alt="org_name || 'Organisation'"
               class="max-h-16 max-w-[240px] object-contain"/>
        </div>

        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
          {{ mode === 'register' ? 'Créer un compte' : 'Se connecter' }}
        </h2>
        <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">
          {{ mode === 'register' ? `Nouveau compte sur @${displayDomain}` : `Boîte mail @${displayDomain}` }}
        </p>

        <!-- LOGIN -->
        <form v-if="mode === 'login'" @submit.prevent="submitLogin" class="mt-8 space-y-4">
          <label class="block">
            <span class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Adresse mail</span>
            <input v-model="loginForm.email" type="email" required autocomplete="username"
                   class="w-full bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-lg px-3 py-2.5 text-gray-900 dark:text-gray-100 text-sm focus:border-[#FF4D2E] focus:ring-2 focus:ring-[#FF4D2E]/20 focus:outline-none"
                   :placeholder="`prenom.nom@${displayDomain}`" />
          </label>
          <label class="block">
            <span class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Mot de passe</span>
            <input v-model="loginForm.password" type="password" required autocomplete="current-password"
                   class="w-full bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-lg px-3 py-2.5 text-gray-900 dark:text-gray-100 text-sm focus:border-[#FF4D2E] focus:ring-2 focus:ring-[#FF4D2E]/20 focus:outline-none" />
          </label>
          <p v-if="errors.email" class="text-rose-500 text-xs">{{ errors.email }}</p>
          <p v-if="errors.password" class="text-rose-500 text-xs">{{ errors.password }}</p>
          <button type="submit" :disabled="processing"
                  class="w-full mt-2 px-6 py-2.5 rounded-lg bg-[#FF4D2E] text-white font-bold text-sm hover:bg-[#df3c1f] disabled:opacity-60 transition shadow-sm">
            {{ processing ? 'Connexion…' : '→ Se connecter' }}
          </button>
          <div class="text-center">
            <button v-if="allowRegister" type="button" @click="mode = 'register'"
                    class="text-gray-500 text-xs hover:text-[#FF4D2E] transition">
              Pas encore de compte ? Créer un compte sur @{{ displayDomain }}
            </button>
          </div>
        </form>

        <!-- REGISTER -->
        <form v-else @submit.prevent="submitRegister" class="mt-8 space-y-4">
          <label class="block">
            <span class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Nom d'utilisateur</span>
            <div class="flex items-center bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-lg overflow-hidden focus-within:border-[#FF4D2E] focus-within:ring-2 focus-within:ring-[#FF4D2E]/20">
              <input v-model="regForm.local" type="text" required autocomplete="username"
                     pattern="^[a-zA-Z0-9][a-zA-Z0-9._\-]{1,63}$"
                     class="flex-1 bg-transparent px-3 py-2.5 text-gray-900 dark:text-gray-100 text-sm focus:outline-none"
                     placeholder="prenom.nom" />
              <span class="px-3 text-gray-500 text-sm">@{{ displayDomain }}</span>
            </div>
          </label>
          <label class="block">
            <span class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Nom affiché (optionnel)</span>
            <input v-model="regForm.display_name" type="text" autocomplete="name"
                   class="w-full bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-lg px-3 py-2.5 text-sm focus:border-[#FF4D2E] focus:ring-2 focus:ring-[#FF4D2E]/20 focus:outline-none"
                   placeholder="Jean Dupont" />
          </label>
          <label class="block">
            <span class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Mot de passe <span class="text-gray-400 font-normal">(10 caractères minimum)</span></span>
            <input v-model="regForm.password" type="password" required minlength="10" autocomplete="new-password"
                   class="w-full bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-lg px-3 py-2.5 text-sm focus:border-[#FF4D2E] focus:ring-2 focus:ring-[#FF4D2E]/20 focus:outline-none" />
          </label>
          <p v-if="errors.local" class="text-rose-500 text-xs">{{ errors.local }}</p>
          <p v-if="errors.password" class="text-rose-500 text-xs">{{ errors.password }}</p>
          <button type="submit" :disabled="processing"
                  class="w-full mt-2 px-6 py-2.5 rounded-lg bg-[#FF4D2E] text-white font-bold text-sm hover:bg-[#df3c1f] disabled:opacity-60 transition shadow-sm">
            {{ processing ? 'Création…' : '→ Créer mon compte' }}
          </button>
          <div class="text-center">
            <button type="button" @click="mode = 'login'"
                    class="text-gray-500 text-xs hover:text-[#FF4D2E] transition">
              ← Retour à la connexion
            </button>
          </div>
        </form>

        <div class="mt-10 pt-6 border-t border-gray-100 dark:border-zinc-800 text-center">
          <p v-if="footer_tagline" class="text-gray-400 text-xs">{{ footer_tagline }}</p>
          <a v-if="footer_label && footer_url" :href="footer_url" target="_blank" rel="noopener"
             class="text-gray-400 text-[11px] hover:text-[#FF4D2E] transition mt-1 inline-block">
            {{ footer_label }}
          </a>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AnimatedLogo from '../Components/AnimatedLogo.vue';

const props = defineProps({
  allowRegister:  { type: Boolean, default: true },
  registerMode:   { type: Boolean, default: false },
  domain:         { type: String,  default: 'example.com' },
  // Branding personnalisable par l'owner de l'organisation
  org_name:       { type: String,  default: null },
  org_logo_url:   { type: String,  default: null },
  // Domaine primaire : sélectionné en priorité dans les placeholders / texte
  primary_domain: { type: String,  default: '' },
  footer_label:   { type: String,  default: '' },
  footer_url:     { type: String,  default: '' },
  footer_tagline: { type: String,  default: '' },
});
const displayDomain = computed(() => props.primary_domain || props.domain);

const mode = ref(props.registerMode ? 'register' : 'login');
const processing = ref(false);
const errors = computed(() => usePage().props.errors || {});

const loginForm = reactive({ email: '', password: '' });
const regForm   = reactive({ local: '', display_name: '', password: '' });

function submitLogin() {
  processing.value = true;
  router.post('/login', loginForm, {
    preserveScroll: true,
    onFinish: () => { processing.value = false; },
  });
}
function submitRegister() {
  processing.value = true;
  router.post('/register', regForm, {
    preserveScroll: true,
    onFinish: () => { processing.value = false; },
  });
}
</script>
