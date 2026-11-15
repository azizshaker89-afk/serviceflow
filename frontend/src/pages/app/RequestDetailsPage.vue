<script setup>
import { computed, ref, watch } from 'vue'
import { http, apiError } from '@/lib/http'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'
import { useToastStore } from '@/stores/toast'
import { formatDate, formatDateTime, formatMoney, initials } from '@/lib/format'
import StatusChip from '@/components/StatusChip.vue'
import SlaChip from '@/components/SlaChip.vue'
import StatusRail from '@/components/StatusRail.vue'
import RequestActions from '@/components/RequestActions.vue'
import AppDialog from '@/components/AppDialog.vue'

const props = defineProps({ id: { type: String, required: true } })
const auth = useAuthStore()
const router = useRouter()
const toast = useToastStore()

const request = ref(null)
const loading = ref(true)
const error = ref(null)

async function load() {
  loading.value = true
  error.value = null
  try {
    const { data } = await http.get(`/service-requests/${props.id}`)
    request.value = data.data
  } catch (e) {
    error.value = apiError(e)
  } finally {
    loading.value = false
  }
}
watch(() => props.id, load, { immediate: true })

/** Whose move it is, so nobody has to ask "who has this now?". */
const court = computed(() => {
  const r = request.value
  if (!r) return null
  if (r.status === 'new') return { who: 'مدير التشغيل', task: 'تعيين فني مناسب' }
  if (['assigned', 'in_progress', 'waiting_parts'].includes(r.status)) {
    const task = { assigned: 'استلام المهمة وبدء العمل', in_progress: 'تنفيذ الصيانة', waiting_parts: 'انتظار وصول القطع' }[r.status]
    return { who: r.technician?.name ?? 'الفني', task, isMe: r.technician?.id === auth.user?.id }
  }
  if (r.status === 'completed') return { who: 'المحاسب', task: 'إصدار الفاتورة' }
  return null
})

function onDeleted() {
  router.push(auth.homeRoute)
}

function onUpdated(fresh) {
  request.value = fresh
  if (auth.role === 'technician' && fresh.technician?.id !== auth.user?.id) {
    toast.show('الطلب لم يعد ضمن مهامك')
  }
}

const canManageParts = computed(() => request.value?.allowed_actions.includes('manage_parts'))
const returning = ref(null)
const returnBusy = ref(false)

async function returnPart() {
  returnBusy.value = true
  try {
    const { data } = await http.delete(`/service-requests/${request.value.id}/parts/${returning.value.id}`)
    request.value = data.data
    toast.show(data.message)
    returning.value = null
  } catch (e) {
    toast.show(apiError(e).message, 'late')
  } finally {
    returnBusy.value = false
  }
}
</script>

<template>
  <RouterLink :to="auth.homeRoute" class="back">رجوع</RouterLink>

  <div v-if="loading" class="panel panel-section mt-3"><div class="skeleton w-25 mb-3"></div><div class="skeleton w-75 mb-2"></div><div class="skeleton w-50"></div></div>

  <div v-else-if="error" class="panel state mt-3">
    <h3>{{ error.status === 404 ? 'الطلب غير موجود أو غير معيّن لك' : 'تعذر تحميل الطلب' }}</h3>
    <p>{{ error.status === 404 ? 'الفني يرى المهام المعيّنة له فقط. لو تتوقع أن الطلب لك، راجع مدير التشغيل.' : error.message }}</p>
    <RouterLink :to="auth.homeRoute" class="btn-sf">العودة للقائمة</RouterLink>
  </div>

  <template v-else-if="request">
    <header class="ticket">
      <div class="ticket-top">
        <span class="code num">{{ request.code }}</span>
        <StatusChip :status="request.status" :label="request.status_label" />
        <span class="chip" :class="{ signal: ['urgent', 'high'].includes(request.priority) }">أولوية {{ request.priority_label }}</span>
        <SlaChip :due-at="request.due_at" :status="request.status" />
        <span v-if="request.source === 'online'" class="chip">طلب أونلاين</span>
      </div>
      <h1>{{ request.category.name }}</h1>
      <div class="meta">
        <span><b>{{ request.customer.name }}</b></span>
        <span class="num phone">{{ request.customer.phone }}</span>
        <span>{{ request.address || request.customer.address }}</span>
        <span v-if="request.preferred_date">الموعد المفضل {{ formatDate(request.preferred_date) }}</span>
      </div>
    </header>

    <section class="panel rail-panel" aria-label="مراحل الطلب">
      <StatusRail :status="request.status" :history="request.history" />
    </section>

    <div class="grid">
      <div class="panel">
        <div class="panel-section">
          <div class="section-head"><h2>وصف العطل</h2><span class="sub">سجّله {{ request.created_by }}</span></div>
          <p class="desc">{{ request.description }}</p>
        </div>
        <div v-if="request.cancellation_reason" class="panel-section">
          <div class="section-head"><h2>سبب الإلغاء</h2></div>
          <p class="desc">{{ request.cancellation_reason }}</p>
        </div>
        <div class="panel-section">
          <div class="section-head"><h2>ساعات العمل</h2><span class="sub">بسعر ساعة الفني وقت التسجيل</span></div>
          <div class="table-wrap">
            <table class="table-sf">
              <thead><tr><th>الوقت</th><th>الفني</th><th>الساعات</th><th>ملاحظة</th></tr></thead>
              <tbody>
                <tr v-for="w in request.work_logs" :key="w.id">
                  <td class="num">{{ formatDateTime(w.logged_at) }}</td><td>{{ w.technician }}</td><td class="num">{{ Number(w.hours) }}</td><td>{{ w.note || '—' }}</td>
                </tr>
                <tr v-if="!request.work_logs.length"><td colspan="4" class="faint">لم تُسجَّل ساعات عمل بعد. التسجيل شرط لإنهاء الصيانة.</td></tr>
              </tbody>
              <tfoot v-if="request.work_logs.length"><tr><td colspan="2"><b>الإجمالي</b></td><td class="num"><b>{{ Number(request.totals.hours) }}</b></td><td></td></tr></tfoot>
            </table>
          </div>
        </div>
        <div class="panel-section">
          <div class="section-head"><h2>قطع الغيار المصروفة</h2><span class="sub">السعر يُثبَّت لحظة الصرف</span></div>
          <div class="table-wrap">
            <table class="table-sf">
              <thead><tr><th>القطعة</th><th>الكمية</th><th>سعر الوحدة</th><th>الإجمالي</th><th v-if="canManageParts"><span class="visually-hidden">إرجاع</span></th></tr></thead>
              <tbody>
                <tr v-for="p in request.parts" :key="p.id">
                  <td>{{ p.name }}<span class="d-block faint num">{{ p.sku }}</span></td><td class="num">{{ p.quantity }}</td><td>{{ formatMoney(p.unit_price) }}</td><td>{{ formatMoney(p.line_total) }}</td>
                  <td v-if="canManageParts" class="text-end"><button type="button" class="btn-sf quiet return-btn" @click="returning = p">إرجاع</button></td>
                </tr>
                <tr v-if="!request.parts.length"><td colspan="4" class="faint">{{ request.no_parts_confirmed ? 'أكّد الفني أن الصيانة لم تحتج قطع غيار.' : 'لم تُصرف قطع لهذا الطلب.' }}</td></tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="panel-section">
          <div class="section-head"><h2>سجل الطلب</h2><span class="sub">كل تغيير حالة ومن قام به</span></div>
          <ol class="history">
            <li v-for="h in request.history" :key="h.id">
              <time class="num faint">{{ formatDateTime(h.created_at) }}</time>
              <div>
                <b>{{ h.changed_by }}</b>
                <span class="faint"> · </span>
                <template v-if="h.from_status_label">{{ h.from_status_label }} ← </template>{{ h.to_status_label }}
                <div v-if="h.note" class="muted note">{{ h.note }}</div>
              </div>
            </li>
          </ol>
        </div>
      </div>

      <aside class="aside">
        <div class="panel panel-section">
          <div class="section-head"><h2>{{ court ? 'الطلب الآن عند' : 'الحالة' }}</h2></div>
          <div v-if="court" class="court">
            <span class="avatar" aria-hidden="true">{{ initials(court.isMe ? auth.user.name : court.who) }}</span>
            <div><b>{{ court.isMe ? 'أنت' : court.who }}</b><span class="muted">{{ court.task }}</span></div>
          </div>
          <p v-else class="muted">{{ request.status === 'cancelled' ? 'الطلب ملغي ولا توجد خطوات متبقية.' : 'لا توجد خطوات متبقية.' }}</p>

          <RequestActions :request="request" @updated="onUpdated" @deleted="onDeleted" />
          <AppDialog :open="!!returning" title="إرجاع قطعة للمخزن" @close="returning = null">
            <p v-if="returning">سيُعاد <b class="num">{{ returning.quantity }}</b> × {{ returning.name }} للمخزن، ويُحذف السطر من الطلب. حركة الإرجاع تظهر في سجل المخزن.</p>
            <template #footer>
              <button type="button" class="btn-sf ink" :disabled="returnBusy" @click="returnPart">إرجاع للمخزن</button>
              <button type="button" class="btn-sf quiet" @click="returning = null">تراجع</button>
            </template>
          </AppDialog>
        </div>
        <div class="panel panel-section facts">
          <div><span class="faint">الفني</span><b>{{ request.technician?.name ?? 'لم يُعيَّن بعد' }}</b></div>
          <div><span class="faint">تاريخ الاستلام</span><b class="num">{{ formatDateTime(request.created_at) }}</b></div>
          <div><span class="faint">الموعد النهائي</span><b class="num">{{ formatDateTime(request.due_at) }}</b></div>
          <div v-if="request.invoice && auth.can('invoices.view')">
            <span class="faint">الفاتورة</span>
            <RouterLink :to="{ name: 'invoice-details', params: { id: request.invoice.id } }" class="num"><b>{{ request.invoice.number }}</b> · {{ request.invoice.status_label }}</RouterLink>
          </div>
        </div>
      </aside>
    </div>
  </template>
</template>

<style scoped>
.return-btn { min-height: 32px; padding: 2px 10px; font-size: var(--fs-sm); }
.back { font-size: var(--fs-sm); color: var(--ink-2); text-decoration: none; }
.back::before { content: "→ "; }
.ticket { display: grid; gap: 10px; padding-block: 16px 20px; }
.ticket-top { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.code { font-size: var(--fs-lg); font-weight: 700; margin-inline-end: 4px; }
.ticket h1 { font-size: var(--fs-2xl); }
.meta { display: flex; flex-wrap: wrap; gap: 4px 22px; color: var(--ink-2); }
.phone { user-select: all; }
.rail-panel { padding: 22px 16px 14px; }
.grid { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 24px; align-items: start; margin-top: 20px; }
.desc { max-width: 65ch; white-space: pre-line; }
.history { list-style: none; margin: 0; padding: 0; }
.history li { display: grid; grid-template-columns: 120px minmax(0, 1fr); gap: 12px; padding-block: 10px; border-bottom: 1px solid var(--line); font-size: var(--fs-sm); }
.history li:last-child { border-bottom: 0; }
.note { margin-top: 2px; }
.aside { display: grid; gap: 16px; position: sticky; top: 20px; }
.court { display: flex; gap: 10px; align-items: center; }
.court div { display: grid; line-height: 1.4; }
.avatar { width: 38px; height: 38px; border-radius: 50%; background: var(--signal-soft); color: var(--signal); display: grid; place-items: center; font-weight: 700; flex: none; }
.facts { display: grid; gap: 10px; }
.facts div { display: flex; justify-content: space-between; gap: 12px; font-size: var(--fs-sm); }
@media (max-width: 960px) { .grid { grid-template-columns: minmax(0, 1fr); } .aside { position: static; } }
@media (max-width: 560px) { .history li { grid-template-columns: minmax(0, 1fr); gap: 2px; } }
</style>
