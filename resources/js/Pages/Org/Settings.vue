<template>
  <AdminLayout title="Organisation">
    <div v-if="flash?.success" class="mb-4 px-4 py-2.5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">{{ flash.success }}</div>

    <section class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 p-5 mb-5">
      <h2 class="font-bold mb-1 flex items-center gap-2">
        <span class="w-7 h-7 rounded-lg bg-[#FF4D2E]/15 text-[#FF4D2E] flex items-center justify-center text-sm">🏢</span>
        Paramètres
      </h2>
      <p class="text-[11px] text-gray-500 mb-3">Ton rôle : <strong class="uppercase">{{ role }}</strong>. Seul un <em>owner</em> peut modifier ces infos.</p>
      <form @submit.prevent="save" class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="text-[10px] uppercase tracking-wider text-gray-500">Nom</label>
          <input v-model="form.name" :disabled="role !== 'owner'" type="text" required maxlength="120"
            class="w-full px-3 py-2 text-sm bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl"/>
        </div>
        <div>
          <label class="text-[10px] uppercase tracking-wider text-gray-500">Slug (URL identifiant)</label>
          <input v-model="form.slug" :disabled="role !== 'owner'" type="text" required maxlength="64" pattern="^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$"
            class="w-full px-3 py-2 text-sm bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl font-mono"/>
        </div>
        <p v-if="errors.slug" class="text-rose-600 text-xs md:col-span-2">{{ errors.slug }}</p>
        <button v-if="role === 'owner'" type="submit" class="md:col-span-2 px-4 py-2 bg-[#FF4D2E] text-white rounded-xl text-sm font-bold">Sauvegarder</button>
      </form>
    </section>

    <section class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 p-5 mb-5">
      <h2 class="font-bold mb-3">📊 Stats</h2>
      <div class="grid grid-cols-3 gap-3 text-center">
        <div class="p-3 rounded-xl bg-gray-50 dark:bg-zinc-900">
          <div class="text-2xl font-bold">{{ stats.members }}</div>
          <div class="text-[11px] text-gray-500 uppercase">Membres</div>
        </div>
        <div class="p-3 rounded-xl bg-gray-50 dark:bg-zinc-900">
          <div class="text-2xl font-bold">{{ stats.domains }}</div>
          <div class="text-[11px] text-gray-500 uppercase">Domaines</div>
        </div>
        <div class="p-3 rounded-xl bg-gray-50 dark:bg-zinc-900">
          <div class="text-2xl font-bold">{{ stats.accounts }}</div>
          <div class="text-[11px] text-gray-500 uppercase">Comptes</div>
        </div>
      </div>
    </section>

    <section class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 p-5">
      <div class="flex items-center justify-between mb-3">
        <h2 class="font-bold">🌐 Domaines</h2>
        <Link href="/admin/domains" class="text-xs text-[#FF4D2E] hover:underline">Gérer →</Link>
      </div>
      <div class="space-y-1.5">
        <div v-for="d in domains" :key="d.id" class="flex items-center justify-between px-3 py-2 rounded-lg bg-gray-50 dark:bg-zinc-900">
          <code class="text-sm font-mono">{{ d.name }}</code>
          <span :class="['text-[10px] uppercase font-bold px-1.5 py-0.5 rounded',
            d.active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-600']">
            {{ d.active ? 'actif' : 'suspendu' }}
          </span>
        </div>
        <p v-if="!domains.length" class="text-xs text-gray-400 text-center py-3">Aucun domaine.</p>
      </div>
    </section>
  </AdminLayout>
</template>
<script setup>
import { reactive, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '../Admin/Layout.vue';
const props = defineProps({ org: Object, role: String, domains: Array, stats: Object });
const form = reactive({ name: props.org?.name || '', slug: props.org?.slug || '' });
const flash = computed(() => usePage().props.flash);
const errors = computed(() => usePage().props.errors || {});
function save() { router.post('/org', form, { preserveScroll: true }); }
</script>
