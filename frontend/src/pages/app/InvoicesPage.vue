<script setup>
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { http, apiError } from '@/lib/http'
import { useAuthStore } from '@/stores/auth'
import { formatDate, formatDateTime, formatMoney } from '@/lib/format'
import StatusChip from '@/components/StatusChip.vue'

const router = useRouter()
const auth = useAuthStore()

/* "awaiting" lists completed requests that still need an invoice: the accountant's inbox. */
const TABS = [
  { key: 'awaiting', label: 'بانتظار الإصدار' },
  { key: 'outstanding', label: 'مستحقة', params: { outstanding: 1 } },
  { key: 'paid', label: 'مدفوعة', params: { status: 'paid' } },
  { key: 'cancelled', label: 'ملغاة', params: { status: 'cancelled' } },
  { key: 'all', label: 'الكل', params: {} },
]
const STATUS_TONE = { unpaid: 'waiting_parts', partially_paid: 'waiting_parts', paid: 'completed', cancelled: 'cancelled' }

const tab = ref(auth.can('invoices.manage') ? 'awaiting' : 'outstanding')
const search = ref('')
const page = ref(1)
const items = ref([])
const meta = ref(null)
const summary = ref(null)
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    if (tab.value === 'awaiting') {
      const [{ data: reqs }, { data: inv }] = await Promise.all([
        http.get('/service-requests', { params: { awaiting_invoice: 1, sort: 'created_at', direction: 'asc', search: search.value || undefined, page: page.value } }),
        http.get('/invoices', { params: { per_page: 5 } }),
      ])
      items.value = reqs.data.items
      meta.value = reqs.data.meta
      summary.value = inv.data.summary
    } else {
      const params = { ...TABS.find((t) => t.key === tab.value).params, search: search.value || undefined, page: page.value }
      const { data } = await http.get('/invoices', { params })
      items.value = data.data.items
      meta.value = data.data.meta
      summary.value = data.data.summary
    }
  } catch (e) {
    error.value = apiError(e).message
  } finally {
    loading.value = false
  }
}

let timer
watch(search, () => { clearTimeout(timer); timer = setTimeout(() => { page.value = 1; load() }, 300) })
watch(tab, () => { page.value = 1; items.value = []; load() })
watch(page, load)
onMounted(load)
</script>

<template>
  <header class="page-head">
    <h1>الفواتير</h1>
    <p class="muted">حالة الفاتورة تُحسب من مجموع الدفعات، ولا تتغير يدويًا.</p>
  </header>

  <section v-if="summary" class="ledger panel" aria-label="ملخص">
    <button type="button" class="cell" :class="{ on: tab === 'awaiting' }" @click="tab = 'awaiting'">
      <span class="label">طلبات مكتملة بدون فاتورة</span>
      <b class="num">{{ summary.awaiting_invoice_count }}</b>
    </button>
    <button type="button" class="cell" :class="{ on: tab === 'outstanding' }" @click="tab = 'outstanding'">
      <span class="label">مبالغ لم تُحصَّل بعد</span>
      <b>{{ formatMoney(summary.outstanding_total) }}</b>
      <span class="faint">في <span class="num">{{ summary.outstanding_count }}</span> فاتورة</span>
    </button>
  </section>

  <div class="toolbar">
    <div class="tabs" role="tablist">
      <button v-for="t in TABS" :key="t.key" type="button" role="tab" :aria-selected="tab === t.key" :class="{ on: tab === t.key }" @click="tab = t.key">{{ t.label }}</button>
    </div>
    <label class="visually-hidden" for="inv-q">بحث</label>
    <input id="inv-q" v-model.trim="search" class="form-control" :placeholder="tab === 'awaiting' ? 'رقم الطلب أو اسم العميل' : 'رقم الفاتورة أو الطلب أو العميل'" />
  </div>

  <div class="panel">
    <div v-if="error" class="state"><h3>تعذر التحميل</h3><p>{{ error }}</p><button type="button" class="btn-sf" @click="load">إعادة المحاولة</button></div>

    <div v-else class="table-wrap">
      <table v-if="tab === 'awaiting'" class="table-sf">
        <thead><tr><th>الطلب</th><th>العميل</th><th>الخدمة</th><th>الفني</th><th><span class="visually-hidden">إجراء</span></th></tr></thead>
        <tbody v-if="loading"><tr v-for="n in 4" :key="n"><td v-for="c in 5" :key="c"><div class="skeleton"></div></td></tr></tbody>
        <tbody v-else-if="items.length">
          <tr v-for="r in items" :key="r.id">
            <td><span class="code num">{{ r.code }}</span></td>
            <td><b class="d-block">{{ r.customer.name }}</b><span class="faint num">{{ r.customer.phone }}</span></td>
            <td>{{ r.category.name }}</td>
            <td>{{ r.technician?.name ?? '—' }}</td>
            <td class="text-end"><RouterLink :to="{ name: 'request-details', params: { id: r.id } }" class="btn-sf mini" :class="{ signal: auth.can('invoices.manage') }">{{ auth.can('invoices.manage') ? 'مراجعة وإصدار' : 'عرض الطلب' }}</RouterLink></td>
          </tr>
        </tbody>
        <tbody v-else><tr><td colspan="5"><div class="state"><h3>كل الطلبات المكتملة لها فواتير</h3><p>أول ما فني ينهي طلب، هيظهر هنا.</p></div></td></tr></tbody>
      </table>

      <table v-else class="table-sf">
        <thead><tr><th>الفاتورة</th><th>العميل</th><th>الإجمالي</th><th>المتبقي</th><th>الحالة</th><th>الاستحقاق</th></tr></thead>
        <tbody v-if="loading"><tr v-for="n in 6" :key="n"><td v-for="c in 6" :key="c"><div class="skeleton"></div></td></tr></tbody>
        <tbody v-else-if="items.length">
          <tr v-for="i in items" :key="i.id" class="clickable" tabindex="0" @click="router.push({ name: 'invoice-details', params: { id: i.id } })" @keydown.enter="router.push({ name: 'invoice-details', params: { id: i.id } })">
            <td><span class="code num">{{ i.invoice_number }}</span><span class="d-block faint num">{{ i.service_request.code }} · {{ formatDateTime(i.issued_at) }}</span></td>
            <td>{{ i.customer.name }}</td>
            <td class="num">{{ formatMoney(i.total) }}</td>
            <td class="num" :class="{ owed: Number(i.balance_due) > 0 && i.status !== 'cancelled' }">{{ i.status === 'cancelled' ? '—' : formatMoney(i.balance_due) }}</td>
            <td><StatusChip :status="STATUS_TONE[i.status]" :label="i.status_label" /></td>
            <td><span class="num" :class="{ owed: i.is_overdue }">{{ formatDate(i.due_date) }}</span><span v-if="i.is_overdue" class="d-block late-text">متأخرة السداد</span></td>
          </tr>
        </tbody>
        <tbody v-else><tr><td colspan="6"><div class="state"><h3>مفيش فواتير هنا</h3></div></td></tr></tbody>
      </table>
    </div>

    <div v-if="meta && meta.last_page > 1" class="pager">
      <span class="faint">صفحة <span class="num">{{ meta.current_page }}</span> من <span class="num">{{ meta.last_page }}</span></span>
      <div class="d-flex gap-2">
        <button type="button" class="btn-sf" :disabled="page <= 1" @click="page--">السابق</button>
        <button type="button" class="btn-sf" :disabled="page >= meta.last_page" @click="page++">التالي</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page-head { margin-bottom: 18px; display: grid; gap: 4px; }
.page-head h1 { font-size: var(--fs-2xl); }
.ledger { display: grid; grid-template-columns: 1fr 1fr; margin-bottom: 18px; overflow: hidden; }
.cell { text-align: start; display: grid; gap: 2px; padding: 16px 20px; border: 0; background: transparent; color: var(--ink); }
.cell + .cell { border-inline-start: 1px solid var(--line); }
.cell.on { box-shadow: inset 0 -3px 0 var(--ink); }
.cell .label { font-size: var(--fs-sm); color: var(--ink-2); }
.cell b { font-size: var(--fs-xl); }
.toolbar { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
.toolbar .form-control { max-width: 320px; }
.tabs { display: inline-flex; background: var(--surface); border: 1px solid var(--line); border-radius: var(--r-sm); padding: 3px; flex-wrap: wrap; }
.tabs button { border: 0; background: transparent; padding: 6px 14px; border-radius: 4px; color: var(--ink-2); font-weight: 500; font-size: var(--fs-sm); }
.tabs button.on { background: var(--ink); color: var(--paper); }
.code { font-weight: 700; }
.owed { color: var(--late); font-weight: 600; }
.late-text { color: var(--late); font-size: var(--fs-xs); }
.mini { min-height: 34px; padding: 4px 12px; font-size: var(--fs-sm); }
.pager { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 16px; border-top: 1px solid var(--line); flex-wrap: wrap; }
@media (max-width: 560px) { .ledger { grid-template-columns: 1fr; } .cell + .cell { border-inline-start: 0; border-top: 1px solid var(--line); } }
</style>
