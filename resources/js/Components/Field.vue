<template>
  <label class="block">
    <span class="block text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold mb-1">{{ label }}</span>
    <input
      class="w-full px-3 py-2 text-sm bg-white dark:bg-zinc-800 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-zinc-600 rounded-lg focus:border-[#FF4D2E] focus:ring-2 focus:ring-[#FF4D2E]/20 focus:outline-none placeholder:text-gray-400 dark:placeholder:text-zinc-500"
      :type="type || 'text'"
      :placeholder="placeholder"
      :maxlength="maxlength"
      :value="modelValue"
      @input="$emit('update:modelValue', $event.target.value)"/>
  </label>
</template>
<script setup>
defineProps({
  label: String,
  modelValue: { type: [String, null], default: '' },
  placeholder: String,
  type: String,
  maxlength: [String, Number],
});
defineEmits(['update:modelValue']);
</script>
