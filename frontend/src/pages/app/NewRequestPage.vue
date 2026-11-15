<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { http, apiError } from '@/lib/http'
import { useToastStore } from '@/stores/toast'

const router = useRouter()
const toast = useToastStore()

const categories = ref([])
const mode = ref('existing')
const search = ref('')
const results = ref([])
const searching = ref(false)
const customer = ref(null)
const newCustomer = reactive({ name: '', phone: '', city: '', address: '' })
const form = reactive({ service_category_id: '', priority: 'normal', description: '', address: '', preferred_date: '' })
const errors = ref({})
const message = ref('')
const saving = ref(false)
const today = new Date().toISOString().slice(0, 10)

onMounted(async () => {
  const { data } = await http.get('/service-categories')
  categories.value = data.data
})

let timer
watch(search, (term) => {
  clearTimeout(timer)
  if (term.trim().length < 2) { results.value = []; return }
  timer = setTimeout(async () => {
    searching.value = true
    try {
      const { data } = await http.get('/customers', { params: { search: term.trim() } })
      results.value = data.data.items
    } finally {
      searching.value = false
    }
  }, 300)
})

function pick(c) {
  customer.value = c
  results.value = []
  search.value = ''
  if (!form.address) form.address = c.address ?? ''
}

async function save() {
  saving.value = true
  errors.value = {}
  message.value = ''
  const payload = {
    ...Object.fromEntries(Object.entries(form).filter(([, v]) => v !== '')),
    ...(mode.value === 'existing'
      ? { customer_id: customer.value?.id ?? null }
      : { customer: Object.fromEntries(Object.entries(newCustomer).filter(([, v]) => v !== '')) }),
  }
  try {
    const { data } = await http.post('/service-requests', payload)
    toast.show(data.message)
    router.push({ name: 'request-details', params: { id: data.data.id } })
  } catch (e) {
    const err = apiError(e)
    errors.value = err.errors
    message.value = err.status === 422 ? 'راجع الحقول المظللة بالأحمر.' : err.message
  } finally {
    saving.value = false
  }
}

const err = (field) => errors.value[field]?.[0]

const PRIORITIES = [
  { value: 'urgent', label: 'عاجلة', sla: 'خلال 4 ساعات' },
  { value: 'high', label: 'عالية', sla: 'خلال 24 ساعة' },
  { value: 'normal', label: 'عادية', sla: 'خلال 48 ساعة' },
  { value: 'low', label: 'منخفضة', sla: 'خلال 72 ساعة' },
]
</script>

<template>
  <RouterLink :to="{ name: 'requests' }" class="back">طلبات الصيانة</RouterLink>
  <header class="page-head">
    <h1>طلب صيانة جديد</h1>
    <p class="muted">لطلبات التليفون والزيارات. العملاء يقدرون كمان يطلبوا بنفسهم من الموقع.</p>
  </header>

  <form class="layout" novalidate @submit.prevent="save">
    <section class="panel">
      <div class="panel-section">
        <div class="section-head">
          <h2>العميل</h2>
          <div class="seg" role="tablist">
            <button type="button" role="tab" :aria-selected="mode === 'existing'" :class="{ on: mode === 'existing' }" @click="mode = 'existing'">عميل مسجل</button>
            <button type="button" role="tab" :aria-selected="mode === 'new'" :class="{ on: mode === 'new' }" @click="mode = 'new'">عميل جديد</button>
          </div>
        </div>

        <template v-if="mode === 'existing'">
          <div v-if="customer" class="picked">
            <div><b>{{ customer.name }}</b><span class="num faint">{{ customer.phone }}</span><span class="faint">{{ customer.address }}</span></div>
            <button type="button" class="btn-sf quiet" @click="customer = null">تغيير</button>
          </div>
          <div v-else class="search">
            <label class="form-label" for="nr-search">ابحث بالاسم أو أول أرقام الموبايل</label>
            <input id="nr-search" v-model="search" class="form-control" :class="{ 'is-invalid': err('customer') || err('customer_id') }" autocomplete="off" placeholder="مثال: 0101 أو أحمد" />
            <div class="invalid-feedback">{{ err('customer') || err('customer_id') }}</div>
            <ul v-if="results.length" class="results" role="listbox">
              <li v-for="c in results" :key="c.id">
                <button type="button" @click="pick(c)">
                  <b>{{ c.name }}</b><span class="num faint">{{ c.phone }}</span>
                  <small class="faint">{{ c.service_requests_count }} طلب سابق</small>
                </button>
              </li>
            </ul>
            <p v-else-if="search.length >= 2 && !searching" class="form-text">مفيش عميل بالبيانات دي. <button type="button" class="link" @click="mode = 'new'">سجّله كعميل جديد</button></p>
          </div>
        </template>

        <div v-else class="grid2">
          <div>
            <label class="form-label" for="nc-name">الاسم</label>
            <input id="nc-name" v-model.trim="newCustomer.name" class="form-control" :class="{ 'is-invalid': err('customer.name') }" />
            <div class="invalid-feedback">{{ err('customer.name') }}</div>
          </div>
          <div>
            <label class="form-label" for="nc-phone">الموبايل</label>
            <input id="nc-phone" v-model.trim="newCustomer.phone" class="form-control num" inputmode="tel" :class="{ 'is-invalid': err('customer.phone') }" placeholder="01XXXXXXXXX" />
            <div class="invalid-feedback">{{ err('customer.phone') }}</div>
          </div>
          <div>
            <label class="form-label" for="nc-city">المدينة</label>
            <input id="nc-city" v-model.trim="newCustomer.city" class="form-control" />
          </div>
          <div>
            <label class="form-label" for="nc-address">العنوان</label>
            <input id="nc-address" v-model.trim="newCustomer.address" class="form-control" />
          </div>
          <p class="form-text full">لو الرقم مسجل قبل كده، الطلب هيتربط بنفس العميل بدل ما يتكرر.</p>
        </div>
      </div>

      <div class="panel-section">
        <div class="section-head"><h2>العطل</h2></div>
        <div class="grid2">
          <div class="full">
            <label class="form-label" for="nr-cat">نوع الخدمة</label>
            <select id="nr-cat" v-model.number="form.service_category_id" class="form-select" :class="{ 'is-invalid': err('service_category_id') }">
              <option value="" disabled>اختر نوع الخدمة</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <div class="invalid-feedback">{{ err('service_category_id') }}</div>
          </div>
          <div class="full">
            <label class="form-label" for="nr-desc">وصف المشكلة</label>
            <textarea id="nr-desc" v-model.trim="form.description" rows="3" class="form-control" :class="{ 'is-invalid': err('description') }" placeholder="اكتب كلام العميل كما قاله، مع أي تفاصيل عن الجهاز"></textarea>
            <div class="invalid-feedback">{{ err('description') }}</div>
          </div>
          <div>
            <label class="form-label" for="nr-addr">عنوان الزيارة</label>
            <input id="nr-addr" v-model.trim="form.address" class="form-control" placeholder="لو مختلف عن عنوان العميل" />
          </div>
          <div>
            <label class="form-label" for="nr-date">الموعد المفضل</label>
            <input id="nr-date" v-model="form.preferred_date" type="date" class="form-control" :min="today" :class="{ 'is-invalid': err('preferred_date') }" />
            <div class="invalid-feedback">{{ err('preferred_date') }}</div>
          </div>
        </div>
      </div>
    </section>

    <aside class="panel panel-section side">
      <h2 class="side-title">الأولوية</h2>
      <fieldset class="prio">
        <legend class="visually-hidden">الأولوية</legend>
        <label v-for="p in PRIORITIES" :key="p.value" :class="{ on: form.priority === p.value }">
          <input v-model="form.priority" type="radio" name="priority" :value="p.value" />
          <span><b>{{ p.label }}</b><small>{{ p.sla }}</small></span>
        </label>
      </fieldset>
      <p class="form-text">الموعد النهائي يتحسب من دلوقتي حسب الأولوية، والطلب يبان «متأخر» لو عدّاه.</p>
      <p v-if="message" class="alert-sf late" role="alert">{{ message }}</p>
      <button type="submit" class="btn-sf signal lg" :disabled="saving">{{ saving ? 'جارٍ الحفظ…' : 'تسجيل الطلب' }}</button>
    </aside>
  </form>
</template>

<style scoped>
.back { font-size: var(--fs-sm); color: var(--ink-2); text-decoration: none; }
.back::before { content: "→ "; }
.page-head { margin-block: 12px 20px; display: grid; gap: 4px; }
.page-head h1 { font-size: var(--fs-2xl); }
.layout { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 24px; align-items: start; }
.seg { display: inline-flex; background: var(--paper); border: 1px solid var(--line); border-radius: var(--r-sm); padding: 2px; }
.seg button { border: 0; background: transparent; padding: 4px 12px; border-radius: 4px; font-size: var(--fs-sm); color: var(--ink-2); }
.seg button.on { background: var(--ink); color: var(--paper); }
.search { position: relative; }
.results { list-style: none; margin: 6px 0 0; padding: 4px; border: 1px solid var(--line); border-radius: var(--r-sm); background: var(--surface); display: grid; gap: 2px; }
.results button { width: 100%; text-align: start; border: 0; background: transparent; padding: 8px 10px; border-radius: 4px; display: flex; gap: 12px; align-items: baseline; flex-wrap: wrap; }
.results button:hover, .results button:focus-visible { background: var(--paper); }
.results small { margin-inline-start: auto; }
.picked { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid var(--ink); border-radius: var(--r-sm); }
.picked div { display: flex; gap: 14px; flex-wrap: wrap; align-items: baseline; }
.link { border: 0; background: none; padding: 0; color: var(--signal); font-weight: 600; text-decoration: underline; }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.full { grid-column: 1 / -1; }
.side { display: grid; gap: 14px; position: sticky; top: 20px; }
.side-title { font-size: var(--fs-lg); }
.prio { display: grid; gap: 6px; border: 0; padding: 0; margin: 0; }
.prio label { display: flex; gap: 10px; align-items: center; padding: 10px 12px; border: 1px solid var(--line); border-radius: var(--r-sm); cursor: pointer; }
.prio label.on { border-color: var(--ink); box-shadow: inset 0 0 0 1px var(--ink); }
.prio input { accent-color: var(--ink); }
.prio span { display: grid; line-height: 1.35; }
.prio small { color: var(--ink-3); }
@media (max-width: 960px) { .layout { grid-template-columns: minmax(0, 1fr); } .side { position: static; } }
@media (max-width: 560px) { .grid2 { grid-template-columns: 1fr; } }
</style>
