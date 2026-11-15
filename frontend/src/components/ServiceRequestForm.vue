<script setup>
import { reactive, ref, watch } from 'vue'
import { http, apiError } from '@/lib/http'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  preselectedCategory: { type: Number, default: null },
})

const blank = () => ({
  name: '', phone: '', service_category_id: props.preselectedCategory ?? '', description: '',
  address: '', city: '', preferred_date: '', website: '',
})
const form = reactive(blank())
const errors = ref({})
const message = ref('')
const submitting = ref(false)
const submitted = ref(null)
const copied = ref(false)
const today = new Date().toISOString().slice(0, 10)

watch(() => props.preselectedCategory, (id) => { if (id) form.service_category_id = id })

async function submit() {
  submitting.value = true
  errors.value = {}
  message.value = ''
  try {
    const payload = Object.fromEntries(Object.entries(form).filter(([, v]) => v !== ''))
    const { data } = await http.post('/public/service-requests', payload)
    submitted.value = { code: data.data.code, phone: form.phone }
  } catch (e) {
    const err = apiError(e)
    errors.value = err.errors
    message.value = err.status === 422 ? 'راجع الحقول المظللة بالأحمر.' : err.message
  } finally {
    submitting.value = false
  }
}

async function copyCode() {
  try {
    await navigator.clipboard.writeText(submitted.value.code)
    copied.value = true
    setTimeout(() => (copied.value = false), 2000)
  } catch {
    copied.value = false
  }
}

function reset() {
  Object.assign(form, blank())
  submitted.value = null
}

const err = (field) => errors.value[field]?.[0]
</script>

<template>
  <div class="job-card panel">
    <div class="card-head">
      <h3>{{ submitted ? 'استلمنا طلبك' : 'طلب صيانة جديد' }}</h3>
      <span class="faint">{{ submitted ? 'رقم الطلب جاهز للمتابعة' : 'بدون تسجيل حساب · دقيقتين' }}</span>
    </div>

    <div v-if="submitted" class="done">
      <p class="muted">احتفظ برقم الطلب. هتحتاجه مع رقم موبايلك عشان تتابع الفني خطوة بخطوة.</p>
      <div class="code-box">
        <span class="code num">{{ submitted.code }}</span>
        <button type="button" class="btn-sf" @click="copyCode">{{ copied ? 'تم النسخ' : 'نسخ الرقم' }}</button>
      </div>
      <ol class="next">
        <li>مدير التشغيل هيراجع طلبك ويعيّن فني متخصص.</li>
        <li>الفني هيكلمك على <span class="num">{{ submitted.phone }}</span> لتأكيد ميعاد الزيارة.</li>
        <li>تقدر تشوف كل خطوة من صفحة المتابعة.</li>
      </ol>
      <div class="done-actions">
        <RouterLink class="btn-sf signal" :to="{ name: 'track', query: { code: submitted.code, phone: submitted.phone } }">متابعة الطلب</RouterLink>
        <button type="button" class="btn-sf quiet" @click="reset">إرسال طلب آخر</button>
      </div>
    </div>

    <form v-else novalidate @submit.prevent="submit">
      <fieldset class="step">
        <legend><span class="n num">1</span>بياناتك</legend>
        <div class="grid">
          <div class="field">
            <label class="form-label" for="rf-name">الاسم</label>
            <input id="rf-name" v-model.trim="form.name" class="form-control" :class="{ 'is-invalid': err('name') }" autocomplete="name" required />
            <div class="invalid-feedback">{{ err('name') }}</div>
          </div>
          <div class="field">
            <label class="form-label" for="rf-phone">رقم الموبايل</label>
            <input id="rf-phone" v-model.trim="form.phone" class="form-control num" :class="{ 'is-invalid': err('phone') }" inputmode="tel" autocomplete="tel" placeholder="01XXXXXXXXX" required />
            <div class="invalid-feedback">{{ err('phone') }}</div>
          </div>
        </div>
      </fieldset>

      <fieldset class="step">
        <legend><span class="n num">2</span>العطل</legend>
        <div class="grid">
          <div class="field wide">
            <label class="form-label" for="rf-category">نوع الخدمة</label>
            <select id="rf-category" v-model.number="form.service_category_id" class="form-select" :class="{ 'is-invalid': err('service_category_id') }" required>
              <option value="" disabled>اختر نوع العطل</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <div class="invalid-feedback">{{ err('service_category_id') }}</div>
          </div>
          <div class="field wide">
            <label class="form-label" for="rf-desc">اوصف المشكلة</label>
            <textarea id="rf-desc" v-model.trim="form.description" class="form-control" :class="{ 'is-invalid': err('description') }" rows="3" placeholder="مثال: التكييف شغال لكن الهوا مش بارد، وفيه صوت عالي من الوحدة الخارجية" required></textarea>
            <div class="invalid-feedback">{{ err('description') }}</div>
          </div>
        </div>
      </fieldset>

      <fieldset class="step">
        <legend><span class="n num">3</span>المكان والميعاد</legend>
        <div class="grid">
          <div class="field wide">
            <label class="form-label" for="rf-address">العنوان</label>
            <input id="rf-address" v-model.trim="form.address" class="form-control" :class="{ 'is-invalid': err('address') }" autocomplete="street-address" placeholder="المنطقة، الشارع، رقم العمارة والشقة" required />
            <div class="invalid-feedback">{{ err('address') }}</div>
          </div>
          <div class="field">
            <label class="form-label" for="rf-city">المدينة <span class="faint">(اختياري)</span></label>
            <input id="rf-city" v-model.trim="form.city" class="form-control" autocomplete="address-level2" />
          </div>
          <div class="field">
            <label class="form-label" for="rf-date">ميعاد يناسبك <span class="faint">(اختياري)</span></label>
            <input id="rf-date" v-model="form.preferred_date" type="date" class="form-control" :class="{ 'is-invalid': err('preferred_date') }" :min="today" />
            <div class="invalid-feedback">{{ err('preferred_date') }}</div>
          </div>
        </div>
      </fieldset>

      <div class="hp" aria-hidden="true">
        <label for="rf-website">اترك هذا الحقل فارغًا</label>
        <input id="rf-website" v-model="form.website" tabindex="-1" autocomplete="off" />
      </div>

      <div class="submit">
        <p v-if="message" class="alert-sf late" role="alert">{{ message }}</p>
        <button type="submit" class="btn-sf signal lg" :disabled="submitting">{{ submitting ? 'جارٍ الإرسال…' : 'إرسال الطلب' }}</button>
        <p class="faint fine">بإرسال الطلب، هنستخدم رقمك للتواصل بخصوص الطلب ده بس.</p>
      </div>
    </form>
  </div>
</template>

<style scoped>
.job-card { overflow: hidden; }
.card-head { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; padding: 18px 22px; border-bottom: 1px dashed var(--rail); flex-wrap: wrap; background: var(--surface); }
.card-head h3 { font-size: var(--fs-lg); }
.card-head span { font-size: var(--fs-sm); }
.step { border: 0; margin: 0; padding: 18px 22px 4px; }
.step + .step { border-top: 1px solid var(--line); }
.step legend { float: none; width: auto; display: flex; align-items: center; gap: 10px; font-size: var(--fs-md); font-weight: 700; margin-bottom: 12px; padding: 0; }
.n { width: 26px; height: 26px; border-radius: 50%; background: var(--ink); color: var(--paper); display: inline-grid; place-items: center; font-size: var(--fs-sm); }
.grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 14px; padding-bottom: 14px; }
.field { display: grid; min-width: 0; }
.wide { grid-column: 1 / -1; }
.hp { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
.submit { padding: 16px 22px 22px; border-top: 1px solid var(--line); display: grid; gap: 10px; }
.fine { font-size: var(--fs-xs); text-align: center; }
.done { padding: 22px; display: grid; gap: 16px; }
.code-box { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 16px; border: 1px dashed var(--ink-3); border-radius: var(--r-sm); background: var(--paper); flex-wrap: wrap; }
.code { font-size: var(--fs-xl); font-weight: 700; letter-spacing: .03em; user-select: all; }
.next { margin: 0; padding-inline-start: 20px; display: grid; gap: 6px; color: var(--ink-2); }
.done-actions { display: flex; gap: 8px; flex-wrap: wrap; }
@media (max-width: 520px) { .grid { grid-template-columns: 1fr; } .step { padding-inline: 16px; } }
</style>
