<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import { http, apiError } from '@/lib/http'
import { formatDateTime } from '@/lib/format'

const filters = reactive({ group: '', causer_id: '', search: '' })
const page = ref(1)
const items = ref([])
const meta = ref(null)
const groups = ref([])
const staff = ref([])
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    const params = { ...Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== '')), page: page.value }
    const { data } = await http.get('/activity-logs', { params })
    items.value = data.data.items
    meta.value = data.data.meta
    groups.value = data.data.groups
    staff.value = data.data.staff
  } catch (e) {
    error.value = apiError(e).message
  } finally {
    loading.value = false
  }
}
let timer
watch(() => filters.search, () => { clearTimeout(timer); timer = setTimeout(() => { page.value = 1; load() }, 300) })
watch([() => filters.group, () => filters.causer_id], () => { page.value = 1; load() })
watch(page, load)
onMounted(load)

const ROUTES = { service_request: 'request-details', invoice: 'invoice-details', customer: 'customer-details' }
</script>

<template>
  <header class="page-head">
    <h1>سجل النشاط</h1>
    <p class="muted">كل عملية مهمة: من عملها، إمتى، وعلى أنهي سجل. السجل للقراءة فقط، ولا يُعدَّل أو يُحذف.</p>
  </header>

  <div class="toolbar">
    <select v-model="filters.group" class="form-select" aria-label="القسم">
      <option value="">كل الأقسام</option>
      <option v-for="g in groups" :key="g.key" :value="g.key">{{ g.label }}</option>
    </select>
    <select v-model="filters.causer_id" class="form-select" aria-label="الموظف">
      <option value="">كل الموظفين</option>
      <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }}</option>
    </select>
    <input v-model.trim="filters.search" class="form-control" placeholder="ابحث برقم طلب أو فاتورة أو كلمة" aria-label="بحث" />
  </div>

  <div class="panel">
    <div v-if="error" class="state"><h3>تعذر التحميل</h3><p>{{ error }}</p></div>
    <ol v-else-if="!loading && items.length" class="log">
      <li v-for="a in items" :key="a.id">
        <time class="num faint">{{ formatDateTime(a.created_at) }}</time>
        <span class="group">{{ a.group_label }}</span>
        <div class="what">
          <span><b>{{ a.causer }}</b> · {{ a.description }}</span>
          <span v-if="a.properties?.note" class="faint note">{{ a.properties.note }}</span>
        </div>
        <RouterLink v-if="a.subject && ROUTES[a.subject.type]" :to="{ name: ROUTES[a.subject.type], params: { id: a.subject.id } }" class="num link">{{ a.subject.label }}</RouterLink>
      </li>
    </ol>
    <div v-else-if="loading" class="panel-section"><div v-for="n in 8" :key="n" class="skeleton mb-3"></div></div>
    <div v-else class="state"><h3>مفيش نشاط بالتصفية دي</h3></div>
    <div v-if="meta && meta.last_page > 1" class="pager">
      <span class="faint">صفحة <span class="num">{{ meta.current_page }}</span> من <span class="num">{{ meta.last_page }}</span> · <span class="num">{{ meta.total }}</span> عملية</span>
      <div class="d-flex gap-2">
        <button type="button" class="btn-sf" :disabled="page <= 1" @click="page--">الأحدث</button>
        <button type="button" class="btn-sf" :disabled="page >= meta.last_page" @click="page++">الأقدم</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page-head { margin-bottom: 18px; display: grid; gap: 4px; }
.page-head h1 { font-size: var(--fs-2xl); }
.toolbar { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
.toolbar .form-select { max-width: 200px; }
.toolbar .form-control { max-width: 320px; }
.log { list-style: none; margin: 0; padding: 0; }
.log li { display: grid; grid-template-columns: 130px 90px minmax(0, 1fr) auto; gap: 14px; align-items: baseline; padding: 12px 20px; border-bottom: 1px solid var(--line); font-size: var(--fs-sm); }
.group { font-size: var(--fs-xs); color: var(--ink-2); border: 1px solid var(--line); border-radius: 4px; padding: 0 8px; justify-self: start; }
.what { display: grid; min-width: 0; }
.note { font-size: var(--fs-xs); }
.link { font-weight: 600; }
.pager { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 16px; flex-wrap: wrap; }
@media (max-width: 760px) { .log li { grid-template-columns: minmax(0, 1fr); gap: 2px; } }
</style>
