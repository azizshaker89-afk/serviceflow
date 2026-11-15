<script setup>
import { onMounted, ref, watch } from 'vue'

/**
 * Native <dialog>: focus trapping, Escape to close and the backdrop come from the browser.
 */
const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, required: true },
  width: { type: String, default: '460px' },
})
const emit = defineEmits(['close'])
const el = ref(null)

function sync(open) {
  if (!el.value) return
  if (open && !el.value.open) el.value.showModal()
  if (!open && el.value.open) el.value.close()
}
watch(() => props.open, sync)
onMounted(() => sync(props.open))
</script>

<template>
  <dialog ref="el" class="dlg" :style="{ width: `min(${width}, calc(100vw - 32px))` }" :aria-label="title" @close="emit('close')" @cancel.prevent="emit('close')">
    <div class="dlg-head">
      <h2>{{ title }}</h2>
      <button type="button" class="x" aria-label="إغلاق" @click="emit('close')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18" /></svg>
      </button>
    </div>
    <div class="dlg-body"><slot /></div>
    <div v-if="$slots.footer" class="dlg-foot"><slot name="footer" /></div>
  </dialog>
</template>

<style scoped>
.dlg { border: 1px solid var(--line); border-radius: var(--r-md); padding: 0; background: var(--surface); color: var(--ink); max-height: calc(100vh - 48px); }
.dlg::backdrop { background: color-mix(in srgb, var(--ink) 45%, transparent); }
.dlg-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 18px 22px 0; }
.dlg-head h2 { font-size: var(--fs-lg); }
.x { border: 0; background: transparent; color: var(--ink-3); width: 36px; height: 36px; border-radius: var(--r-sm); display: grid; place-items: center; }
.x:hover { background: var(--paper); color: var(--ink); }
.x svg { width: 18px; height: 18px; }
.dlg-body { padding: 16px 22px; display: grid; gap: 14px; }
.dlg-foot { padding: 0 22px 20px; display: flex; gap: 8px; flex-wrap: wrap; }
</style>
