<template>
  <div class="min-h-screen flex flex-col items-center justify-center px-4"
       style="background:linear-gradient(135deg,#15151a 0%,#2a1510 100%)">
    <svg viewBox="0 0 520 100" height="46" class="mb-8">
      <rect x="12" y="35" width="10" height="30" rx="5" fill="#FAFAF7"/>
      <rect x="30" y="25" width="10" height="50" rx="5" fill="#FAFAF7"/>
      <rect x="48" y="15" width="10" height="70" rx="5" fill="#FAFAF7"/>
      <rect x="66" y="22" width="10" height="56" rx="5" fill="#FF4D2E"/>
      <rect x="84" y="20" width="10" height="60" rx="5" fill="#FAFAF7"/>
      <rect x="102" y="30" width="10" height="40" rx="5" fill="#FAFAF7"/>
      <rect x="120" y="35" width="10" height="30" rx="5" fill="#FAFAF7"/>
      <text x="170" y="68" font-family="ui-monospace,Menlo,monospace" font-weight="500"
            font-size="64" letter-spacing="-3.2" fill="#FAFAF7">noliae</text>
    </svg>

    <div class="bg-white/[0.04] border border-white/10 rounded-3xl p-8 max-w-sm w-full"
         style="border-top:3px solid #FF4D2E">
      <h1 class="text-white text-lg font-black text-center">Noliae Mail</h1>
      <p class="text-gray-400 text-xs mt-1 text-center">
        {{ mode === 'register' ? 'Créer un nouveau compte' : 'Connexion à votre boîte mail' }}
      </p>

      <!-- LOGIN -->
      <form v-if="mode === 'login'" @submit.prevent="submitLogin" class="mt-6 space-y-3">
        <label class="block">
          <span class="block text-[11px] uppercase tracking-wider text-gray-400 mb-1">Adresse mail</span>
          <input v-model="loginForm.email" type="email" required autocomplete="username"
                 class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:border-[#FF4D2E] focus:outline-none"
                 :placeholder="`nom@${domain}`" />
        </label>
        <label class="block">
          <span class="block text-[11px] uppercase tracking-wider text-gray-400 mb-1">Mot de passe</span>
          <input v-model="loginForm.password" type="password" required autocomplete="current-password"
                 class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:border-[#FF4D2E] focus:outline-none" />
        </label>
        <p v-if="errors.email" class="text-rose-400 text-xs">{{ errors.email }}</p>
        <button type="submit" :disabled="processing"
                class="w-full mt-2 px-6 py-2.5 rounded-full bg-[#FF4D2E] text-white font-bold text-sm hover:bg-[#df3c1f] disabled:opacity-60 transition">
          {{ processing ? 'Connexion…' : 'Se connecter' }}
        </button>
        <button v-if="allowRegister" type="button" @click="mode = 'register'; clearErrors()"
                class="w-full text-gray-400 text-xs hover:text-white transition">
          Pas encore de compte ? Créer un compte sur @{{ domain }}
        </button>
      </form>

      <!-- REGISTER -->
      <form v-else @submit.prevent="submitRegister" class="mt-6 space-y-3">
        <label class="block">
          <span class="block text-[11px] uppercase tracking-wider text-gray-400 mb-1">Nom d'utilisateur</span>
          <div class="flex items-center bg-black/30 border border-white/10 rounded-lg overflow-hidden focus-within:border-[#FF4D2E]">
            <input v-model="regForm.local" type="text" required autocomplete="username"
                   pattern="^[a-zA-Z0-9][a-zA-Z0-9._\-]{1,63}$"
                   class="flex-1 bg-transparent px-3 py-2 text-white text-sm focus:outline-none"
                   placeholder="prenom.nom" />
            <span class="px-3 text-gray-500 text-sm">@{{ domain }}</span>
          </div>
        </label>
        <label class="block">
          <span class="block text-[11px] uppercase tracking-wider text-gray-400 mb-1">Nom affiché (optionnel)</span>
          <input v-model="regForm.display_name" type="text" autocomplete="name"
                 class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:border-[#FF4D2E] focus:outline-none"
                 placeholder="Jean Dupont" />
        </label>
        <label class="block">
          <span class="block text-[11px] uppercase tracking-wider text-gray-400 mb-1">Mot de passe (10 caractères min)</span>
          <input v-model="regForm.password" type="password" required minlength="10" autocomplete="new-password"
                 class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:border-[#FF4D2E] focus:outline-none" />
        </label>
        <p v-if="errors.local" class="text-rose-400 text-xs">{{ errors.local }}</p>
        <p v-if="errors.password" class="text-rose-400 text-xs">{{ errors.password }}</p>
        <button type="submit" :disabled="processing"
                class="w-full mt-2 px-6 py-2.5 rounded-full bg-[#FF4D2E] text-white font-bold text-sm hover:bg-[#df3c1f] disabled:opacity-60 transition">
          {{ processing ? 'Création…' : 'Créer mon compte' }}
        </button>
        <button type="button" @click="mode = 'login'; clearErrors()"
                class="w-full text-gray-400 text-xs hover:text-white transition">
          ← Retour à la connexion
        </button>
      </form>
    </div>
    <p class="text-gray-600 text-xs mt-6">Noliae Mail — messagerie souveraine, open source</p>
    <a href="https://github.com/Noliae/noliae-mail-oss" target="_blank" rel="noopener"
       class="text-gray-700 text-[11px] mt-1 hover:text-gray-500">github.com/Noliae/noliae-mail-oss</a>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
  allowRegister: { type: Boolean, default: true },
  registerMode:  { type: Boolean, default: false },
  domain:        { type: String,  default: 'example.com' },
});

const mode = ref(props.registerMode ? 'register' : 'login');
const processing = ref(false);
const errors = computed(() => usePage().props.errors || {});

const loginForm = reactive({ email: '', password: '' });
const regForm   = reactive({ local: '', display_name: '', password: '' });

function clearErrors() { /* Inertia clears on next request */ }

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
