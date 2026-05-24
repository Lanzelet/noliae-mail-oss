<template>
  <div class="min-h-screen flex flex-col lg:flex-row bg-white dark:bg-zinc-900">
    <!-- ── Panneau gauche : logo + badge Admin Center ── -->
    <aside class="lg:w-1/3 xl:w-2/5 bg-gradient-to-br from-zinc-900 via-zinc-800 to-zinc-900 flex items-center justify-center relative overflow-hidden min-h-[160px] lg:min-h-screen">
      <div class="flex flex-col items-center gap-4">
        <svg viewBox="0 0 140 100" class="w-2/3 max-w-md opacity-90">
          <rect x="12" y="35" width="10" height="30" rx="5" fill="white"/>
          <rect x="30" y="25" width="10" height="50" rx="5" fill="white"/>
          <rect x="48" y="15" width="10" height="70" rx="5" fill="white"/>
          <rect x="66" y="22" width="10" height="56" rx="5" fill="#FF4D2E"/>
          <rect x="84" y="20" width="10" height="60" rx="5" fill="white"/>
          <rect x="102" y="30" width="10" height="40" rx="5" fill="white"/>
          <rect x="120" y="35" width="10" height="30" rx="5" fill="white"/>
        </svg>
        <span class="text-[10px] uppercase tracking-wider bg-[#FF4D2E] text-white px-2.5 py-1 rounded font-bold">Admin Center</span>
      </div>
    </aside>

    <!-- ── Panneau droit : formulaire ── -->
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
          <span class="font-black text-xl">Admin Center</span>
        </div>

        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Connexion administrateur</h2>
        <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">
          {{ org_name || `Organisation @${domain}` }}
        </p>

        <form @submit.prevent="submit" class="mt-8 space-y-4">
          <label class="block">
            <span class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Adresse mail admin</span>
            <input v-model="form.email" type="email" required autocomplete="username"
                   class="w-full bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-lg px-3 py-2.5 text-sm focus:border-[#FF4D2E] focus:ring-2 focus:ring-[#FF4D2E]/20 focus:outline-none"
                   :placeholder="`admin@${domain}`" />
          </label>
          <label class="block">
            <span class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Mot de passe</span>
            <input v-model="form.password" type="password" required autocomplete="current-password"
                   class="w-full bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-lg px-3 py-2.5 text-sm focus:border-[#FF4D2E] focus:ring-2 focus:ring-[#FF4D2E]/20 focus:outline-none" />
          </label>
          <p v-if="errors.email" class="text-rose-500 text-xs">{{ errors.email }}</p>
          <button type="submit" :disabled="processing"
                  class="w-full mt-2 px-6 py-3 rounded-lg bg-[#FF4D2E] text-white font-bold text-sm hover:bg-[#df3c1f] disabled:opacity-60 transition shadow-sm flex items-center justify-center gap-2">
            <span>→</span>
            {{ processing ? 'Connexion…' : 'Accéder à l\'admin' }}
          </button>
          <div class="text-center pt-2">
            <a href="/login" class="text-gray-500 text-xs hover:text-[#FF4D2E] transition">
              Connexion utilisateur standard
            </a>
          </div>
        </form>

        <div class="mt-10 pt-6 border-t border-gray-100 dark:border-zinc-800 text-center">
          <p class="text-gray-400 text-[11px]">Noliae Mail Admin Center</p>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

defineProps({ domain: String, org_name: String });

const form = reactive({ email: '', password: '' });
const processing = ref(false);
const errors = computed(() => usePage().props.errors || {});

function submit() {
  processing.value = true;
  // On réutilise POST /login (même endpoint, même session) ; au succès on
  // sera redirigé sur /webmail par défaut — mais l'utilisateur peut juste
  // cliquer sur "Admin" dans la sidebar. Redirige direct si admin :
  router.post('/login', { ...form, redirect: '/admin' }, {
    preserveScroll: true,
    onFinish: () => { processing.value = false; },
  });
}
</script>
