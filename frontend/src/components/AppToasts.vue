<script setup>
import { useToastStore } from '@/stores/toast'

const toasts = useToastStore()
</script>

<template>
  <div class="toasts" aria-live="polite">
    <TransitionGroup name="toast">
      <div v-for="t in toasts.items" :key="t.id" class="toast-sf" :class="t.tone" role="status" @click="toasts.dismiss(t.id)">
        {{ t.message }}
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.toasts { position: fixed; z-index: 1100; inset-inline-end: 16px; bottom: calc(16px + env(safe-area-inset-bottom, 0px)); display: grid; gap: 8px; max-width: min(360px, calc(100vw - 32px)); }
.toast-sf { background: var(--ink); color: var(--paper); padding: 11px 16px; border-radius: var(--r-sm); font-size: var(--fs-sm); font-weight: 500; cursor: pointer; }
.toast-sf.late { background: var(--late); color: #fff; }
.toast-enter-active, .toast-leave-active { transition: all .25s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(8px); }
</style>
