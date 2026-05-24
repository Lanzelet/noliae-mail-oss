<template>
  <div class="min-h-screen bg-gray-50 dark:bg-zinc-950 text-gray-900 dark:text-gray-100">
    <header class="bg-white dark:bg-zinc-900 border-b border-gray-200 dark:border-zinc-800 sticky top-0 z-10">
      <div class="max-w-5xl mx-auto px-5 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <svg viewBox="0 0 140 100" class="h-6"><rect x="12" y="35" width="10" height="30" rx="5" fill="currentColor"/><rect x="30" y="25" width="10" height="50" rx="5" fill="currentColor"/><rect x="48" y="15" width="10" height="70" rx="5" fill="currentColor"/><rect x="66" y="22" width="10" height="56" rx="5" fill="#FF4D2E"/><rect x="84" y="20" width="10" height="60" rx="5" fill="currentColor"/><rect x="102" y="30" width="10" height="40" rx="5" fill="currentColor"/><rect x="120" y="35" width="10" height="30" rx="5" fill="currentColor"/></svg>
          <span class="font-bold">Carnet d'adresses</span>
        </div>
        <div class="text-xs text-gray-500 flex items-center gap-4">
          <Link href="/people" class="text-gray-600 hover:text-[#FF4D2E]">👥 Annuaire</Link>
          <Link href="/webmail" class="text-gray-600 hover:text-[#FF4D2E]">Webmail →</Link>
        </div>
      </div>
    </header>

    <main class="max-w-5xl mx-auto px-5 py-8 space-y-6">
      <div v-if="flash?.success" class="px-4 py-2.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ flash.success }}</div>

      <form @submit.prevent="search" class="flex gap-2">
        <input v-model="qDraft" type="search" placeholder="Chercher email, nom, société…"
          class="flex-1 px-4 py-2.5 bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-600 rounded-xl text-gray-900 dark:text-gray-100"/>
        <button type="submit" class="px-4 py-2.5 bg-[#FF4D2E] text-white rounded-xl text-sm font-bold">Chercher</button>
      </form>

      <!-- Tabs Personnel / Organisation -->
      <div class="flex gap-1 border-b border-gray-200 dark:border-zinc-800">
        <button v-for="t in tabs" :key="t.id" @click="activeTab = t.id"
          :class="['px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition',
                   activeTab === t.id
                     ? 'border-[#FF4D2E] text-[#FF4D2E]'
                     : 'border-transparent text-gray-500 hover:text-gray-800 dark:hover:text-gray-200']">
          {{ t.label }}
          <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 dark:bg-zinc-800 text-gray-600 dark:text-zinc-300">{{ t.count }}</span>
        </button>
      </div>

      <!-- ─── PERSONNEL ─── -->
      <div v-show="activeTab === 'personal'" class="space-y-5">
        <section class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-200 dark:border-zinc-700 p-5">
          <h2 class="font-bold mb-1">➕ Nouveau contact personnel</h2>
          <p class="text-[11px] text-gray-500 dark:text-zinc-400 mb-3">Privé à ton compte. Les destinataires de tes mails y sont aussi ajoutés automatiquement.</p>
          <form @submit.prevent="createPersonal" class="grid grid-cols-1 md:grid-cols-2 gap-2">
            <input v-model="personalForm.email" required type="email" placeholder="email@exemple.com" class="px-3 py-2 text-sm bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-600 rounded-xl"/>
            <input v-model="personalForm.display_name" placeholder="Nom affiché" class="px-3 py-2 text-sm bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-600 rounded-xl"/>
            <input v-model="personalForm.company" placeholder="Société" class="px-3 py-2 text-sm bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-600 rounded-xl"/>
            <input v-model="personalForm.job_title" placeholder="Fonction" class="px-3 py-2 text-sm bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-600 rounded-xl"/>
            <input v-model="personalForm.phone" placeholder="Téléphone" class="px-3 py-2 text-sm bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-600 rounded-xl md:col-span-2"/>
            <button type="submit" class="md:col-span-2 px-4 py-2 bg-[#FF4D2E] text-white rounded-xl text-sm font-bold">Ajouter</button>
            <p v-if="errors.email" class="text-rose-500 text-xs md:col-span-2">{{ errors.email }}</p>
          </form>
        </section>

        <div class="text-xs text-gray-500 dark:text-zinc-400">{{ personal.length }} contact{{ personal.length !== 1 ? 's' : '' }} personnel{{ personal.length !== 1 ? 's' : '' }}</div>

        <div class="space-y-2">
          <ContactRow v-for="c in personal" :key="c.id" :c="c"
            :show-delete="true" @delete="removePersonal(c)"/>
          <div v-if="!personal.length" class="text-center text-gray-400 py-12 text-sm">
            Aucun contact personnel encore. Envoie un mail à quelqu'un — son adresse sera automatiquement ajoutée.
          </div>
        </div>
      </div>

      <!-- ─── ORGANISATION ─── -->
      <div v-show="activeTab === 'org'" class="space-y-5">
        <section v-if="can_edit" class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-200 dark:border-zinc-700 p-5">
          <h2 class="font-bold mb-1">➕ Nouveau contact organisation</h2>
          <p class="text-[11px] text-gray-500 dark:text-zinc-400 mb-3">Visible par tous les membres de ton organisation.</p>
          <form @submit.prevent="createOrg" class="grid grid-cols-1 md:grid-cols-2 gap-2">
            <input v-model="orgForm.email" required type="email" placeholder="email@exemple.com" class="px-3 py-2 text-sm bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-600 rounded-xl"/>
            <input v-model="orgForm.display_name" placeholder="Nom affiché" class="px-3 py-2 text-sm bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-600 rounded-xl"/>
            <input v-model="orgForm.company" placeholder="Société" class="px-3 py-2 text-sm bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-600 rounded-xl"/>
            <input v-model="orgForm.job_title" placeholder="Fonction" class="px-3 py-2 text-sm bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-600 rounded-xl"/>
            <input v-model="orgForm.phone" placeholder="Téléphone" class="px-3 py-2 text-sm bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-600 rounded-xl md:col-span-2"/>
            <button type="submit" class="md:col-span-2 px-4 py-2 bg-[#FF4D2E] text-white rounded-xl text-sm font-bold">Ajouter</button>
          </form>
        </section>

        <div class="text-xs text-gray-500 dark:text-zinc-400">{{ org.length }} contact{{ org.length !== 1 ? 's' : '' }} organisation</div>

        <div class="space-y-2">
          <ContactRow v-for="c in org" :key="c.id" :c="c"
            :show-delete="can_edit && c.source !== 'member'" @delete="removeOrg(c)"/>
          <div v-if="!org.length" class="text-center text-gray-400 py-12 text-sm">Aucun contact organisation.</div>
        </div>
      </div>
    </main>
  </div>
</template>
<script setup>
import { reactive, ref, computed, h } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
  personal: { type: Array, default: () => [] },
  org:      { type: Array, default: () => [] },
  q: String, role: String, can_edit: Boolean,
});
const qDraft = ref(props.q || '');
const activeTab = ref('personal');
const tabs = computed(() => [
  { id: 'personal', label: 'Mes contacts',         count: props.personal.length },
  { id: 'org',      label: 'Carnet organisation',  count: props.org.length },
]);

const personalForm = reactive({ email:'', display_name:'', company:'', job_title:'', phone:'' });
const orgForm      = reactive({ email:'', display_name:'', company:'', job_title:'', phone:'' });
const flash  = computed(() => usePage().props.flash);
const errors = computed(() => usePage().props.errors || {});

function search()         { router.get('/contacts', { q: qDraft.value }, { preserveState: false }); }
function createPersonal() { router.post('/contacts/personal', personalForm, { preserveScroll: true, onSuccess: () => Object.keys(personalForm).forEach(k => personalForm[k]='') }); }
function createOrg()      { router.post('/contacts',          orgForm,      { preserveScroll: true, onSuccess: () => Object.keys(orgForm).forEach(k => orgForm[k]='') }); }
function removePersonal(c){ if (confirm(`Retirer ${c.email} de ton carnet ?`)) router.delete(`/contacts/personal/${c.id}`, { preserveScroll: true }); }
function removeOrg(c)     { if (confirm(`Supprimer ${c.email} du carnet org ?`)) router.delete(`/contacts/${c.id}`, { preserveScroll: true }); }

// Ligne contact réutilisable (rendu inline pour simplicité)
const ContactRow = {
  props: ['c', 'showDelete'],
  emits: ['delete'],
  setup(p, { emit }) {
    return () => h('div', { class: 'bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl p-3 flex items-center gap-3' }, [
      h('img', {
        src: `/webmail/avatar/${p.c.avatar_hash}?n=${encodeURIComponent(p.c.display_name || p.c.email)}`,
        class: 'w-10 h-10 rounded-full object-cover bg-gray-200 dark:bg-zinc-700',
      }),
      h('div', { class: 'flex-1 min-w-0' }, [
        h('div', { class: 'font-semibold text-sm truncate text-gray-900 dark:text-gray-100' }, [
          p.c.display_name || p.c.email,
          p.c.company ? h('span', { class: 'text-gray-500 dark:text-zinc-400 font-normal' }, ` · ${p.c.company}`) : null,
        ]),
        h('div', { class: 'text-xs text-gray-500 dark:text-zinc-400 truncate' }, [
          h('a', { href: `mailto:${p.c.email}`, class: 'font-mono hover:text-[#FF4D2E]' }, p.c.email),
          p.c.job_title ? ` · ${p.c.job_title}` : null,
          p.c.phone ? ` · ${p.c.phone}` : null,
        ]),
      ]),
      // Badge source
      p.c.source === 'member'
        ? h('span', { class: 'text-[10px] uppercase font-bold bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-200 px-1.5 py-0.5 rounded' }, 'membre')
        : p.c.source === 'auto'
        ? h('span', { class: 'text-[10px] uppercase font-bold bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-200 px-1.5 py-0.5 rounded' }, 'auto')
        : null,
      p.showDelete
        ? h('button', { onClick: () => emit('delete'), class: 'text-xs text-gray-400 hover:text-rose-600' }, '×')
        : null,
    ]);
  },
};
</script>
