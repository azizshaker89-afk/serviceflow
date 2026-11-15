<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { http, apiError } from '@/lib/http'
import { useAuthStore } from '@/stores/auth'
import { formatDate, formatMoney, slaState } from '@/lib/format'
import { STATUS_TONE } from '@/lib/status'
import ChartCanvas from '@/components/charts/ChartCanvas.vue'
import { baseOptions } from '@/components/charts/useChartTheme'
import StatusChip from '@/components/StatusChip.vue'

const auth = useAuthStore()
const PERIODS = [
  { days: 7, label: '7 أيام' },
  { days: 30, label: '30 يوم' },
  { days: 90, label: '90 يوم' },
]
const days = ref(30)
const report = ref(null)
const loading = ref(true)
const error = ref('')
const revenueAsTable = ref(false)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const to = new Date()
    const from = new Date(to.getTime() - (days.value - 1) * 864e5)
    const iso = (d) => d.toISOString().slice(0, 10)
    const { data } = await http.get('/reports/dashboard', { params: { from: iso(from), to: iso(to) } })
    report.value = data.data
  } catch (e) {
    error.value = apiError(e).message
  } finally {
    loading.value = false
  }
}
watch(days, load)
onMounted(load)

const ops = computed(() => report.value?.operations)
const fin = computed(() => report.value?.finance)

const monthName = (ym) => new Intl.DateTimeFormat('ar-EG', { month: 'short' }).format(new Date(`${ym}-01T00:00:00`))
const dayLabel = (d) => new Intl.DateTimeFormat('ar-EG-u-nu-latn', { day: 'numeric', month: 'numeric' }).format(new Date(`${d}T00:00:00`))

/* Intake: a single series, so no legend; the card title names it. */
const intakeChart = computed(() => {
  const rows = ops.value?.daily_intake ?? []
  return (t) => {
    const options = baseOptions(t)
    return {
      type: 'line',
      data: {
        labels: rows.map((r) => dayLabel(r.date)),
        datasets: [{
          label: 'طلبات جديدة',
          data: rows.map((r) => r.count),
          borderColor: t.series[0],
          backgroundColor: `${t.series[0]}1f`,
          fill: true,
          borderWidth: 2,
          tension: 0.3,
          pointRadius: 0,
          pointHoverRadius: 5,
          pointHoverBackgroundColor: t.series[0],
          pointHoverBorderColor: t.surface,
          pointHoverBorderWidth: 2,
        }],
      },
      options,
    }
  }
})

/* Revenue: two measures of the same unit on one axis, grouped bars with a 2px surface gap. */
const revenueChart = computed(() => {
  const rows = fin.value?.monthly_revenue ?? []
  return (t) => {
    const options = baseOptions(t, { money: true })
    const bar = (label, key, color) => ({
      label,
      data: rows.map((r) => Number(r[key])),
      backgroundColor: color,
      borderColor: t.surface,
      borderWidth: { left: 1, right: 1 },
      borderRadius: { topLeft: 4, topRight: 4 },
      borderSkipped: 'bottom',
      maxBarThickness: 28,
      categoryPercentage: 0.7,
      barPercentage: 0.9,
    })
    return {
      type: 'bar',
      data: {
        labels: rows.map((r) => monthName(r.month)),
        datasets: [bar('فواتير صادرة', 'invoiced', t.series[0]), bar('مبالغ محصلة', 'collected', t.series[1])],
      },
      options,
    }
  }
})

const statusRows = computed(() => {
  const rows = (ops.value?.status_distribution ?? []).filter((r) => !['completed', 'cancelled'].includes(r.status))
  const max = Math.max(1, ...rows.map((r) => r.count))
  return rows.map((r) => ({ ...r, pct: (r.count / max) * 100 }))
})
const categoryRows = computed(() => {
  const rows = ops.value?.top_categories ?? []
  const max = Math.max(1, ...rows.map((r) => r.count))
  return rows.map((r) => ({ ...r, pct: (r.count / max) * 100 }))
})
const maxCompleted = computed(() => Math.max(1, ...(ops.value?.technicians ?? []).map((t) => t.completed)))
const greeting = computed(() => auth.user?.name?.split(' ')[0] ?? '')
</script>

<template>
  <header class="page-head">
    <div>
      <h1>لوحة المتابعة</h1>
      <p class="muted">أهلًا {{ greeting }}. الأرقام محسوبة لحظيًا من الطلبات والفواتير.</p>
    </div>
    <div class="period" role="group" aria-label="الفترة">
      <button v-for="p in PERIODS" :key="p.days" type="button" :aria-pressed="days === p.days" :class="{ on: days === p.days }" @click="days = p.days">{{ p.label }}</button>
    </div>
  </header>

  <div v-if="error" class="panel state"><h3>تعذر تحميل اللوحة</h3><p>{{ error }}</p><button type="button" class="btn-sf" @click="load">إعادة المحاولة</button></div>

  <template v-else-if="report">
    <!-- OPERATIONS -->
    <template v-if="ops">
      <section class="tiles" aria-label="مؤشرات التشغيل">
        <RouterLink :to="{ name: 'requests' }" class="tile">
          <span class="t-label">طلبات مفتوحة</span>
          <b class="num">{{ ops.kpis.open }}</b>
          <span class="t-sub">جديد اليوم <span class="num">{{ ops.kpis.new_today }}</span> · الأسبوع <span class="num">{{ ops.kpis.new_this_week }}</span></span>
        </RouterLink>
        <div class="tile" :class="{ attention: ops.kpis.unassigned }">
          <span class="t-label">بانتظار تعيين فني</span>
          <b class="num">{{ ops.kpis.unassigned }}</b>
          <span class="t-sub">{{ ops.kpis.unassigned ? 'خطوتك الجاية' : 'كل الطلبات معيّنة' }}</span>
        </div>
        <div class="tile" :class="{ late: ops.kpis.overdue }">
          <span class="t-label">متأخرة عن موعدها</span>
          <b class="num">{{ ops.kpis.overdue }}</b>
          <span class="t-sub">بانتظار قطع <span class="num">{{ ops.kpis.waiting_parts }}</span></span>
        </div>
        <div class="tile">
          <span class="t-label">اتنفذ في الفترة</span>
          <b class="num">{{ ops.kpis.completed_in_period }}</b>
          <span class="t-sub">في الموعد <span class="num">{{ ops.on_time_rate ?? '—' }}{{ ops.on_time_rate !== null ? '%' : '' }}</span> · متوسط <span class="num">{{ ops.average_completion_hours ?? '—' }}</span> س</span>
        </div>
      </section>

      <div class="row two-one">
        <section class="panel card">
          <div class="card-head"><h2>الطلبات الجديدة يوميًا</h2><span class="faint">آخر {{ days }} يوم</span></div>
          <ChartCanvas :build="intakeChart" :height="220" label="خط بعدد الطلبات الجديدة لكل يوم" />
        </section>
        <section class="panel card">
          <div class="card-head"><h2>الطلبات المفتوحة الآن</h2></div>
          <ul class="hbars">
            <li v-for="r in statusRows" :key="r.status">
              <span class="hb-label">{{ r.label }}</span>
              <span class="hb-track"><span class="hb-fill" :class="STATUS_TONE[r.status] || 'ink'" :style="{ width: r.pct + '%' }"></span></span>
              <b class="num">{{ r.count }}</b>
            </li>
          </ul>
        </section>
      </div>

      <div class="row one-one">
        <section class="panel card">
          <div class="card-head">
            <h2>محتاجة تدخل الآن</h2>
            <RouterLink :to="{ name: 'requests' }" class="faint small">كل الطلبات</RouterLink>
          </div>
          <ul v-if="ops.attention.length" class="attention-list">
            <li v-for="a in ops.attention" :key="a.id">
              <RouterLink :to="{ name: 'request-details', params: { id: a.id } }">
                <span class="a-main"><b class="num">{{ a.code }}</b><span>{{ a.category }} · {{ a.customer }}</span></span>
                <span class="a-side">
                  <StatusChip :status="a.status" :label="a.status_label" />
                  <span class="small" :class="a.is_overdue ? 'late-text' : 'faint'">{{ a.is_overdue ? slaState(a.due_at).text : (a.technician ?? 'بدون فني') }}</span>
                </span>
              </RouterLink>
            </li>
          </ul>
          <p v-else class="empty-ok">مفيش طلبات متأخرة أو بدون فني. كله ماشي.</p>
        </section>
        <section class="panel card">
          <div class="card-head"><h2>أكثر الأعطال طلبًا</h2><span class="faint">آخر {{ days }} يوم</span></div>
          <ul class="hbars">
            <li v-for="c in categoryRows" :key="c.name">
              <span class="hb-label">{{ c.name }}</span>
              <span class="hb-track"><span class="hb-fill series" :style="{ width: c.pct + '%' }"></span></span>
              <b class="num">{{ c.count }}</b>
            </li>
          </ul>
          <p v-if="!categoryRows.length" class="faint">مفيش طلبات في الفترة دي.</p>
        </section>
      </div>
    </template>

    <!-- FINANCE -->
    <template v-if="fin">
      <h2 class="group-title">الماليات</h2>
      <section class="tiles" aria-label="مؤشرات مالية">
        <div class="tile"><span class="t-label">تم تحصيله في الفترة</span><b>{{ formatMoney(fin.kpis.collected_in_period) }}</b></div>
        <div class="tile"><span class="t-label">فواتير صادرة في الفترة</span><b>{{ formatMoney(fin.kpis.invoiced_in_period) }}</b><span class="t-sub">تكلفة القطع {{ formatMoney(fin.kpis.parts_cost_in_period) }}</span></div>
        <RouterLink :to="{ name: 'invoices' }" class="tile" :class="{ attention: Number(fin.kpis.outstanding) > 0 }">
          <span class="t-label">لم يُحصَّل بعد</span><b>{{ formatMoney(fin.kpis.outstanding) }}</b><span class="t-sub">في <span class="num">{{ fin.kpis.outstanding_invoices }}</span> فاتورة</span>
        </RouterLink>
        <RouterLink :to="{ name: 'invoices' }" class="tile"><span class="t-label">مكتملة بدون فاتورة</span><b class="num">{{ fin.kpis.awaiting_invoice }}</b><span class="t-sub">بانتظار المحاسب</span></RouterLink>
      </section>

      <section class="panel card">
        <div class="card-head">
          <h2>الإيرادات الشهرية</h2>
          <div class="legend">
            <span><i class="sw s1"></i>فواتير صادرة</span>
            <span><i class="sw s2"></i>مبالغ محصلة</span>
            <button type="button" class="btn-sf quiet mini" :aria-pressed="revenueAsTable" @click="revenueAsTable = !revenueAsTable">{{ revenueAsTable ? 'عرض كرسم' : 'عرض كجدول' }}</button>
          </div>
        </div>
        <table v-if="revenueAsTable" class="table-sf">
          <thead><tr><th>الشهر</th><th>فواتير صادرة</th><th>مبالغ محصلة</th></tr></thead>
          <tbody><tr v-for="m in fin.monthly_revenue" :key="m.month"><td>{{ monthName(m.month) }} <span class="faint num">{{ m.month }}</span></td><td class="num">{{ formatMoney(m.invoiced) }}</td><td class="num">{{ formatMoney(m.collected) }}</td></tr></tbody>
        </table>
        <ChartCanvas v-else :build="revenueChart" :height="240" label="أعمدة بالفواتير الصادرة والمبالغ المحصلة لكل شهر" />
      </section>
    </template>

    <!-- PEOPLE & STOCK -->
    <template v-if="ops">
      <section class="panel card">
        <div class="card-head"><h2>أداء الفنيين</h2><span class="faint">المنفّذ والإيراد في آخر {{ days }} يوم، المفتوح الآن</span></div>
        <div class="table-wrap">
          <table class="table-sf techs">
            <thead><tr><th>الفني</th><th>اتنفذ</th><th>في الموعد</th><th>مفتوح الآن</th><th>متأخر</th><th>ساعات مسجلة</th><th>إيراد مرتبط</th></tr></thead>
            <tbody>
              <tr v-for="t in ops.technicians" :key="t.id">
                <td><b>{{ t.name }}</b></td>
                <td><span class="inline-bar"><span :style="{ width: (t.completed / maxCompleted) * 100 + '%' }"></span></span><span class="num">{{ t.completed }}</span></td>
                <td class="num">{{ t.on_time_rate !== null ? `${t.on_time_rate}%` : '—' }}</td>
                <td class="num">{{ t.open }}</td>
                <td class="num" :class="{ 'late-text': t.overdue }">{{ t.overdue }}</td>
                <td class="num">{{ Number(t.hours_logged) }}</td>
                <td class="num">{{ formatMoney(t.revenue) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <div class="row one-one">
        <section class="panel card">
          <div class="card-head">
            <h2>أكثر قطع الغيار صرفًا</h2>
            <RouterLink v-if="auth.can('spare-parts.manage')" :to="{ name: 'inventory' }" class="small" :class="ops.low_stock_count ? 'late-text' : 'faint'">{{ ops.low_stock_count ? `${ops.low_stock_count} قطع تحت الحد الأدنى` : 'المخزون' }}</RouterLink>
          </div>
          <table class="table-sf">
            <thead><tr><th>القطعة</th><th>المصروف</th><th>الرصيد</th></tr></thead>
            <tbody>
              <tr v-for="p in ops.parts_usage" :key="p.sku"><td>{{ p.name }}</td><td class="num">{{ p.quantity }}</td><td class="num" :class="{ 'late-text': p.is_low }">{{ p.stock }}</td></tr>
              <tr v-if="!ops.parts_usage.length"><td colspan="3" class="faint">مفيش قطع اتصرفت في الفترة دي.</td></tr>
            </tbody>
          </table>
        </section>
        <section class="panel card">
          <div class="card-head"><h2>أكثر العملاء طلبًا</h2></div>
          <ol class="ranked">
            <li v-for="c in ops.top_customers" :key="c.id">
              <RouterLink :to="{ name: 'customer-details', params: { id: c.id } }">{{ c.name }}</RouterLink>
              <span class="faint"><span class="num">{{ c.requests }}</span> طلب</span>
            </li>
          </ol>
          <p v-if="!ops.top_customers.length" class="faint">مفيش طلبات في الفترة دي.</p>
        </section>
      </div>
    </template>

    <p class="faint small foot">الفترة: {{ formatDate(report.period.from) }} – {{ formatDate(report.period.to) }}</p>
  </template>

  <div v-else class="tiles"><div v-for="n in 4" :key="n" class="tile"><div class="skeleton w-50 mb-2"></div><div class="skeleton w-25"></div></div></div>
</template>

<style scoped>
.page-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; }
.page-head h1 { font-size: var(--fs-2xl); }
.period { display: inline-flex; background: var(--surface); border: 1px solid var(--line); border-radius: var(--r-sm); padding: 3px; }
.period button { border: 0; background: transparent; padding: 6px 14px; border-radius: 4px; color: var(--ink-2); font-weight: 500; font-size: var(--fs-sm); }
.period button.on { background: var(--ink); color: var(--paper); }

.tiles { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); background: var(--surface); border: 1px solid var(--line); border-radius: var(--r-md); margin-bottom: 20px; overflow: hidden; }
.tile { display: grid; gap: 2px; padding: 18px 20px; color: var(--ink); text-decoration: none; position: relative; }
.tile + .tile { border-inline-start: 1px solid var(--line); }
a.tile:hover { background: var(--paper); }
.tile b { font-size: var(--fs-2xl); line-height: 1.2; }
.t-label { font-size: var(--fs-sm); color: var(--ink-2); font-weight: 500; }
.t-sub { font-size: var(--fs-xs); color: var(--ink-3); }
.tile.late { box-shadow: inset 0 3px 0 var(--late); }
.tile.late b { color: var(--late); }
.tile.attention { box-shadow: inset 0 3px 0 var(--signal); }

.row { display: grid; gap: 20px; margin-bottom: 20px; }
.two-one { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); }
.one-one { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
.card { padding: 18px 20px; margin-bottom: 20px; }
.row .card { margin-bottom: 0; }
.card-head { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; margin-bottom: 14px; flex-wrap: wrap; }
.card-head h2 { font-size: var(--fs-md); }
.small { font-size: var(--fs-xs); }
.group-title { font-size: var(--fs-lg); margin: 8px 0 12px; }

.hbars { list-style: none; margin: 0; padding: 0; display: grid; gap: 12px; }
.hbars li { display: grid; grid-template-columns: 110px minmax(0, 1fr) 36px; gap: 10px; align-items: center; font-size: var(--fs-sm); }
.hb-label { color: var(--ink-2); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.hb-track { height: 10px; background: var(--paper); border-radius: 0 4px 4px 0; overflow: hidden; }
.hb-fill { display: block; height: 100%; border-radius: 4px 0 0 4px; background: var(--ink); transition: width .5s ease; }
.hb-fill.signal { background: var(--signal); }
.hb-fill.wait { background: var(--wait); }
.hb-fill.series { background: var(--series-1); }
.hbars b { text-align: end; }

.attention-list { list-style: none; margin: 0; padding: 0; }
.attention-list li + li { border-top: 1px solid var(--line); }
.attention-list a { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 10px 0; text-decoration: none; color: var(--ink); }
.attention-list a:hover b { color: var(--signal); }
.a-main { display: grid; line-height: 1.4; min-width: 0; }
.a-main span { font-size: var(--fs-sm); color: var(--ink-2); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.a-side { display: grid; justify-items: end; gap: 2px; flex: none; }
.late-text { color: var(--late); font-weight: 600; }
.empty-ok { color: var(--done); font-weight: 500; }

.legend { display: flex; align-items: center; gap: 14px; font-size: var(--fs-sm); color: var(--ink-2); flex-wrap: wrap; }
.legend span { display: inline-flex; align-items: center; gap: 6px; }
.sw { width: 12px; height: 12px; border-radius: 3px; display: inline-block; }
.sw.s1 { background: var(--series-1); }
.sw.s2 { background: var(--series-2); }
.mini { min-height: 30px; padding: 2px 10px; font-size: var(--fs-xs); }

.techs td { white-space: nowrap; }
.inline-bar { display: inline-block; width: 70px; height: 6px; background: var(--paper); border-radius: 3px; margin-inline-end: 8px; vertical-align: middle; overflow: hidden; }
.inline-bar span { display: block; height: 100%; background: var(--series-1); border-radius: 3px; }
.ranked { margin: 0; padding-inline-start: 20px; display: grid; gap: 8px; }
.ranked li { display: list-item; }
.ranked li a { margin-inline-end: 8px; }
.foot { margin-top: 4px; }

@media (max-width: 1100px) { .tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); } .tile:nth-child(3) { border-inline-start: 0; } .tile:nth-child(n+3) { border-top: 1px solid var(--line); } }
@media (max-width: 960px) { .two-one, .one-one { grid-template-columns: minmax(0, 1fr); } }
@media (max-width: 520px) { .tiles { grid-template-columns: minmax(0, 1fr); } .tile + .tile { border-inline-start: 0; border-top: 1px solid var(--line); } .hbars li { grid-template-columns: 90px minmax(0, 1fr) 30px; } }
</style>
