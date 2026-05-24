<template>
  <div class="min-h-screen bg-gray-50 dark:bg-zinc-900 text-gray-900 dark:text-gray-100 flex">
    <!-- ── Sidebar Exchange-style ──────────────────────────────────── -->
    <aside :class="['fixed lg:sticky top-0 inset-y-0 z-30 bg-white dark:bg-zinc-950 border-r border-gray-200 dark:border-zinc-800 transition-transform duration-200 ease-in-out',
                    'w-64 flex flex-col h-screen', sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0']">
      <div class="px-5 py-4 border-b border-gray-100 dark:border-zinc-800">
        <Link href="/admin" class="flex items-center gap-2 group">
          <AnimatedLogo color="currentColor" accent="#FF4D2E" class="h-6 w-auto"/>
          <div>
            <div class="font-bold text-sm leading-none">Noliae Mail</div>
            <div class="text-[10px] uppercase tracking-wider text-[#FF4D2E] font-bold mt-0.5">Admin Center</div>
          </div>
        </Link>
      </div>

      <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-4 text-sm">
        <div v-for="section in sections" :key="section.label">
          <div class="px-3 mb-1 text-[10px] uppercase tracking-wider text-gray-400 font-bold flex items-center gap-1.5">
            <span>{{ section.icon }}</span>{{ section.label }}
          </div>
          <Link v-for="t in section.items" :key="t.href" :href="t.href"
            :class="['flex items-center gap-2 px-3 py-2 rounded-lg transition mb-0.5',
                     isActive(t.href)
                       ? 'bg-[#FF4D2E]/10 text-[#FF4D2E] font-semibold'
                       : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-zinc-900']">
            <span class="w-4 text-center">{{ t.emoji }}</span>
            <span class="flex-1 truncate">{{ t.label }}</span>
            <span v-if="t.badge" class="text-[10px] font-bold bg-[#FF4D2E] text-white px-1.5 py-0.5 rounded">{{ t.badge }}</span>
          </Link>
        </div>
      </nav>

      <div class="border-t border-gray-100 dark:border-zinc-800 p-3 space-y-1">
        <a href="/webmail"
          class="flex items-center gap-2 px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-zinc-900 text-sm">
          <span class="w-4 text-center">📧</span>Retour au webmail
        </a>
        <a href="/account"
          class="flex items-center gap-2 px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-zinc-900 text-sm">
          <span class="w-4 text-center">👤</span>Mon compte
        </a>
      </div>
    </aside>

    <!-- ── Overlay mobile ── -->
    <div v-if="sidebarOpen" @click="sidebarOpen=false"
      class="lg:hidden fixed inset-0 bg-black/40 z-20"/>

    <!-- ── Main area ─────────────────────────────────────────────── -->
    <div class="flex-1 min-w-0 flex flex-col">
      <header class="bg-white dark:bg-zinc-800 border-b border-gray-200 dark:border-zinc-700 sticky top-0 z-10">
        <div class="px-5 py-3 flex items-center justify-between gap-3">
          <div class="flex items-center gap-3 min-w-0">
            <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden text-gray-500 hover:text-gray-900 dark:hover:text-gray-100">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
            </button>
            <h1 class="text-lg font-bold truncate">{{ title }}</h1>
            <span v-if="page?.props?.org_name" class="hidden md:inline text-xs text-gray-500 truncate">· {{ page.props.org_name }}</span>
          </div>
          <div class="flex items-center gap-2 text-xs text-gray-500 shrink-0">
            <span v-if="adminEmail" class="hidden sm:block font-mono">{{ adminEmail }}</span>
            <span v-if="userRole" :class="['hidden sm:inline-block text-[10px] uppercase font-bold px-2 py-0.5 rounded',
              userRole === 'owner' ? 'bg-purple-100 text-purple-700' :
              userRole === 'admin' ? 'bg-red-100 text-red-700' :
              'bg-blue-100 text-blue-700']">{{ userRole }}</span>
            <button @click="logout" type="button" class="px-3 py-1.5 rounded-lg text-gray-600 hover:bg-gray-100 dark:hover:bg-zinc-900 hover:text-[#FF4D2E]">
              Déconnexion
            </button>
          </div>
        </div>
      </header>

      <main class="flex-1 px-5 py-6 max-w-6xl w-full mx-auto">
        <div v-if="flash?.success" class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ flash.success }}</div>
        <slot/>
      </main>
    </div>
  </div>
</template>
<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AnimatedLogo from '../../Components/AnimatedLogo.vue';
defineProps({ title: String, admin_email: String, app_domain: String });
const page = computed(() => usePage());
const sidebarOpen = ref(false);

function logout() { router.post('/logout'); }
function isActive(h) {
  const u = page.value.url;
  if (h === '/admin') return u === '/admin';
  if (h === '/org') return u === '/org' || u === '/org/';
  return u.startsWith(h);
}

// Navigation Exchange-style : sections groupées
const sections = [
  {
    label: 'Vue d\'ensemble', icon: '📊',
    items: [
      { label: 'Tableau de bord', emoji: '🏠', href: '/admin' },
    ],
  },
  {
    label: 'Organisation', icon: '🏢',
    items: [
      { label: 'Paramètres',     emoji: '⚙️',  href: '/org' },
      { label: 'Membres & RBAC', emoji: '👥',  href: '/org/members' },
      { label: 'Annuaire',       emoji: '📇',  href: '/people' },
      { label: 'Carnet adresses',emoji: '📒',  href: '/contacts' },
    ],
  },
  {
    label: 'Boîtes mail', icon: '📬',
    items: [
      { label: 'Domaines',       emoji: '🌐', href: '/admin/domains' },
      { label: 'Comptes',        emoji: '👤', href: '/admin/accounts' },
      { label: 'Boîtes partagées', emoji: '🤝', href: '/admin/shared' },
      { label: 'Forwards',       emoji: '↪️', href: '/admin/aliases' },
      { label: 'Listes',         emoji: '📋', href: '/admin/lists' },
    ],
  },
  {
    label: 'Sécurité', icon: '🛡️',
    items: [
      { label: 'Anti-spam',      emoji: '🚫', href: '/admin/rspamd' },
      { label: 'Quarantaine',    emoji: '⚠️', href: '/admin/spam' },
      { label: 'Audit log',      emoji: '📜', href: '/admin/audit' },
    ],
  },
  {
    label: 'Système', icon: '🔧',
    items: [
      { label: 'Migrations IMAP',emoji: '📥', href: '/admin/migrations' },
      { label: 'Paramètres app', emoji: '⚙️', href: '/admin/settings' },
    ],
  },
];

const flash = computed(() => page.value.props.flash);
const adminEmail = computed(() => page.value.props.admin_email || page.value.props.me_email || '');
const userRole = computed(() => page.value.props.user_role || page.value.props.role || '');
</script>
