<script setup>
import { reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { http, apiError } from '@/lib/http'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { formatDate, formatMoney } from '@/lib/format'
import AppDialog from '@/components/AppDialog.vue'
import StatusChip from '@/components/StatusChip.vue'

const props = defineProps({ id: { type: String, required: true } })
const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()

const data = ref(null)
const loading = ref(true)
const error = ref(null)

async function load() {
  loading.value = true
  error.value = null
  try {
    const res = await http.get(`/customers/${props.id}`)
    data.value = res.data.data
  } catch (e) {
    error.value = apiError(e)
  } finally {
    loading.value = false
  }
}
watch(() => props.id, load, { immediate: true })

const INVOICE_TONE = { unpaid: 'waiting_parts', partially_paid: 'waiting_parts', paid: 'completed', cancelled: 'cancelled' }

const dialog = ref(null)
const form = reactive({})
const errors = ref({})
const message = ref('')
const busy = ref(false)

function openEdit() {
  const c = data.value.customer
  Object.assign(form, { name: c.name, phone: c.phone, city: c.city ?? '', address: c.address ?? '', email: c.email ?? '', notes: c.notes ?? '' })
  errors.value = {}
  message.value = ''
  dialog.value = 'edit'
}

async function saveEdit() {
  busy.value = true
  errors.value = {}
  try {
    const payload = Object.fromEntries(Object.entries(form).map(([k, v]) => [k, v === '' ? null : v]))
    const res = await http.put(`/customers/${props.id}`, payload)
    toast.show(res.data.message)
    dialog.value = null
    await load()
  } catch (e) {
    const err = apiError(e)
    errors.value = err.errors
    message.value = err.status === 422 && Object.keys(err.errors).length ? '' : err.message
  } finally {
    busy.value = false
  }
}

async function remove() {
  busy.value = true
  message.value = ''
  try {
    const res = await http.delete(`/customers/${props.id}`)
    toast.show(res.data.message)
    router.push({ name: 'customers' })
  } catch (e) {
    message.value = apiError(e).message
  } finally {
    busy.value = false
  }
}
const err = (f) => errors.value[f]?.[0]
</script>

<template>
  <RouterLink :to="{ name: 'customers' }" class="back">العملاء</RouterLink>

  <div v-if="loading" class="panel panel-section mt-3"><div class="skeleton w-25 mb-2"></div><div class="skeleton w-50"></div></div>
  <div v-else-if="error" class="panel state mt-3"><h3>تعذر تحميل العميل</h3><p>{{ error.message }}</p></div>

  <template v-else-if="data">
    <header class="head">
      <div>
        <h1>{{ data.customer.name }}</h1>
        <p class="meta">
          <span class="num phone">{{ data.customer.phone }}</span>
          <span v-if="data.customer.city">{{ data.customer.city }}</span>
          <span v-if="data.customer.address">{{ data.customer.address }}</span>
          <span v-if="data.customer.email" class="num">{{ data.customer.email }}</span>
        </p>
      </div>
      <div v-if="auth.can('customers.manage')" class="d-flex gap-2">
        <button type="button" class="btn-sf" @click="openEdit">تعديل البيانات</button>
        <button type="button" class="btn-sf quiet danger" @click="dialog = 'delete'; message = ''">حذف</button>
      </div>
    </header>

    <dl class="facts panel">
      <div><dt>الطلبات</dt><dd class="num">{{ data.customer.service_requests_count }}</dd></div>
      <div v-if="data.balance_due !== null"><dt>عليه حاليًا</dt><dd :class="{ owed: Number(data.balance_due) > 0 }">{{ formatMoney(data.balance_due) }}</dd></div>
      <div><dt>عميل منذ</dt><dd class="num">{{ formatDate(data.customer.created_at) }}</dd></div>
    </dl>
    <p v-if="data.customer.notes" class="notes panel panel-section">{{ data.customer.notes }}</p>

    <div class="cols">
      <section class="panel">
        <div class="panel-section"><div class="section-head"><h2>الطلبات</h2></div>
          <table class="table-sf">
            <thead><tr><th>الطلب</th><th>الخدمة</th><th>الحالة</th><th>التاريخ</th></tr></thead>
            <tbody>
              <tr v-for="r in data.requests" :key="r.id" class="clickable" @click="router.push({ name: 'request-details', params: { id: r.id } })">
                <td class="num"><b>{{ r.code }}</b></td><td>{{ r.category.name }}</td><td><StatusChip :status="r.status" :label="r.status_label" /></td><td class="num">{{ formatDate(r.created_at) }}</td>
              </tr>
              <tr v-if="!data.requests.length"><td colspan="4" class="faint">لا توجد طلبات.</td></tr>
            </tbody>
          </table>
        </div>
      </section>
      <section v-if="data.invoices" class="panel">
        <div class="panel-section"><div class="section-head"><h2>الفواتير</h2></div>
          <table class="table-sf">
            <thead><tr><th>الفاتورة</th><th>الإجمالي</th><th>الحالة</th></tr></thead>
            <tbody>
              <tr v-for="i in data.invoices" :key="i.id" class="clickable" @click="router.push({ name: 'invoice-details', params: { id: i.id } })">
                <td class="num"><b>{{ i.invoice_number }}</b></td><td class="num">{{ formatMoney(i.total) }}</td><td><StatusChip :status="INVOICE_TONE[i.status]" :label="i.status_label" /></td>
              </tr>
              <tr v-if="!data.invoices.length"><td colspan="3" class="faint">لا توجد فواتير.</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <AppDialog :open="dialog === 'edit'" title="تعديل بيانات العميل" width="520px" @close="dialog = null">
      <div class="grid2">
        <div><label class="form-label" for="ce-n">الاسم</label><input id="ce-n" v-model.trim="form.name" class="form-control" :class="{ 'is-invalid': err('name') }" /><div class="invalid-feedback">{{ err('name') }}</div></div>
        <div><label class="form-label" for="ce-p">الموبايل</label><input id="ce-p" v-model.trim="form.phone" class="form-control num" :class="{ 'is-invalid': err('phone') }" /><div class="invalid-feedback">{{ err('phone') }}</div></div>
        <div><label class="form-label" for="ce-c">المدينة</label><input id="ce-c" v-model.trim="form.city" class="form-control" /></div>
        <div><label class="form-label" for="ce-e">البريد</label><input id="ce-e" v-model.trim="form.email" class="form-control num" :class="{ 'is-invalid': err('email') }" /></div>
        <div class="full"><label class="form-label" for="ce-a">العنوان</label><input id="ce-a" v-model.trim="form.address" class="form-control" /></div>
        <div class="full"><label class="form-label" for="ce-no">ملاحظات داخلية</label><textarea id="ce-no" v-model.trim="form.notes" rows="2" class="form-control"></textarea></div>
      </div>
      <p v-if="message" class="alert-sf late">{{ message }}</p>
      <template #footer>
        <button type="button" class="btn-sf ink" :disabled="busy" @click="saveEdit">حفظ التعديلات</button>
        <button type="button" class="btn-sf quiet" @click="dialog = null">تراجع</button>
      </template>
    </AppDialog>

    <AppDialog :open="dialog === 'delete'" title="حذف العميل" @close="dialog = null">
      <p>طلبات وفواتير العميل السابقة تفضل محفوظة. الحذف مرفوض لو عنده طلب مفتوح أو فاتورة لم تُسدد.</p>
      <p v-if="message" class="alert-sf late">{{ message }}</p>
      <template #footer>
        <button type="button" class="btn-sf danger" :disabled="busy" @click="remove">حذف العميل</button>
        <button type="button" class="btn-sf quiet" @click="dialog = null">تراجع</button>
      </template>
    </AppDialog>
  </template>
</template>

<style scoped>
.back { font-size: var(--fs-sm); color: var(--ink-2); text-decoration: none; }
.back::before { content: "→ "; }
.head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin: 14px 0 18px; }
.head h1 { font-size: var(--fs-2xl); }
.meta { display: flex; flex-wrap: wrap; gap: 4px 18px; color: var(--ink-2); margin-top: 4px; }
.phone { user-select: all; }
.facts { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); margin: 0 0 16px; }
.facts div { padding: 14px 20px; }
.facts div + div { border-inline-start: 1px solid var(--line); }
.facts dt { font-size: var(--fs-sm); color: var(--ink-2); font-weight: 500; }
.facts dd { margin: 0; font-size: var(--fs-xl); font-weight: 700; }
.owed { color: var(--late); }
.notes { margin-bottom: 16px; color: var(--ink-2); }
.cols { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); gap: 20px; align-items: start; }
.danger { color: var(--late); }
.btn-sf.danger { color: var(--late); }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.full { grid-column: 1 / -1; }
@media (max-width: 960px) { .cols { grid-template-columns: minmax(0, 1fr); } }
@media (max-width: 520px) { .grid2, .facts { grid-template-columns: 1fr; } .facts div + div { border-inline-start: 0; border-top: 1px solid var(--line); } }
</style>
