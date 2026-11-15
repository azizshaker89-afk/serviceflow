<script setup>
import { computed } from 'vue'

/** Line icons for the service categories, matched by name. Unknown categories get a wrench. */
const props = defineProps({ name: { type: String, required: true } })

const PATHS = {
  ac: 'M3 6h18v7H3zM6 10h12M7 17l-1 3M12 17v3M17 17l1 3',
  install: 'M3 6h18v7H3zM12 13v8M9 18l3 3 3-3',
  electric: 'M13 2L4 14h7l-1 8 9-12h-7z',
  plumbing: 'M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11z',
  washer: 'M5 3h14v18H5zM5 7h14M12 15m-4 0a4 4 0 1 0 8 0a4 4 0 1 0-8 0',
  fridge: 'M6 2h12v20H6zM6 10h12M9 5v2M9 13v3',
  heater: 'M12 3c1 3 4 4.5 4 8a4 4 0 0 1-8 0c0-2 1-3 2-4 0 2 1 3 2 3 0-2-1-4 0-7zM7 21h10',
  inspect: 'M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14zM16 16l5 5',
  default: 'M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z',
}

const key = computed(() => {
  const n = props.name
  if (n.includes('تركيب')) return 'install'
  if (n.includes('تكييف')) return 'ac'
  if (n.includes('كهرباء')) return 'electric'
  if (n.includes('سباكة')) return 'plumbing'
  if (n.includes('غسال')) return 'washer'
  if (n.includes('ثلاج')) return 'fridge'
  if (n.includes('سخان')) return 'heater'
  if (n.includes('معاينة')) return 'inspect'
  return 'default'
})
</script>

<template>
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path :d="PATHS[key]" />
  </svg>
</template>
