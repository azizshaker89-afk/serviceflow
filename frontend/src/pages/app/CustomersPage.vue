<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { http, apiError } from '@/lib/http'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { formatDate, formatMoney } from '@/lib/format'
import AppDialog from '@/components/AppDialog.vue'

const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()

const search = ref('')
const page = ref(1)
const items = ref([])
const meta = ref(null)
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await http.get('/customers', { params: { search: search.value || undefined, page: page.value } })
    items.value = data.data.items
    meta.value = data.data.meta
  } catch (e) {
    error.value = apiError(e).message
  } finally {
    loading.value = false
  }
}
let timer
watch(search, () => { clearTimeout(timer); timer = setTimeout(() => { page.value = 1; load() }, 300) })
watch(page, load)
onMounted(load)

const dialog = ref(false)
const form = reactive({ name: '', phone: '', city: '', address: '', email: '', notes: '' })
const errors = ref({})
const busy = ref(false)

function openCreate() {
  Object.assign(form, { name: '', phone: '', city: '', address: '', email: '', notes: '' })
  errors.value = {}
  dialog.value = true
}

async function save() {
  busy.value = true
  errors.value = {}
  try {
    const payload = Object.fromEntries(Object.entries(form).filter(([, v]) => v !== ''))
    const { data } = await http.post('/customers', payload)
    toast.show(data.message)
    dialog.value = false
    router.push({ name: 'customer-details', params: { id: data.data.id } })
  } catch (e) {
    const err = apiError(e)
    errors.value = err.errors
    if (err.status !== 422) toast.show(err.message, 'late')
  } finally {
    busy.value = false
  }
}
const err = (f) => errors.value[f]?.[0]
const open = (c) => router.push({ name: 'customer-details', params: { id: c.id } })
</script>

<template>
  <header class="page-head">
    <div>
      <h1>العملاء</h1>
      <p class="muted">رقم الموبايل هو هوية العميل: الطلبات الأونلاين بنفس الرقم بتتربط بنفس العميل.</p>
    </div>
    <button v-if="auth.can('customers.manage')" type="button" class="btn-sf signal" @click="openCreate">عميل جديد</button>
  </header>

  <div class="toolbar">
    <label class="visually-hidden" for="cu-q">بحث</label>
    <input id="cu-q" v-model.trim="search" class="form-control" placeholder="ابحث بالاسم أو أول أرقام الموبايل" />
    <span v-if="meta" class="faint"><span class="num">{{ meta.total }}</span> عميل</span>
  </div>

  <div class="panel">
    <div v-if="error" class="state"><h3>تعذر تحميل العملاء</h3><p>{{ error }}</p></div>
    <div v-else class="table-wrap">
      <table class="table-sf">
        <thead><tr><th>العميل</th><th>المدينة</th><th>الطلبات</th><th v-if="auth.can('invoices.view')">إجمالي الفواتير</th><th>آخر طلب</th></tr></thead>
        <tbody v-if="loading"><tr v-for="n in 8" :key="n"><td v-for="c in 5" :key="c"><div class="skeleton"></div></td></tr></tbody>
        <tbody v-else-if="items.length">
          <tr v-for="c in items" :key="c.id" class="clickable" tabindex="0" @click="open(c)" @keydown.enter="open(c)">
            <td><b class="d-block">{{ c.name }}</b><span class="faint num">{{ c.phone }}</span></td>
            <td>{{ c.city || '—' }}</td>
            <td class="num">{{ c.service_requests_count }}</td>
            <td v-if="auth.can('invoices.view')" class="num">{{ formatMoney(c.billed_total) }}</td>
            <td class="num">{{ c.last_request_at ? formatDate(c.last_request_at) : '—' }}</td>
          </tr>
        </tbody>
        <tbody v-else><tr><td colspan="5"><div class="state"><h3>مفيش عميل بالبيانات دي</h3></div></td></tr></tbody>
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

  <AppDialog :open="dialog" title="عميل جديد" width="520px" @close="dialog = false">
    <div class="grid2">
      <div><label class="form-label" for="nc-n">الاسم</label><input id="nc-n" v-model.trim="form.name" class="form-control" :class="{ 'is-invalid': err('name') }" /><div class="invalid-feedback">{{ err('name') }}</div></div>
      <div><label class="form-label" for="nc-p">الموبايل</label><input id="nc-p" v-model.trim="form.phone" class="form-control num" inputmode="tel" :class="{ 'is-invalid': err('phone') }" /><div class="invalid-feedback">{{ err('phone') }}</div></div>
      <div><label class="form-label" for="nc-c">المدينة</label><input id="nc-c" v-model.trim="form.city" class="form-control" /></div>
      <div><label class="form-label" for="nc-e">البريد الإلكتروني</label><input id="nc-e" v-model.trim="form.email" type="email" class="form-control num" :class="{ 'is-invalid': err('email') }" /><div class="invalid-feedback">{{ err('email') }}</div></div>
      <div class="full"><label class="form-label" for="nc-a">العنوان</label><input id="nc-a" v-model.trim="form.address" class="form-control" /></div>
    </div>
    <template #footer>
      <button type="button" class="btn-sf ink" :disabled="busy" @click="save">إضافة العميل</button>
      <button type="button" class="btn-sf quiet" @click="dialog = false">تراجع</button>
    </template>
  </AppDialog>
</template>

<style scoped>
.page-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; }
.page-head h1 { font-size: var(--fs-2xl); }
.toolbar { display: flex; align-items: center; gap: 14px; margin-bottom: 14px; }
.toolbar .form-control { max-width: 360px; }
.pager { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 16px; border-top: 1px solid var(--line); flex-wrap: wrap; }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.full { grid-column: 1 / -1; }
@media (max-width: 520px) { .grid2 { grid-template-columns: 1fr; } }
</style>
