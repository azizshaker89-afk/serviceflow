<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { http, apiError } from '@/lib/http'
import { useToastStore } from '@/stores/toast'
import { formatDate, formatDateTime, formatMoney } from '@/lib/format'
import AppDialog from '@/components/AppDialog.vue'
import StatusChip from '@/components/StatusChip.vue'

const props = defineProps({ id: { type: String, required: true } })
const toast = useToastStore()

const invoice = ref(null)
const loading = ref(true)
const error = ref(null)

async function load() {
  loading.value = true
  error.value = null
  try {
    const { data } = await http.get(`/invoices/${props.id}`)
    invoice.value = data.data
  } catch (e) {
    error.value = apiError(e)
  } finally {
    loading.value = false
  }
}
watch(() => props.id, load, { immediate: true })

const STATUS_TONE = { unpaid: 'waiting_parts', partially_paid: 'waiting_parts', paid: 'completed', cancelled: 'cancelled' }
const can = (action) => invoice.value?.allowed_actions.includes(action)
const paidShare = computed(() => (invoice.value ? Math.min(100, (Number(invoice.value.amount_paid) / Number(invoice.value.total)) * 100) : 0))

const METHODS = [
  { value: 'cash', label: 'نقدي' },
  { value: 'card', label: 'بطاقة' },
  { value: 'wallet', label: 'محفظة إلكترونية' },
  { value: 'bank_transfer', label: 'تحويل بنكي' },
]

const dialog = ref(null)
const form = reactive({})
const errors = ref({})
const message = ref('')
const busy = ref(false)

function open(kind) {
  Object.keys(form).forEach((k) => delete form[k])
  Object.assign(form, kind === 'pay' ? { amount: invoice.value.balance_due, method: 'cash', reference: '' } : { reason: '' })
  errors.value = {}
  message.value = ''
  dialog.value = kind
}

async function submit(url) {
  busy.value = true
  errors.value = {}
  message.value = ''
  try {
    const { data } = await http.post(url, form)
    invoice.value = data.data
    toast.show(data.message)
    dialog.value = null
  } catch (e) {
    const err = apiError(e)
    errors.value = err.errors
    message.value = err.status === 422 && Object.keys(err.errors).length ? '' : err.message
  } finally {
    busy.value = false
  }
}

const err = (f) => errors.value[f]?.[0]
const print = () => window.print()
</script>

<template>
  <RouterLink :to="{ name: 'invoices' }" class="back no-print">الفواتير</RouterLink>

  <div v-if="loading" class="panel panel-section mt-3"><div class="skeleton w-25 mb-3"></div><div class="skeleton w-75"></div></div>
  <div v-else-if="error" class="panel state mt-3"><h3>تعذر تحميل الفاتورة</h3><p>{{ error.message }}</p></div>

  <div v-else-if="invoice" class="layout">
    <article class="doc panel" :class="{ void: invoice.status === 'cancelled' }">
      <header class="doc-head">
        <div>
          <span class="doc-kind">فاتورة صيانة</span>
          <h1 class="num">{{ invoice.invoice_number }}</h1>
        </div>
        <StatusChip :status="STATUS_TONE[invoice.status]" :label="invoice.status_label" />
      </header>

      <dl class="doc-meta">
        <div><dt>العميل</dt><dd>{{ invoice.customer.name }}<span class="d-block num faint">{{ invoice.customer.phone }}</span></dd></div>
        <div><dt>الطلب</dt><dd><RouterLink :to="{ name: 'request-details', params: { id: invoice.service_request.id } }" class="num">{{ invoice.service_request.code }}</RouterLink><span class="d-block faint">{{ invoice.service_request.category }}</span></dd></div>
        <div><dt>تاريخ الإصدار</dt><dd class="num">{{ formatDate(invoice.issued_at) }}<span class="d-block faint">بواسطة {{ invoice.issued_by }}</span></dd></div>
        <div><dt>الاستحقاق</dt><dd class="num" :class="{ owed: invoice.is_overdue }">{{ formatDate(invoice.due_date) }}<span v-if="invoice.is_overdue" class="d-block">متأخرة السداد</span></dd></div>
      </dl>

      <div class="table-wrap">
        <table class="table-sf lines">
          <thead><tr><th>البند</th><th>النوع</th><th>الكمية</th><th>سعر الوحدة</th><th>الإجمالي</th></tr></thead>
          <tbody>
            <tr v-for="item in invoice.items" :key="item.id">
              <td>{{ item.description }}</td>
              <td class="faint">{{ item.type_label }}</td>
              <td class="num">{{ Number(item.quantity) }}{{ item.type === 'labor' && item.quantity !== '1.00' ? ' س' : '' }}</td>
              <td class="num">{{ formatMoney(item.unit_price) }}</td>
              <td class="num">{{ formatMoney(item.total) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <dl class="totals">
        <div><dt>قطع الغيار</dt><dd class="num">{{ formatMoney(invoice.parts_total) }}</dd></div>
        <div><dt>المصنعية</dt><dd class="num">{{ formatMoney(invoice.labor_total) }}</dd></div>
        <div v-if="Number(invoice.discount) > 0"><dt>الخصم</dt><dd class="num">− {{ formatMoney(invoice.discount) }}</dd></div>
        <div><dt>ضريبة القيمة المضافة <span class="num">{{ Number(invoice.tax_rate) }}%</span></dt><dd class="num">{{ formatMoney(invoice.tax_amount) }}</dd></div>
        <div class="grand"><dt>الإجمالي</dt><dd class="num">{{ formatMoney(invoice.total) }}</dd></div>
        <div><dt>المدفوع</dt><dd class="num">{{ formatMoney(invoice.amount_paid) }}</dd></div>
        <div class="due"><dt>المتبقي</dt><dd class="num">{{ formatMoney(invoice.balance_due) }}</dd></div>
      </dl>

      <p v-if="invoice.cancellation_reason" class="alert-sf late">أُلغيت في {{ formatDateTime(invoice.cancelled_at) }}: {{ invoice.cancellation_reason }}</p>
      <p v-if="invoice.notes" class="muted">{{ invoice.notes }}</p>
      <p class="doc-foot faint">الضريبة محسوبة بعد الخصم. أسعار القطع مثبتة لحظة صرفها، وساعات العمل بسعر الفني وقت تسجيلها.</p>
    </article>

    <aside class="side no-print">
      <div class="panel panel-section">
        <div class="section-head"><h2>التحصيل</h2></div>
        <div class="meter" role="img" :aria-label="`تم تحصيل ${Math.round(paidShare)}%`"><span :style="{ width: paidShare + '%' }"></span></div>
        <p class="muted mt-2">تم تحصيل <b class="num">{{ formatMoney(invoice.amount_paid) }}</b> من <span class="num">{{ formatMoney(invoice.total) }}</span></p>
        <div class="actions">
          <button v-if="can('record_payment')" type="button" class="btn-sf signal" @click="open('pay')">تسجيل دفعة</button>
          <button type="button" class="btn-sf" @click="print">طباعة الفاتورة</button>
          <button v-if="can('cancel')" type="button" class="btn-sf danger" @click="open('cancel')">إلغاء الفاتورة</button>
        </div>
        <p v-if="!can('record_payment') && invoice.status !== 'paid' && invoice.status !== 'cancelled'" class="faint small mt-2">التحصيل من صلاحيات المحاسب فقط.</p>
      </div>

      <div class="panel panel-section">
        <div class="section-head"><h2>الدفعات</h2></div>
        <ol v-if="invoice.payments.length" class="payments">
          <li v-for="p in invoice.payments" :key="p.id">
            <b class="num">{{ formatMoney(p.amount) }}</b>
            <span>{{ p.method_label }}<template v-if="p.reference"> · <span class="num">{{ p.reference }}</span></template></span>
            <span class="faint num">{{ formatDateTime(p.paid_at) }} · {{ p.received_by }}</span>
          </li>
        </ol>
        <p v-else class="faint">لم تُسجَّل دفعات بعد.</p>
      </div>
    </aside>

    <AppDialog :open="dialog === 'pay'" title="تسجيل دفعة" @close="dialog = null">
      <p class="muted">المتبقي على الفاتورة <b class="num">{{ formatMoney(invoice.balance_due) }}</b>. لا يُقبل مبلغ أكبر منه.</p>
      <div>
        <label class="form-label" for="pay-amount">المبلغ (ج.م)</label>
        <input id="pay-amount" v-model="form.amount" inputmode="decimal" class="form-control num" :class="{ 'is-invalid': err('amount') }" />
        <div class="invalid-feedback">{{ err('amount') }}</div>
      </div>
      <fieldset class="methods">
        <legend class="form-label">طريقة الدفع</legend>
        <label v-for="m in METHODS" :key="m.value" :class="{ on: form.method === m.value }">
          <input v-model="form.method" type="radio" name="method" :value="m.value" />{{ m.label }}
        </label>
      </fieldset>
      <div v-if="form.method !== 'cash'">
        <label class="form-label" for="pay-ref">رقم العملية</label>
        <input id="pay-ref" v-model.trim="form.reference" class="form-control num" placeholder="مثال: TX-48213" />
      </div>
      <p v-if="message" class="alert-sf late">{{ message }}</p>
      <template #footer>
        <button type="button" class="btn-sf signal" :disabled="busy" @click="submit(`/invoices/${invoice.id}/payments`)">تسجيل الدفعة</button>
        <button type="button" class="btn-sf quiet" @click="dialog = null">تراجع</button>
      </template>
    </AppDialog>

    <AppDialog :open="dialog === 'cancel'" title="إلغاء الفاتورة" @close="dialog = null">
      <p class="muted">الفاتورة الملغاة تبقى في السجل، ويمكن بعدها إصدار فاتورة جديدة مصححة لنفس الطلب.</p>
      <div>
        <label class="form-label" for="cx-reason">سبب الإلغاء</label>
        <input id="cx-reason" v-model.trim="form.reason" class="form-control" :class="{ 'is-invalid': err('reason') }" placeholder="مثال: نسيت الخصم المتفق عليه" />
        <div class="invalid-feedback">{{ err('reason') }}</div>
      </div>
      <p v-if="message" class="alert-sf late">{{ message }}</p>
      <template #footer>
        <button type="button" class="btn-sf danger" :disabled="busy" @click="submit(`/invoices/${invoice.id}/cancel`)">إلغاء الفاتورة</button>
        <button type="button" class="btn-sf quiet" @click="dialog = null">تراجع</button>
      </template>
    </AppDialog>
  </div>
</template>

<style scoped>
.back { font-size: var(--fs-sm); color: var(--ink-2); text-decoration: none; }
.back::before { content: "→ "; }
.layout { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 24px; align-items: start; margin-top: 14px; }
.doc { padding: 32px 36px; display: grid; gap: 24px; position: relative; }
.doc.void::after { content: "ملغاة"; position: absolute; inset: 0; display: grid; place-items: center; font-size: 5rem; font-weight: 700; color: var(--late); opacity: .12; transform: rotate(-14deg); pointer-events: none; }
.doc-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; padding-bottom: 18px; border-bottom: 2px solid var(--ink); }
.doc-kind { color: var(--ink-2); font-size: var(--fs-sm); }
.doc-head h1 { font-size: var(--fs-2xl); letter-spacing: .02em; }
.doc-meta { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin: 0; }
.doc-meta dt { font-size: var(--fs-xs); color: var(--ink-3); font-weight: 500; }
.doc-meta dd { margin: 2px 0 0; font-weight: 600; }
.lines td:nth-child(n+3), .lines th:nth-child(n+3) { text-align: end; }
.totals { margin: 0 0 0 auto; width: min(340px, 100%); margin-inline-start: auto; margin-inline-end: 0; display: grid; gap: 6px; }
.totals div { display: flex; justify-content: space-between; gap: 16px; color: var(--ink-2); }
.totals dd { margin: 0; }
.totals .grand { color: var(--ink); font-weight: 700; font-size: var(--fs-xl); border-top: 2px solid var(--ink); padding-top: 10px; margin-top: 4px; }
.totals .due { color: var(--ink); font-weight: 700; }
.owed { color: var(--late); }
.doc-foot { font-size: var(--fs-xs); border-top: 1px dashed var(--line); padding-top: 12px; }
.side { display: grid; gap: 16px; position: sticky; top: 20px; }
.meter { height: 8px; background: var(--paper); border-radius: 4px; overflow: hidden; box-shadow: inset 0 0 0 1px var(--line); }
.meter span { display: block; height: 100%; background: var(--done); transition: width .5s ease; }
.actions { display: grid; gap: 8px; margin-top: 14px; }
.btn-sf.danger { color: var(--late); }
.small { font-size: var(--fs-xs); }
.payments { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
.payments li { display: grid; gap: 1px; padding-bottom: 10px; border-bottom: 1px solid var(--line); font-size: var(--fs-sm); }
.payments li:last-child { border-bottom: 0; padding-bottom: 0; }
.payments b { font-size: var(--fs-md); }
.methods { display: flex; flex-wrap: wrap; gap: 6px; border: 0; padding: 0; margin: 0; }
.methods legend { width: 100%; }
.methods label { display: inline-flex; gap: 6px; align-items: center; padding: 7px 12px; border: 1px solid var(--line); border-radius: var(--r-sm); cursor: pointer; font-size: var(--fs-sm); }
.methods label.on { border-color: var(--ink); box-shadow: inset 0 0 0 1px var(--ink); }
.methods input { accent-color: var(--ink); }
@media (max-width: 960px) { .layout { grid-template-columns: minmax(0, 1fr); } .side { position: static; } .doc-meta { grid-template-columns: 1fr 1fr; } }
@media (max-width: 560px) { .doc { padding: 20px; } }
@media print {
  .no-print { display: none !important; }
  .layout { display: block; }
  .doc { border: 0; padding: 0; }
}
</style>
