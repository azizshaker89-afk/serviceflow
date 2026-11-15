<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { http, apiError } from '@/lib/http'
import { useAuthStore } from '@/stores/auth'
import { formatDateTime } from '@/lib/format'
import StatusChip from '@/components/StatusChip.vue'
import SlaChip from '@/components/SlaChip.vue'

const router = useRouter()
const auth = useAuthStore()

const PRESETS = [
  { key: 'open', label: 'المفتوحة', params: { open: 1 } },
  { key: 'new', label: 'تحتاج تعيين', params: { status: 'new' } },
  { key: 'overdue', label: 'المتأخرة', params: { overdue: 1 } },
  { key: 'all', label: 'الكل', params: {} },
]

const preset = ref('open')
const filters = reactive({ search: '', priority: '', sort: 'due_at', direction: 'asc' })
const page = ref(1)
const items = ref([])
const meta = ref(null)
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    const params = {
      ...PRESETS.find((p) => p.key === preset.value).params,
      ...Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== '')),
      page: page.value,
    }
    const { data } = await http.get('/service-requests', { params })
    items.value = data.data.items
    meta.value = data.data.meta
  } catch (e) {
    error.value = apiError(e).message
  } finally {
    loading.value = false
  }
}

let searchTimer
watch(() => filters.search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { page.value = 1; load() }, 350)
})
watch([preset, () => filters.priority, () => filters.sort, () => filters.direction], () => { page.value = 1; load() })
watch(page, load)
onMounted(load)

function toggleSort(column) {
  if (filters.sort === column) filters.direction = filters.direction === 'asc' ? 'desc' : 'asc'
  else { filters.sort = column; filters.direction = column === 'due_at' ? 'asc' : 'desc' }
}
const open = (item) => router.push({ name: 'request-details', params: { id: item.id } })
</script>

<template>
  <header class="page-head">
    <div>
      <h1>طلبات الصيانة</h1>
      <p class="muted">رتّب بالموعد النهائي عشان المتأخر يبان الأول.</p>
    </div>
    <RouterLink v-if="auth.can('service-requests.create')" :to="{ name: 'request-new' }" class="btn-sf signal">طلب جديد</RouterLink>
  </header>

  <div class="toolbar">
    <div class="presets" role="tablist" aria-label="تصفية سريعة">
      <button v-for="p in PRESETS" :key="p.key" type="button" role="tab" :aria-selected="preset === p.key" :class="{ on: preset === p.key }" @click="preset = p.key">{{ p.label }}</button>
    </div>
    <div class="filters">
      <label class="visually-hidden" for="rq-search">بحث</label>
      <input id="rq-search" v-model.trim="filters.search" class="form-control" placeholder="ابحث برقم الطلب أو اسم العميل أو موبايله" />
      <label class="visually-hidden" for="rq-priority">الأولوية</label>
      <select id="rq-priority" v-model="filters.priority" class="form-select">
        <option value="">كل الأولويات</option>
        <option value="urgent">عاجلة</option>
        <option value="high">عالية</option>
        <option value="normal">عادية</option>
        <option value="low">منخفضة</option>
      </select>
    </div>
  </div>

  <div class="panel">
    <div v-if="error" class="state">
      <h3>تعذر تحميل الطلبات</h3>
      <p>{{ error }}</p>
      <button type="button" class="btn-sf" @click="load">إعادة المحاولة</button>
    </div>
    <div v-else class="table-wrap">
      <table class="table-sf">
        <thead>
          <tr>
            <th><button type="button" class="sort" @click="toggleSort('code')">رقم الطلب</button></th>
            <th>العميل</th>
            <th>الخدمة</th>
            <th>الحالة</th>
            <th>الفني</th>
            <th><button type="button" class="sort" :aria-sort="filters.sort === 'due_at' ? (filters.direction === 'asc' ? 'ascending' : 'descending') : 'none'" @click="toggleSort('due_at')">الموعد النهائي {{ filters.sort === 'due_at' ? (filters.direction === 'asc' ? '↑' : '↓') : '' }}</button></th>
          </tr>
        </thead>
        <tbody v-if="loading">
          <tr v-for="n in 6" :key="n"><td v-for="c in 6" :key="c"><div class="skeleton"></div></td></tr>
        </tbody>
        <tbody v-else-if="items.length">
          <tr v-for="r in items" :key="r.id" class="clickable" tabindex="0" @click="open(r)" @keydown.enter="open(r)">
            <td>
              <RouterLink :to="{ name: 'request-details', params: { id: r.id } }" class="code num" @click.stop>{{ r.code }}</RouterLink>
              <span v-if="r.source === 'online'" class="chip signal tiny">أونلاين</span>
            </td>
            <td><b class="d-block">{{ r.customer.name }}</b><span class="faint num">{{ r.customer.phone }}</span></td>
            <td>{{ r.category.name }}<span class="d-block faint">{{ r.priority_label }}</span></td>
            <td><StatusChip :status="r.status" :label="r.status_label" /></td>
            <td>{{ r.technician?.name ?? '—' }}</td>
            <td><SlaChip :due-at="r.due_at" :status="r.status" /><span class="d-block faint num">{{ formatDateTime(r.due_at) }}</span></td>
          </tr>
        </tbody>
        <tbody v-else>
          <tr><td colspan="6"><div class="state"><h3>مفيش طلبات هنا</h3><p>جرّب تغيّر التصفية أو كلمة البحث.</p></div></td></tr>
        </tbody>
      </table>
    </div>
    <div v-if="meta && meta.last_page > 1" class="pager">
      <span class="faint">صفحة <span class="num">{{ meta.current_page }}</span> من <span class="num">{{ meta.last_page }}</span> · <span class="num">{{ meta.total }}</span> طلب</span>
      <div class="d-flex gap-2">
        <button type="button" class="btn-sf" :disabled="page <= 1" @click="page--">السابق</button>
        <button type="button" class="btn-sf" :disabled="page >= meta.last_page" @click="page++">التالي</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page-head { margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; }
.page-head h1 { font-size: var(--fs-2xl); }
.toolbar { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
.presets { display: inline-flex; background: var(--surface); border: 1px solid var(--line); border-radius: var(--r-sm); padding: 3px; flex-wrap: wrap; }
.presets button { border: 0; background: transparent; padding: 6px 14px; border-radius: 4px; color: var(--ink-2); font-weight: 500; font-size: var(--fs-sm); }
.presets button.on { background: var(--ink); color: var(--paper); }
.filters { display: flex; gap: 8px; flex: 1; justify-content: flex-end; min-width: 280px; }
.filters .form-control { max-width: 340px; }
.filters .form-select { max-width: 170px; }
.code { font-weight: 700; text-decoration: none; }
.tiny { font-size: var(--fs-xs); padding: 0 8px; margin-inline-start: 6px; }
.sort { border: 0; background: none; padding: 0; color: inherit; font-weight: inherit; }
.pager { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 16px; border-top: 1px solid var(--line); flex-wrap: wrap; }
@media (max-width: 640px) { .filters { min-width: 0; flex-direction: column; } .filters .form-control, .filters .form-select { max-width: none; } }
</style>
