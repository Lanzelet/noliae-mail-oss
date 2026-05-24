<template>
  <div class="min-h-screen flex flex-col lg:flex-row bg-white dark:bg-zinc-900">
    <!-- ── Panneau gauche : logo animé sur gradient sombre ── -->
    <aside class="lg:w-1/3 xl:w-2/5 bg-gradient-to-br from-zinc-900 via-zinc-800 to-zinc-900 flex items-center justify-center relative overflow-hidden min-h-[160px] lg:min-h-screen">
      <AnimatedLogo color="white" accent="#FF4D2E" class="w-1/3 max-w-[260px] opacity-60"/>
    </aside>

    <!-- ── Panneau droit : formulaire ── -->
    <main class="flex-1 flex items-center justify-center px-6 py-12 lg:py-0">
      <div class="w-full max-w-md">
        <!-- En mobile, branding Admin Center caché si un logo orga custom est uploadé. -->
        <div v-if="!org_logo_url" class="lg:hidden flex items-center gap-2 mb-8">
          <AnimatedLogo color="#FF4D2E" accent="#FF4D2E" class="h-8 w-auto"/>
          <span class="font-black text-xl">Admin Center</span>
        </div>

        <div v-if="org_logo_url" class="mb-6">
          <img :src="org_logo_url" :alt="org_name || 'Organisation'"
               class="max-h-16 max-w-[240px] object-contain"/>
        </div>

        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Connexion administrateur</h2>
        <!-- Si un logo est uploadé on n'affiche pas le nom orga en sous-titre
             (le logo le dit déjà). Sinon on garde le fallback @domain. -->
        <p v-if="!org_logo_url" class="text-gray-500 dark:text-gray-400 text-sm mt-1">
          {{ org_name || `Organisation @${displayDomain}` }}
        </p>

        <form @submit.prevent="submit" class="mt-8 space-y-4">
          <label class="block">
            <span class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Adresse mail admin</span>
            <input v-model="form.email" type="email" required autocomplete="username"
                   class="w-full bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-lg px-3 py-2.5 text-sm focus:border-[#FF4D2E] focus:ring-2 focus:ring-[#FF4D2E]/20 focus:outline-none"
                   :placeholder="`admin@${displayDomain}`" />
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
  domain: String, org_name: String, org_logo_url: String,
  primary_domain: { type: String, default: '' },
  footer_label: { type: String, default: '' },
  footer_url:   { type: String, default: '' },
  footer_tagline:{ type: String, default: '' },
});
const displayDomain = computed(() => props.primary_domain || props.domain);

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
