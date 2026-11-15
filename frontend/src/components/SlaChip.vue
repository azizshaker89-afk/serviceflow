<script setup>
import { computed } from 'vue'
import { OPEN_STATUSES } from '@/lib/status'
import { slaState } from '@/lib/format'

const props = defineProps({ dueAt: { type: String, required: true }, status: { type: String, required: true } })
const sla = computed(() => (OPEN_STATUSES.includes(props.status) ? slaState(props.dueAt) : null))
</script>

<template>
  <span v-if="sla" class="chip" :class="{ late: sla.late }">{{ sla.text }}</span>
</template>
