<script setup>
import { computed } from 'vue'
import { formatDateTime } from '@/lib/format'

/**
 * The stamped progress rail: the one bold element of the identity.
 * Billing is drawn as a dashed node because it is read from the invoice, not a request status.
 */
const props = defineProps({
  status: { type: String, required: true },
  /** [{ to_status, created_at, changed_by? }] oldest first or newest first; order does not matter. */
  history: { type: Array, default: () => [] },
  invoice: { type: Object, default: null },
  showNames: { type: Boolean, default: true },
})

const ORDER = ['new', 'assigned', 'in_progress', 'completed']

const latestFor = (statuses) =>
  [...props.history]
    .filter((h) => statuses.includes(h.to_status ?? h.status))
    .sort((a, b) => new Date(b.created_at ?? b.at) - new Date(a.created_at ?? a.at))[0] ?? null

const lastBeforeCancel = computed(() => {
  const sorted = [...props.history].sort((a, b) => new Date(a.created_at ?? a.at) - new Date(b.created_at ?? b.at))
  const prev = sorted.filter((h) => (h.to_status ?? h.status) !== 'cancelled').pop()
  const s = prev ? prev.to_status ?? prev.status : 'new'
  return ORDER.indexOf(s === 'waiting_parts' ? 'in_progress' : s)
})

const steps = computed(() => {
  const st = props.status
  const idx = st === 'waiting_parts' ? 2 : ORDER.indexOf(st)
  const base = [
    { key: 'new', label: 'استلام الطلب', match: ['new'] },
    { key: 'assigned', label: 'تعيين فني', match: ['assigned'] },
    { key: 'in_progress', label: st === 'waiting_parts' ? 'بانتظار قطع' : 'التنفيذ', match: ['in_progress', 'waiting_parts'] },
    { key: 'completed', label: 'تمت الصيانة', match: ['completed'] },
  ]
  return base.map((step, i) => {
    let state = 'todo'
    if (st === 'cancelled') {
      state = i < lastBeforeCancel.value ? 'done' : i === lastBeforeCancel.value ? 'cancelled' : 'todo'
    } else if (i < idx || (st === 'completed' && i === idx)) state = 'done'
    else if (i === idx) state = st === 'waiting_parts' ? 'waiting' : 'current'
    const stamp = state === 'cancelled' ? latestFor(['cancelled']) : state !== 'todo' ? latestFor(step.match) : null
    return { ...step, number: i + 1, state, stamp, label: state === 'cancelled' ? 'أُلغي هنا' : step.label }
  })
})

const invoiceState = computed(() => {
  if (!props.invoice) return 'todo'
  return props.invoice.status === 'paid' ? 'done' : 'current'
})

const fill = computed(() => {
  const st = props.status
  if (st === 'cancelled') return (Math.max(0, lastBeforeCancel.value) / 4) * 80
  if (invoiceState.value === 'done') return 80
  if (props.invoice) return 70
  const idx = st === 'waiting_parts' ? 2 : ORDER.indexOf(st)
  return (Math.max(0, idx) / 4) * 80
})
</script>

<template>
  <div class="rail-wrap">
    <ol class="rail" :class="{ cancelled: status === 'cancelled' }" aria-label="مراحل الطلب">
      <span class="rail-fill" :style="{ width: fill + '%' }" aria-hidden="true"></span>
      <li v-for="step in steps" :key="step.key" class="step" :class="step.state" :aria-current="['current', 'waiting'].includes(step.state) ? 'step' : undefined">
        <span class="node" aria-hidden="true">
          <svg v-if="step.state === 'done'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
          <svg v-else-if="step.state === 'cancelled'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18" /></svg>
          <svg v-else-if="step.state === 'waiting'" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="5" width="4" height="14" rx="1" /><rect x="14" y="5" width="4" height="14" rx="1" /></svg>
          <span v-else class="num">{{ step.number }}</span>
        </span>
        <span class="step-label">{{ step.label }}</span>
        <div v-if="step.stamp" class="stamp">
          <b v-if="showNames">{{ step.stamp.changed_by }}</b>
          <span class="num">{{ formatDateTime(step.stamp.created_at ?? step.stamp.at) }}</span>
        </div>
        <div v-else class="stamp empty">لم يحدث بعد</div>
      </li>
      <li class="step derived" :class="invoiceState">
        <span class="node" aria-hidden="true">
          <svg v-if="invoiceState === 'done'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
          <span v-else>₤</span>
        </span>
        <span class="step-label">{{ invoice ? invoice.status_label : 'الفاتورة' }}</span>
        <div v-if="invoice" class="stamp"><b class="num">{{ invoice.number }}</b></div>
        <div v-else class="stamp empty">تصدر بعد إتمام الصيانة</div>
      </li>
    </ol>
  </div>
</template>

<style scoped>
.rail-wrap { overflow-x: auto; padding-bottom: 6px; }
.rail { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(5, minmax(118px, 1fr)); position: relative; min-width: 620px; }
.rail::before, .rail-fill { content: ""; position: absolute; top: 21px; height: 6px; border-radius: 3px; inset-inline-start: 10%; }
.rail::before { inset-inline-end: 10%; background: repeating-linear-gradient(-45deg, var(--rail) 0 8px, transparent 8px 12px); }
.rail-fill { background: var(--ink); transition: width .7s cubic-bezier(.2, .8, .2, 1); }
.rail.cancelled .rail-fill { background: var(--late); }
.step { position: relative; z-index: 1; display: grid; justify-items: center; text-align: center; gap: 8px; }
.node { width: 48px; height: 48px; border-radius: 50%; display: grid; place-items: center; background: var(--paper); border: 2px solid var(--rail); color: var(--ink-3); font-weight: 700; }
.node svg { width: 22px; height: 22px; }
.step.done .node { background: var(--ink); border-color: var(--ink); color: var(--paper); }
.step.current .node { background: var(--signal); border-color: var(--signal); color: var(--signal-ink); box-shadow: 0 0 0 6px var(--signal-soft); }
.step.waiting .node { background: var(--wait); border-color: var(--wait); color: var(--surface); box-shadow: 0 0 0 6px var(--wait-soft); }
.step.cancelled .node { background: var(--late); border-color: var(--late); color: var(--surface); }
.step.derived .node { border-style: dashed; }
.step.derived.done .node { border-style: solid; background: var(--done); border-color: var(--done); color: var(--surface); }
.step.derived.current .node { border-style: solid; }
.step-label { font-weight: 600; }
.step.todo .step-label { color: var(--ink-3); }
.stamp { font-size: var(--fs-xs); color: var(--ink-2); line-height: 1.5; min-height: 2.6em; padding: 2px 8px; border-radius: 4px; border: 1px dashed var(--line); background: var(--surface); }
.stamp b { display: block; color: var(--ink); font-weight: 600; font-size: var(--fs-sm); }
.stamp.empty { border-color: transparent; background: transparent; color: var(--ink-3); }
.step.current .node, .step.waiting .node { animation: stamp .55s cubic-bezier(.2, 1.6, .4, 1) both; }
@keyframes stamp { from { transform: scale(1.45) rotate(-8deg); } to { transform: none; } }
</style>
