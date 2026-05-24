<template>
  <div class="min-h-screen flex flex-col items-center justify-center px-4"
       style="background:linear-gradient(135deg,#15151a 0%,#2a1510 100%)">
    <svg viewBox="0 0 520 100" height="40" class="mb-6 opacity-90">
      <rect x="12" y="35" width="10" height="30" rx="5" fill="#FAFAF7"/>
      <rect x="30" y="25" width="10" height="50" rx="5" fill="#FAFAF7"/>
      <rect x="48" y="15" width="10" height="70" rx="5" fill="#FAFAF7"/>
      <rect x="66" y="22" width="10" height="56" rx="5" fill="#FF4D2E"/>
      <rect x="84" y="20" width="10" height="60" rx="5" fill="#FAFAF7"/>
      <rect x="102" y="30" width="10" height="40" rx="5" fill="#FAFAF7"/>
      <rect x="120" y="35" width="10" height="30" rx="5" fill="#FAFAF7"/>
    </svg>
    <div class="bg-white/[0.04] border border-white/10 rounded-3xl p-8 max-w-sm w-full text-center"
         style="border-top:3px solid #FF4D2E">
      <div class="w-14 h-14 mx-auto rounded-2xl bg-[#FF4D2E]/15 flex items-center justify-center mb-3">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#FF4D2E" stroke-width="2">
          <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
      </div>
      <h1 class="text-white text-lg font-black mb-1">Vérification 2FA</h1>
      <p class="text-gray-400 text-xs mb-5">Entre le code à 6 chiffres de ton application d'authentification.</p>

      <form @submit.prevent="submit">
        <input v-model="code" type="text" inputmode="numeric" autocomplete="one-time-code" required maxlength="25"
               class="w-full text-center text-2xl tracking-[0.4em] font-mono bg-black/30 border border-white/10 rounded-xl px-3 py-3 text-white focus:border-[#FF4D2E] focus:outline-none"
               placeholder="000000" autofocus />
        <p v-if="errors.code" class="text-rose-400 text-xs mt-2">{{ errors.code }}</p>
        <button type="submit" :disabled="processing"
                class="w-full mt-4 px-6 py-2.5 rounded-full bg-[#FF4D2E] text-white font-bold text-sm hover:bg-[#df3c1f] disabled:opacity-60 transition">
          {{ processing ? 'Vérification…' : 'Valider' }}
        </button>
        <p class="text-[11px] text-gray-500 mt-4">
          Tu peux aussi entrer un <strong>code de récupération</strong> (format <code>xxxx-xxxx</code>).
        </p>
      </form>
    </div>
  </div>
</template>
<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
const code = ref('');
const processing = ref(false);
const errors = computed(() => usePage().props.errors || {});
function submit() {
  processing.value = true;
  router.post('/login/2fa', { code: code.value }, { onFinish: () => processing.value = false });
}
</script>
