<template>
  <div class="absolute z-50 left-0 right-0 mt-1 bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl shadow-2xl overflow-hidden">
    <div v-for="(it, i) in items" :key="it.email"
      @mousedown.prevent="$emit('pick', it)"
      @mouseenter="$emit('hover', i)"
      :class="['flex items-center gap-3 px-3 py-2 cursor-pointer transition',
               i === active ? 'bg-[#FF4D2E]/10' : 'hover:bg-gray-50 dark:hover:bg-zinc-900']">
      <img :src="`/webmail/avatar/${it.avatar_hash}?n=${encodeURIComponent(it.display_name || it.email)}`"
        class="w-8 h-8 rounded-full object-cover bg-gray-200 dark:bg-zinc-700 shrink-0" alt=""/>
      <div class="flex-1 min-w-0">
        <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
          {{ it.display_name || it.email }}
          <span v-if="it.company" class="text-gray-400 font-normal text-xs">· {{ it.company }}</span>
        </div>
        <div class="text-xs text-gray-500 font-mono truncate">{{ it.email }}</div>
      </div>
      <span v-if="it.source === 'member'" class="text-[9px] uppercase font-bold bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200 px-1.5 py-0.5 rounded">interne</span>
    </div>
    <div class="px-3 py-1.5 bg-gray-50 dark:bg-zinc-900 text-[10px] text-gray-400 border-t border-gray-100 dark:border-zinc-700">
      ↑↓ naviguer · Entrée valider · Échap fermer
    </div>
  </div>
</template>
<script setup>
defineProps({
  items:  { type: Array, required: true },
  active: { type: Number, default: 0 },
});
defineEmits(['pick', 'hover']);
</script>
