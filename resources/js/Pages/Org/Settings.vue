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

    <!-- ═════ Branding : logo + footer login ═════ -->
    <section class="bg-white dark:bg-zinc-800 rounded-2xl border border-gray-200 dark:border-zinc-700 p-5 mb-5">
      <h2 class="font-bold mb-1 flex items-center gap-2">
        <span class="w-7 h-7 rounded-lg bg-indigo-500/15 text-indigo-600 flex items-center justify-center text-sm">🎨</span>
        Branding pages de connexion
      </h2>
      <p class="text-[11px] text-gray-500 mb-4">Affichés sur <code>/login</code> et <code>/admin/login</code>. Owner uniquement.</p>

      <div class="flex items-start gap-5 mb-5">
        <div class="flex flex-col items-center gap-2">
          <div class="w-28 h-28 rounded-xl bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 flex items-center justify-center overflow-hidden">
            <img v-if="logo_url" :src="logo_url" alt="Logo orga" class="max-w-full max-h-full object-contain"/>
            <span v-else class="text-gray-400 text-xs">Aucun logo</span>
          </div>
          <div v-if="role === 'owner'" class="flex items-center gap-1">
            <label class="px-2 py-1 text-[11px] font-semibold bg-[#FF4D2E]/10 text-[#FF4D2E] rounded-lg cursor-pointer hover:bg-[#FF4D2E]/20">
              Changer
              <input ref="logoInput" type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="hidden" @change="uploadLogo"/>
            </label>
            <button v-if="logo_url" type="button" @click="deleteLogo" class="px-2 py-1 text-[11px] text-gray-500 hover:text-rose-600">Retirer</button>
          </div>
          <p v-if="errors.logo" class="text-rose-600 text-[10px] text-center max-w-[120px]">{{ errors.logo }}</p>
        </div>
        <div class="flex-1 text-sm space-y-1">
          <p class="font-semibold">Logo de l'organisation</p>
          <p class="text-[11px] text-gray-500">PNG, JPG, WebP ou SVG · max 1 Mo · idéalement 200×80px transparent.<br/>Affiché juste au-dessus du formulaire de connexion.</p>
        </div>
      </div>

      <form @submit.prevent="saveBranding" class="grid grid-cols-1 md:grid-cols-3 gap-3 pt-4 border-t border-gray-100 dark:border-zinc-700">
        <label class="block md:col-span-3">
          <span class="block text-[10px] uppercase tracking-wider text-gray-500">Phrase d'accroche (footer)</span>
          <input v-model="brandingForm.footer_tagline" :disabled="role !== 'owner'" type="text" maxlength="120"
            placeholder="Messagerie souveraine, open source"
            class="w-full mt-1 px-3 py-2 text-sm bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-lg"/>
        </label>
        <label class="block">
          <span class="block text-[10px] uppercase tracking-wider text-gray-500">Lien — texte affiché</span>
          <input v-model="brandingForm.footer_label" :disabled="role !== 'owner'" type="text" maxlength="80"
            placeholder="Aide / Mentions légales"
            class="w-full mt-1 px-3 py-2 text-sm bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-lg"/>
        </label>
        <label class="block md:col-span-2">
          <span class="block text-[10px] uppercase tracking-wider text-gray-500">Lien — URL cible</span>
          <input v-model="brandingForm.footer_url" :disabled="role !== 'owner'" type="url" maxlength="255"
            placeholder="https://exemple.com/aide"
            class="w-full mt-1 px-3 py-2 text-sm bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-lg font-mono"/>
        </label>
        <p class="text-[11px] text-gray-500 md:col-span-3">
          Laisse vide pour ne rien afficher. Pour retirer complètement le lien Github par défaut : mets <code>label</code> et <code>url</code> à vide.
        </p>
        <button v-if="role === 'owner'" type="submit" class="md:col-span-3 px-4 py-2 bg-[#FF4D2E] text-white rounded-lg text-sm font-bold">
          💾 Sauvegarder le branding
        </button>
      </form>
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
import { reactive, ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '../Admin/Layout.vue';
const props = defineProps({
  org: Object, role: String, domains: Array, stats: Object,
  logo_url: String, branding: Object,
});
const form = reactive({ name: props.org?.name || '', slug: props.org?.slug || '' });
const brandingForm = reactive({
  footer_label:   props.branding?.footer_label   || '',
  footer_url:     props.branding?.footer_url     || '',
  footer_tagline: props.branding?.footer_tagline || '',
});
const logoInput = ref(null);
const flash = computed(() => usePage().props.flash);
const errors = computed(() => usePage().props.errors || {});
function save() { router.post('/org', form, { preserveScroll: true }); }
function saveBranding() { router.post('/org/branding', brandingForm, { preserveScroll: true }); }
function uploadLogo(e) {
  const f = e.target.files?.[0]; if (!f) return;
  const fd = new FormData(); fd.append('logo', f);
  router.post('/org/logo', fd, { preserveScroll: true, forceFormData: true,
    onFinish: () => { if (logoInput.value) logoInput.value.value = ''; }});
}
function deleteLogo() {
  if (confirm('Retirer le logo de l\'organisation ?')) router.delete('/org/logo', { preserveScroll: true });
}
</script>
