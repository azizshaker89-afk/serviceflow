<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { http, apiError } from '@/lib/http'
import { useToastStore } from '@/stores/toast'
import { formatDateTime, formatMoney } from '@/lib/format'
import AppDialog from '@/components/AppDialog.vue'

const toast = useToastStore()

const items = ref([])
const meta = ref(null)
const loading = ref(true)
const error = ref('')
const filters = reactive({ search: '', low_stock: false })
const page = ref(1)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await http.get('/spare-parts', {
      params: { search: filters.search || undefined, low_stock: filters.low_stock ? 1 : undefined, page: page.value },
    })
    items.value = data.data.items
    meta.value = data.data.meta
  } catch (e) {
    error.value = apiError(e).message
  } finally {
    loading.value = false
  }
}
let timer
watch(() => filters.search, () => { clearTimeout(timer); timer = setTimeout(() => { page.value = 1; load() }, 300) })
watch(() => filters.low_stock, () => { page.value = 1; load() })
watch(page, load)
onMounted(load)

/* ---------- dialogs ---------- */
const dialog = ref(null)
const target = ref(null)
const form = reactive({})
const errors = ref({})
const message = ref('')
const busy = ref(false)
const movements = ref([])

function reset(values) {
  Object.keys(form).forEach((k) => delete form[k])
  Object.assign(form, values)
  errors.value = {}
  message.value = ''
}

function openStock(part, type) {
  target.value = part
  reset({ type, quantity: type === 'purchase' ? 10 : -1, note: '' })
  dialog.value = 'stock'
}
function openEdit(part) {
  target.value = part
  reset({ name: part.name, unit_cost: part.unit_cost, unit_price: part.unit_price, min_stock: part.min_stock, is_active: part.is_active })
  dialog.value = 'edit'
}
function openCreate() {
  target.value = null
  reset({ sku: '', name: '', unit_cost: '', unit_price: '', min_stock: 5, opening_stock: 0 })
  dialog.value = 'create'
}
async function openMovements(part) {
  target.value = part
  movements.value = []
  dialog.value = 'movements'
  try {
    const { data } = await http.get(`/spare-parts/${part.id}/movements`)
    movements.value = data.data.movements
  } catch (e) {
    toast.show(apiError(e).message, 'late')
  }
}

async function submit(request) {
  busy.value = true
  errors.value = {}
  message.value = ''
  try {
    const { data } = await request()
    toast.show(data.message)
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

const saveStock = () => submit(() => http.post(`/spare-parts/${target.value.id}/stock`, form))
const saveEdit = () => submit(() => http.put(`/spare-parts/${target.value.id}`, form))
const saveCreate = () => submit(() => http.post('/spare-parts', form))

const err = (f) => errors.value[f]?.[0]
const projected = computed(() => (target.value ? target.value.stock_quantity + Number(form.quantity || 0) : 0))
const margin = (p) => (Number(p.unit_cost) > 0 ? Math.round(((p.unit_price - p.unit_cost) / p.unit_cost) * 100) : null)
</script>

<template>
  <header class="page-head">
    <div>
      <h1>المخزون</h1>
      <p class="muted">الرصيد لا يُعدَّل يدويًا. كل تغيير هو حركة: توريد، صرف لطلب، مرتجع، أو تسوية جرد بسبب مكتوب.</p>
    </div>
    <button type="button" class="btn-sf signal" @click="openCreate">قطعة جديدة</button>
  </header>

  <div class="toolbar">
    <label class="visually-hidden" for="inv-search">بحث</label>
    <input id="inv-search" v-model.trim="filters.search" class="form-control" placeholder="ابحث بالاسم أو الكود" />
    <label class="low-toggle" :class="{ on: filters.low_stock }">
      <input v-model="filters.low_stock" type="checkbox" />
      تحت الحد الأدنى
      <span v-if="meta?.low_stock_count" class="chip late tiny num">{{ meta.low_stock_count }}</span>
    </label>
  </div>

  <div class="panel">
    <div v-if="error" class="state"><h3>تعذر تحميل المخزون</h3><p>{{ error }}</p><button type="button" class="btn-sf" @click="load">إعادة المحاولة</button></div>
    <div v-else class="table-wrap">
      <table class="table-sf">
        <thead>
          <tr><th>القطعة</th><th>الرصيد</th><th>التكلفة</th><th>البيع</th><th>الهامش</th><th>المصروف</th><th><span class="visually-hidden">إجراءات</span></th></tr>
        </thead>
        <tbody v-if="loading"><tr v-for="n in 6" :key="n"><td v-for="c in 7" :key="c"><div class="skeleton"></div></td></tr></tbody>
        <tbody v-else-if="items.length">
          <tr v-for="p in items" :key="p.id" :class="{ inactive: !p.is_active }">
            <td><b class="d-block">{{ p.name }}</b><span class="faint num">{{ p.sku }}</span><span v-if="!p.is_active" class="chip tiny ms-2">موقوفة</span></td>
            <td>
              <span class="stock num" :class="{ low: p.is_low_stock }">{{ p.stock_quantity }}</span>
              <span class="faint d-block">الحد الأدنى <span class="num">{{ p.min_stock }}</span></span>
            </td>
            <td>{{ formatMoney(p.unit_cost) }}</td>
            <td>{{ formatMoney(p.unit_price) }}</td>
            <td class="num">{{ margin(p) !== null ? `${margin(p)}%` : '—' }}</td>
            <td class="num">{{ p.used_quantity }}</td>
            <td class="row-actions">
              <button type="button" class="btn-sf mini" :class="{ signal: p.is_low_stock }" @click="openStock(p, 'purchase')">توريد</button>
              <button type="button" class="btn-sf mini quiet" @click="openStock(p, 'adjustment')">تسوية</button>
              <button type="button" class="btn-sf mini quiet" @click="openMovements(p)">الحركات</button>
              <button type="button" class="btn-sf mini quiet" @click="openEdit(p)">تعديل</button>
            </td>
          </tr>
        </tbody>
        <tbody v-else><tr><td colspan="7"><div class="state"><h3>{{ filters.low_stock ? 'كل القطع فوق الحد الأدنى' : 'مفيش قطع بالاسم ده' }}</h3></div></td></tr></tbody>
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

  <AppDialog :open="dialog === 'stock'" :title="form.type === 'purchase' ? 'توريد كمية جديدة' : 'تسوية بعد الجرد'" @close="dialog = null">
    <p v-if="target"><b>{{ target.name }}</b> · الرصيد الحالي <b class="num">{{ target.stock_quantity }}</b></p>
    <div>
      <label class="form-label" for="st-qty">{{ form.type === 'purchase' ? 'الكمية الواردة' : 'الفرق (سالب للنقص، موجب للزيادة)' }}</label>
      <input id="st-qty" v-model.number="form.quantity" type="number" class="form-control num" :class="{ 'is-invalid': err('quantity') }" />
      <div class="invalid-feedback">{{ err('quantity') }}</div>
    </div>
    <div>
      <label class="form-label" for="st-note">{{ form.type === 'purchase' ? 'ملاحظة (رقم فاتورة المورد مثلًا)' : 'سبب التسوية' }}</label>
      <input id="st-note" v-model.trim="form.note" class="form-control" :class="{ 'is-invalid': err('note') }" :placeholder="form.type === 'purchase' ? 'اختياري' : 'مثال: 2 تالفة بعد الجرد'" />
      <div class="invalid-feedback">{{ err('note') }}</div>
    </div>
    <p v-if="target" class="form-text">الرصيد بعد الحركة: <b class="num" :class="{ 'text-danger': projected < 0 }">{{ projected }}</b></p>
    <p v-if="message" class="alert-sf late">{{ message }}</p>
    <template #footer>
      <button type="button" class="btn-sf ink" :disabled="busy" @click="saveStock">تسجيل الحركة</button>
      <button type="button" class="btn-sf quiet" @click="dialog = null">تراجع</button>
    </template>
  </AppDialog>

  <AppDialog :open="dialog === 'edit' || dialog === 'create'" :title="dialog === 'create' ? 'قطعة جديدة' : 'تعديل بيانات القطعة'" width="520px" @close="dialog = null">
    <div class="grid2">
      <div v-if="dialog === 'create'">
        <label class="form-label" for="pt-sku">الكود</label>
        <input id="pt-sku" v-model.trim="form.sku" class="form-control num" :class="{ 'is-invalid': err('sku') }" placeholder="AC-CAP-45" />
        <div class="invalid-feedback">{{ err('sku') }}</div>
      </div>
      <div :class="{ full: dialog === 'edit' }">
        <label class="form-label" for="pt-name">الاسم</label>
        <input id="pt-name" v-model.trim="form.name" class="form-control" :class="{ 'is-invalid': err('name') }" />
        <div class="invalid-feedback">{{ err('name') }}</div>
      </div>
      <div>
        <label class="form-label" for="pt-cost">سعر التكلفة</label>
        <input id="pt-cost" v-model="form.unit_cost" inputmode="decimal" class="form-control num" :class="{ 'is-invalid': err('unit_cost') }" />
        <div class="invalid-feedback">{{ err('unit_cost') }}</div>
      </div>
      <div>
        <label class="form-label" for="pt-price">سعر البيع</label>
        <input id="pt-price" v-model="form.unit_price" inputmode="decimal" class="form-control num" :class="{ 'is-invalid': err('unit_price') }" />
        <div class="invalid-feedback">{{ err('unit_price') }}</div>
      </div>
      <div>
        <label class="form-label" for="pt-min">الحد الأدنى للتنبيه</label>
        <input id="pt-min" v-model.number="form.min_stock" type="number" min="0" class="form-control num" :class="{ 'is-invalid': err('min_stock') }" />
      </div>
      <div v-if="dialog === 'create'">
        <label class="form-label" for="pt-open">الرصيد الافتتاحي</label>
        <input id="pt-open" v-model.number="form.opening_stock" type="number" min="0" class="form-control num" />
      </div>
      <label v-else class="form-check align-self-end mb-2">
        <input v-model="form.is_active" class="form-check-input" type="checkbox" />
        <span class="form-check-label">متاحة للصرف</span>
      </label>
    </div>
    <p class="form-text">{{ dialog === 'create' ? 'الرصيد الافتتاحي يُسجَّل كحركة توريد.' : 'تغيير السعر لا يغيّر أسعار القطع المصروفة سابقًا، فهي مثبتة لحظة الصرف.' }}</p>
    <p v-if="message" class="alert-sf late">{{ message }}</p>
    <template #footer>
      <button type="button" class="btn-sf ink" :disabled="busy" @click="dialog === 'create' ? saveCreate() : saveEdit()">{{ dialog === 'create' ? 'إضافة القطعة' : 'حفظ التعديلات' }}</button>
      <button type="button" class="btn-sf quiet" @click="dialog = null">تراجع</button>
    </template>
  </AppDialog>

  <AppDialog :open="dialog === 'movements'" :title="`سجل حركات: ${target?.name ?? ''}`" width="720px" @close="dialog = null">
    <p class="muted">كل سطر يشرح تغيّر الرصيد، والرصيد بعد الحركة محفوظ معه.</p>
    <div class="table-wrap">
      <table class="table-sf">
        <thead><tr><th>الوقت</th><th>الحركة</th><th>الكمية</th><th>الرصيد بعدها</th><th>بواسطة</th><th>المرجع</th></tr></thead>
        <tbody>
          <tr v-for="m in movements" :key="m.id">
            <td class="num">{{ formatDateTime(m.created_at) }}</td>
            <td>{{ m.type_label }}</td>
            <td class="num" :class="m.quantity < 0 ? 'neg' : 'pos'">{{ m.quantity > 0 ? `+${m.quantity}` : m.quantity }}</td>
            <td class="num"><b>{{ m.balance_after }}</b></td>
            <td>{{ m.user }}</td>
            <td>
              <RouterLink v-if="m.service_request" :to="{ name: 'request-details', params: { id: m.service_request.id } }" class="num">{{ m.service_request.code }}</RouterLink>
              <span v-else class="faint">{{ m.note || '—' }}</span>
            </td>
          </tr>
          <tr v-if="!movements.length"><td colspan="6"><div class="skeleton"></div></td></tr>
        </tbody>
      </table>
    </div>
  </AppDialog>
</template>

<style scoped>
.page-head { margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; }
.page-head h1 { font-size: var(--fs-2xl); }
.page-head p { max-width: 70ch; }
.toolbar { display: flex; gap: 10px; margin-bottom: 14px; flex-wrap: wrap; }
.toolbar .form-control { max-width: 340px; }
.low-toggle { display: inline-flex; align-items: center; gap: 8px; padding: 0 14px; border: 1px solid var(--line); border-radius: var(--r-sm); background: var(--surface); cursor: pointer; font-size: var(--fs-sm); font-weight: 500; min-height: 44px; }
.low-toggle.on { border-color: var(--ink); }
.low-toggle input { accent-color: var(--ink); }
.tiny { font-size: var(--fs-xs); padding: 0 8px; }
.stock { font-size: var(--fs-lg); font-weight: 700; }
.stock.low { color: var(--late); }
tr.inactive td { opacity: .6; }
.row-actions { white-space: nowrap; }
.mini { min-height: 32px; padding: 2px 10px; font-size: var(--fs-sm); }
.neg { color: var(--late); }
.pos { color: var(--done); }
.pager { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 16px; border-top: 1px solid var(--line); flex-wrap: wrap; }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.full { grid-column: 1 / -1; }
@media (max-width: 520px) { .grid2 { grid-template-columns: 1fr; } }
</style>
