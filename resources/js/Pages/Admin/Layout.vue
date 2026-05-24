<template>
  <div class="min-h-screen bg-gray-50 dark:bg-zinc-900 text-gray-900 dark:text-gray-100">
    <header class="bg-white dark:bg-zinc-800 border-b border-gray-200 dark:border-zinc-700">
      <div class="max-w-6xl mx-auto px-5 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <svg viewBox="0 0 140 100" class="h-6"><rect x="12" y="35" width="10" height="30" rx="5" fill="currentColor"/><rect x="30" y="25" width="10" height="50" rx="5" fill="currentColor"/><rect x="48" y="15" width="10" height="70" rx="5" fill="currentColor"/><rect x="66" y="22" width="10" height="56" rx="5" fill="#FF4D2E"/><rect x="84" y="20" width="10" height="60" rx="5" fill="currentColor"/><rect x="102" y="30" width="10" height="40" rx="5" fill="currentColor"/><rect x="120" y="35" width="10" height="30" rx="5" fill="currentColor"/></svg>
          <span class="font-bold">Noliae Mail</span>
          <span class="text-[10px] uppercase tracking-wider bg-[#FF4D2E]/15 text-[#FF4D2E] px-1.5 py-0.5 rounded">Admin</span>
        </div>
        <div class="text-xs text-gray-500">
          <span class="font-mono">{{ admin_email }}</span>
          <a href="/webmail" class="ml-4 text-gray-600 hover:text-[#FF4D2E]">← Webmail</a>
          <button @click="logout" type="button" class="ml-3 text-gray-600 hover:text-[#FF4D2E]">Déconnexion</button>
        </div>
      </div>
      <nav class="max-w-6xl mx-auto px-5 flex gap-1 text-sm">
        <Link v-for="t in tabs" :key="t.href" :href="t.href"
              :class="['px-4 py-2.5 border-b-2 transition',
                       isActive(t.href) ? 'border-[#FF4D2E] text-[#FF4D2E] font-semibold' : 'border-transparent text-gray-500 hover:text-gray-800 dark:hover:text-gray-200']">
          {{ t.label }}
        </Link>
      </nav>
    </header>

    <main class="max-w-6xl mx-auto px-5 py-8">
      <div v-if="flash?.success" class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ flash.success }}</div>
      <h1 class="text-2xl font-bold mb-6">{{ title }}</h1>
      <slot/>
    </main>
  </div>
</template>
<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
function logout() { router.post('/logout'); }
import { computed } from 'vue';
defineProps({ title: String, admin_email: String, app_domain: String });
const tabs = [
  { label: 'Tableau de bord', href: '/admin' },
  { label: 'Domaines',        href: '/admin/domains' },
  { label: 'Comptes',         href: '/admin/accounts' },
  { label: 'Forwards',        href: '/admin/aliases' },
  { label: 'Listes',          href: '/admin/lists' },
  { label: 'Partagées',       href: '/admin/shared' },
  { label: 'Paramètres',      href: '/admin/settings' },
];
const flash = computed(() => usePage().props.flash);
function isActive(h) { const u = usePage().url; return h === '/admin' ? u === '/admin' : u.startsWith(h); }
</script>
