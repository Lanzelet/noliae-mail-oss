<template>
  <div class="min-h-screen bg-gray-50 dark:bg-zinc-900 text-gray-900 dark:text-gray-100">
    <header class="bg-white dark:bg-zinc-800 border-b border-gray-200 dark:border-zinc-700 sticky top-0 z-10">
      <div class="max-w-5xl mx-auto px-5 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <svg viewBox="0 0 140 100" class="h-6"><rect x="12" y="35" width="10" height="30" rx="5" fill="currentColor"/><rect x="30" y="25" width="10" height="50" rx="5" fill="currentColor"/><rect x="48" y="15" width="10" height="70" rx="5" fill="currentColor"/><rect x="66" y="22" width="10" height="56" rx="5" fill="#FF4D2E"/><rect x="84" y="20" width="10" height="60" rx="5" fill="currentColor"/><rect x="102" y="30" width="10" height="40" rx="5" fill="currentColor"/><rect x="120" y="35" width="10" height="30" rx="5" fill="currentColor"/></svg>
          <span class="font-bold">Annuaire</span>
          <span class="text-xs text-gray-500">· {{ org?.name }}</span>
        </div>
        <div class="text-xs text-gray-500 flex items-center gap-4">
          <Link href="/contacts" class="text-gray-600 hover:text-[#FF4D2E]">📇 Carnet</Link>
          <Link href="/webmail" class="text-gray-600 hover:text-[#FF4D2E]">Webmail →</Link>
        </div>
      </div>
    </header>

    <main class="max-w-5xl mx-auto px-5 py-8 space-y-5">
      <form @submit.prevent="search" class="flex gap-2">
        <input v-model="qDraft" type="search" placeholder="Chercher par nom, email…"
          class="flex-1 px-4 py-2.5 bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl focus:outline-none focus:border-[#FF4D2E]"/>
        <button type="submit" class="px-4 py-2.5 bg-[#FF4D2E] text-white rounded-xl text-sm font-bold hover:bg-[#FF4D2E]/90">Chercher</button>
      </form>

      <div class="text-xs text-gray-500">{{ people.length }} personne{{ people.length !== 1 ? 's' : '' }}</div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <div v-for="p in people" :key="p.id"
          class="bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-2xl p-4 flex items-center gap-3 hover:border-[#FF4D2E]/40 transition">
          <img :src="`/webmail/avatar/${p.avatar_hash}?n=${encodeURIComponent(p.display_name || p.email)}`"
            class="w-12 h-12 rounded-full object-cover bg-gray-200 dark:bg-zinc-700" alt=""/>
          <div class="flex-1 min-w-0">
            <div class="font-semibold truncate">{{ p.display_name || '—' }}</div>
            <a :href="`mailto:${p.email}`" class="text-xs text-gray-500 font-mono truncate block hover:text-[#FF4D2E]">{{ p.email }}</a>
            <div class="flex gap-1 mt-1">
              <span v-if="p.role && p.role !== 'member'" :class="['text-[10px] uppercase font-bold px-1.5 py-0.5 rounded',
                p.role === 'owner' ? 'bg-purple-100 text-purple-700' :
                p.role === 'admin' ? 'bg-red-100 text-red-700' :
                'bg-blue-100 text-blue-700']">{{ p.role }}</span>
              <span v-if="p.email === me" class="text-[10px] uppercase font-bold bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded">moi</span>
            </div>
          </div>
        </div>
        <div v-if="!people.length" class="col-span-full text-center text-gray-400 py-12 text-sm">
          Aucun résultat.
        </div>
      </div>
    </main>
  </div>
</template>
<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
const props = defineProps({ org: Object, people: Array, q: String, me: String });
const qDraft = ref(props.q || '');
function search() {
  router.get('/people', { q: qDraft.value }, { preserveState: false, preserveScroll: false });
}
</script>
