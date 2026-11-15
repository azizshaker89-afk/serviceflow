<script setup>
import { computed, onMounted, ref } from 'vue'
import { http, apiError } from '@/lib/http'
import { useAuthStore } from '@/stores/auth'
import StatusChip from '@/components/StatusChip.vue'
import SlaChip from '@/components/SlaChip.vue'

const auth = useAuthStore()
const tasks = ref([])
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await http.get('/service-requests', { params: { open: 1, sort: 'due_at', direction: 'asc', per_page: 50 } })
    tasks.value = data.data.items
  } catch (e) {
    error.value = apiError(e).message
  } finally {
    loading.value = false
  }
}
onMounted(load)

const lateCount = computed(() => tasks.value.filter((t) => t.is_overdue).length)
const firstName = computed(() => auth.user?.name?.split(' ')[0] ?? '')
</script>

<template>
  <header class="page-head">
    <h1>مهامي</h1>
    <p class="muted" v-if="!loading && !error">
      أهلًا {{ firstName }}. عندك <b class="num">{{ tasks.length }}</b> مهام مفتوحة<template v-if="lateCount">، منها <b class="late-text num">{{ lateCount }}</b> متأخرة</template>.
    </p>
  </header>

  <div v-if="error" class="panel state">
    <h3>تعذر تحميل مهامك</h3><p>{{ error }}</p>
    <button type="button" class="btn-sf" @click="load">إعادة المحاولة</button>
  </div>

  <ol v-else-if="loading" class="tasks">
    <li v-for="n in 3" :key="n" class="task panel"><div class="skeleton w-25 mb-2"></div><div class="skeleton w-75 mb-2"></div><div class="skeleton w-50"></div></li>
  </ol>

  <ol v-else-if="tasks.length" class="tasks">
    <li v-for="t in tasks" :key="t.id" class="task panel" :class="{ late: t.is_overdue }">
      <div class="task-main">
        <div class="row-top">
          <span class="code num">{{ t.code }}</span>
          <StatusChip :status="t.status" :label="t.status_label" />
          <SlaChip :due-at="t.due_at" :status="t.status" />
        </div>
        <h2>{{ t.description }}</h2>
        <p class="muted">{{ t.category.name }} · {{ t.customer.name }} · <span class="num">{{ t.customer.phone }}</span></p>
        <p class="faint">{{ t.address }}</p>
      </div>
      <RouterLink :to="{ name: 'request-details', params: { id: t.id } }" class="btn-sf" :class="{ signal: t.status === 'assigned' }">
        {{ t.status === 'assigned' ? 'استلام المهمة' : 'فتح المهمة' }}
      </RouterLink>
    </li>
  </ol>

  <div v-else class="panel state">
    <h3>مفيش مهام معيّنة لك دلوقتي</h3>
    <p>أول ما المدير يعيّن لك طلب هيظهر هنا، مرتب بالموعد النهائي.</p>
  </div>
</template>

<style scoped>
.page-head { margin-bottom: 20px; display: grid; gap: 4px; }
.page-head h1 { font-size: var(--fs-2xl); }
.late-text { color: var(--late); }
.tasks { list-style: none; padding: 0; margin: 0; display: grid; gap: 12px; max-width: 920px; }
.task { padding: 18px 20px; display: flex; gap: 20px; align-items: center; justify-content: space-between; }
.task.late { border-color: var(--late); box-shadow: inset -4px 0 0 var(--late); }
.task-main { display: grid; gap: 6px; min-width: 0; }
.row-top { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.code { font-weight: 700; color: var(--ink-2); }
.task h2 { font-size: var(--fs-lg); }
.task .btn-sf { flex: none; min-height: 48px; }
@media (max-width: 640px) { .task { flex-direction: column; align-items: stretch; } }
</style>
