<template>
  <label class="block">
    <span class="block text-[10px] uppercase tracking-wider text-gray-500">{{ label }}</span>
    <input
      class="w-full mt-1 px-3 py-2 text-sm bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-lg focus:border-[#FF4D2E] focus:outline-none"
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
