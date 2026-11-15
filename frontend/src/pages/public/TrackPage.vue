<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { http, apiError } from '@/lib/http'
import { formatDate, formatMoney } from '@/lib/format'
import StatusChip from '@/components/StatusChip.vue'
import StatusRail from '@/components/StatusRail.vue'

const route = useRoute()
const router = useRouter()
const form = reactive({ code: route.query.code ?? '', phone: route.query.phone ?? '' })
const result = ref(null)
const error = ref('')
const fieldErrors = ref({})
const loading = ref(false)

const NEXT_STEP = {
  new: 'طلبك وصل وبيتراجع. هيتعيّن له فني متخصص قريب.',
  assigned: 'اتعيّن فني لطلبك وهيتواصل معاك لتأكيد الزيارة.',
  in_progress: 'الفني شغال على العطل دلوقتي.',
  waiting_parts: 'الشغل متوقف مؤقتًا لحد ما توصل قطعة غيار.',
  completed: 'الصيانة خلصت. الفاتورة هتوصلك مفصلة.',
  cancelled: 'الطلب ده اتلغى. لو محتاج الخدمة تاني ابعت طلب جديد.',
}

async function lookup() {
  loading.value = true
  error.value = ''
  fieldErrors.value = {}
  try {
    const { data } = await http.post('/public/service-requests/track', form)
    result.value = data.data
    router.replace({ query: { code: data.data.code, phone: form.phone } })
  } catch (e) {
    const err = apiError(e)
    result.value = null
    fieldErrors.value = err.errors
    error.value = err.status === 422 ? '' : err.message
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  if (form.code && form.phone) lookup()
})
</script>

<template>
  <div class="container-sf page">
    <div class="intro">
      <h1>تابع طلبك</h1>
      <p class="muted">اكتب رقم الطلب ورقم الموبايل اللي سجلت بيه. لازم الاتنين يتطابقوا عشان محدش غيرك يشوف طلبك.</p>
    </div>

    <form class="lookup panel" novalidate @submit.prevent="lookup">
      <div class="field">
        <label class="form-label" for="t-code">رقم الطلب</label>
        <input id="t-code" v-model.trim="form.code" class="form-control num" :class="{ 'is-invalid': fieldErrors.code }" placeholder="SR-2026-00014" required />
        <div class="invalid-feedback">{{ fieldErrors.code?.[0] }}</div>
      </div>
      <div class="field">
        <label class="form-label" for="t-phone">رقم الموبايل</label>
        <input id="t-phone" v-model.trim="form.phone" class="form-control num" :class="{ 'is-invalid': fieldErrors.phone }" inputmode="tel" placeholder="01XXXXXXXXX" required />
        <div class="invalid-feedback">{{ fieldErrors.phone?.[0] }}</div>
      </div>
      <button type="submit" class="btn-sf signal" :disabled="loading">{{ loading ? 'جارٍ البحث…' : 'عرض حالة الطلب' }}</button>
    </form>

    <p v-if="error" class="alert-sf late mt-3" role="alert">{{ error }}</p>

    <section v-if="result" class="result panel" aria-live="polite">
      <div class="panel-section head">
        <div>
          <span class="code num">{{ result.code }}</span>
          <h2>{{ result.category }}</h2>
          <p class="muted">اتسجل {{ formatDate(result.created_at) }}<template v-if="result.preferred_date"> · الموعد المفضل {{ formatDate(result.preferred_date) }}</template></p>
        </div>
        <StatusChip :status="result.status" :label="result.status_label" />
      </div>
      <div class="panel-section">
        <StatusRail :status="result.status" :history="result.timeline" :invoice="result.invoice" :show-names="false" />
      </div>
      <div class="panel-section next">
        <h3>الخطوة الجاية</h3>
        <p>{{ NEXT_STEP[result.status] }}</p>
      </div>
      <div v-if="result.invoice" class="panel-section invoice">
        <div><span class="faint">الفاتورة</span><b class="num">{{ result.invoice.number }}</b></div>
        <div><span class="faint">الإجمالي</span><b>{{ formatMoney(result.invoice.total) }}</b></div>
        <div><span class="faint">المتبقي</span><b>{{ formatMoney(result.invoice.balance_due) }}</b></div>
        <StatusChip :status="result.invoice.status === 'paid' ? 'completed' : 'waiting_parts'" :label="result.invoice.status_label" />
      </div>
    </section>
  </div>
</template>

<style scoped>
.page { padding-block: 48px 72px; display: grid; gap: 24px; max-width: 980px; }
.intro { display: grid; gap: 8px; max-width: 60ch; }
.intro h1 { font-size: var(--fs-2xl); }
.lookup { padding: 20px 22px; display: grid; grid-template-columns: 1fr 1fr auto; gap: 14px; align-items: end; }
.field { display: grid; min-width: 0; }
.lookup .btn-sf { margin-bottom: 0; }
.head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; }
.head .code { font-weight: 700; color: var(--ink-2); }
.head h2 { font-size: var(--fs-xl); margin-block: 2px 4px; }
.next h3 { font-size: var(--fs-md); margin-bottom: 4px; }
.invoice { display: flex; gap: 28px; align-items: center; flex-wrap: wrap; }
.invoice div { display: grid; }
.invoice b { font-size: var(--fs-lg); }
@media (max-width: 640px) { .lookup { grid-template-columns: minmax(0, 1fr); } }
</style>
