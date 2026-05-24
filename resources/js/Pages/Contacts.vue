<template>
  <div class="min-h-screen bg-gray-50 dark:bg-zinc-900 text-gray-900 dark:text-gray-100">
    <header class="bg-white dark:bg-zinc-800 border-b border-gray-200 dark:border-zinc-700 sticky top-0 z-10">
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

    <main class="max-w-5xl mx-auto px-5 py-8 space-y-5">
      <div v-if="flash?.success" class="px-4 py-2.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ flash.success }}</div>

      <form @submit.prevent="search" class="flex gap-2">
        <input v-model="qDraft" type="search" placeholder="Chercher email, nom, société…"
          class="flex-1 px-4 py-2.5 bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl"/>
        <button type="submit" class="px-4 py-2.5 bg-[#FF4D2E] text-white rounded-xl text-sm font-bold">Chercher</button>
      </form>

      <section v-if="can_edit" class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 p-5">
        <h2 class="font-bold mb-3">➕ Nouveau contact</h2>
        <form @submit.prevent="create" class="grid grid-cols-1 md:grid-cols-2 gap-2">
          <input v-model="form.email" required type="email" placeholder="email@exemple.com" class="px-3 py-2 text-sm bg-gray-50 dark:bg-zinc-900 border rounded-xl"/>
          <input v-model="form.display_name" placeholder="Nom affiché" class="px-3 py-2 text-sm bg-gray-50 dark:bg-zinc-900 border rounded-xl"/>
          <input v-model="form.company" placeholder="Société" class="px-3 py-2 text-sm bg-gray-50 dark:bg-zinc-900 border rounded-xl"/>
          <input v-model="form.job_title" placeholder="Fonction" class="px-3 py-2 text-sm bg-gray-50 dark:bg-zinc-900 border rounded-xl"/>
          <input v-model="form.phone" placeholder="Téléphone" class="px-3 py-2 text-sm bg-gray-50 dark:bg-zinc-900 border rounded-xl md:col-span-2"/>
          <button type="submit" class="md:col-span-2 px-4 py-2 bg-[#FF4D2E] text-white rounded-xl text-sm font-bold">Ajouter</button>
          <p v-if="errors.email" class="text-rose-600 text-xs md:col-span-2">{{ errors.email }}</p>
        </form>
      </section>

      <div class="text-xs text-gray-500">{{ contacts.length }} contact{{ contacts.length !== 1 ? 's' : '' }}</div>

      <div class="space-y-2">
        <div v-for="c in contacts" :key="c.id"
          class="bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl p-3 flex items-center gap-3">
          <img :src="`/webmail/avatar/${c.avatar_hash}?n=${encodeURIComponent(c.display_name || c.email)}`"
            class="w-10 h-10 rounded-full object-cover bg-gray-200" alt=""/>
          <div class="flex-1 min-w-0">
            <div class="font-semibold text-sm truncate">
              {{ c.display_name || c.email }}
              <span v-if="c.company" class="text-gray-500 font-normal">· {{ c.company }}</span>
            </div>
            <div class="text-xs text-gray-500 truncate">
              <a :href="`mailto:${c.email}`" class="font-mono hover:text-[#FF4D2E]">{{ c.email }}</a>
              <span v-if="c.job_title"> · {{ c.job_title }}</span>
              <span v-if="c.phone"> · {{ c.phone }}</span>
            </div>
          </div>
          <span v-if="c.source === 'member'" class="text-[10px] uppercase font-bold bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded">membre</span>
          <button v-if="can_edit && c.source !== 'member'" @click="remove(c)" class="text-xs text-gray-400 hover:text-rose-600">×</button>
        </div>
        <div v-if="!contacts.length" class="text-center text-gray-400 py-12 text-sm">Aucun contact.</div>
      </div>
    </main>
  </div>
</template>
<script setup>
import { reactive, ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
const props = defineProps({ contacts: Array, q: String, role: String, can_edit: Boolean });
const qDraft = ref(props.q || '');
const form = reactive({ email:'', display_name:'', company:'', job_title:'', phone:'' });
const flash = computed(() => usePage().props.flash);
const errors = computed(() => usePage().props.errors || {});
function search() { router.get('/contacts', { q: qDraft.value }, { preserveState: false }); }
function create() {
  router.post('/contacts', form, { preserveScroll: true, onSuccess: () => { Object.keys(form).forEach(k => form[k]=''); } });
}
function remove(c) {
  if (confirm(`Supprimer ${c.email} ?`)) router.delete(`/contacts/${c.id}`, { preserveScroll: true });
}
</script>
