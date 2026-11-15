<script setup>
import { STATUS_TONE } from '@/lib/status'

defineProps({ status: { type: String, required: true }, label: { type: String, required: true } })
</script>

<template>
  <span class="chip" :class="STATUS_TONE[status]"><span class="dot" aria-hidden="true"></span>{{ label }}</span>
</template>
