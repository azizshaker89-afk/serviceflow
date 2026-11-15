<script setup>
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { http, apiError } from '@/lib/http'
import { useToastStore } from '@/stores/toast'
import AppDialog from '@/components/AppDialog.vue'

/**
 * Renders exactly the actions the API returned in allowed_actions, each wired to its endpoint.
 * The API re-authorizes every call; hiding a button here is only a convenience.
 */
const props = defineProps({ request: { type: Object, required: true } })
const emit = defineEmits(['updated', 'deleted'])
const toast = useToastStore()
const router = useRouter()

const allowed = computed(() => new Set(props.request.allowed_actions))
const hasWork = computed(() => props.request.work_logs.length > 0)
const hasParts = computed(() => props.request.parts.length > 0)

/** Primary = the step that moves the request forward; shown in signal orange. */
const BUTTONS = [
  { key: 'assign', label: () => (props.request.technician ? 'إعادة التعيين لفني آخر' : 'تعيين فني'), primary: () => !props.request.technician },
  { key: 'start', label: () => 'استلام المهمة وبدء العمل', primary: () => true },
  { key: 'resume', label: () => 'وصلت القطع، استكمال العمل', primary: () => true },
  { key: 'complete', label: () => 'إنهاء الصيانة', primary: () => true },
  { key: 'issue_invoice', label: () => 'إصدار الفاتورة', primary: () => true },
  { key: 'log_work', label: () => 'تسجيل ساعات عمل' },
  { key: 'manage_parts', label: () => 'صرف قطعة غيار' },
  { key: 'pause', label: () => 'إيقاف: بانتظار قطع' },
  { key: 'update', label: () => 'تعديل بيانات الطلب' },
  { key: 'cancel', label: () => 'إلغاء الطلب', danger: true },
  { key: 'delete', label: () => 'حذف الطلب', danger: true },
]
const buttons = computed(() => BUTTONS.filter((b) => allowed.value.has(b.key)))

const dialog = ref(null)
const busy = ref(false)
const errors = ref({})
const message = ref('')
const form = reactive({})
const technicians = ref([])
const categories = ref([])
const loadingList = ref(false)

const reset = (values = {}) => {
  Object.keys(form).forEach((k) => delete form[k])
  Object.assign(form, values)
  errors.value = {}
  message.value = ''
}

async function open(key) {
  if (key === 'start' || key === 'resume') {
    return run(`/service-requests/${props.request.id}/${key}`)
  }
  dialog.value = key
  if (key === 'assign') {
    reset({ technician_id: null, note: '' })
    loadingList.value = true
    try {
      const { data } = await http.get('/technicians')
      technicians.value = data.data.filter((t) => t.id !== props.request.technician?.id)
    } finally {
      loadingList.value = false
    }
  }
  if (key === 'manage_parts') {
    reset({ spare_part_id: null, quantity: 1 })
    partSearch.value = ''
    await loadParts()
  }
  if (key === 'issue_invoice') {
    reset({ discount: '', due_date: '', notes: '' })
    await loadPreview()
  }
  if (key === 'pause') reset({ waiting_for: '' })
  if (key === 'log_work') reset({ hours: 1, note: '' })
  if (key === 'complete') reset({ no_parts_confirmed: false, note: '' })
  if (key === 'cancel') reset({ reason: '' })
  if (key === 'delete') reset({})
  if (key === 'update') {
    reset({
      priority: props.request.priority,
      service_category_id: props.request.service_category_id,
      description: props.request.description,
      address: props.request.address ?? '',
      preferred_date: props.request.preferred_date ?? '',
    })
    if (!categories.value.length) {
      const { data } = await http.get('/service-categories')
      categories.value = data.data
    }
  }
}

const close = () => { if (!busy.value) dialog.value = null }

/* ---------- invoice preview: same calculator as the real invoice, nothing saved ---------- */
const preview = ref(null)
const previewError = ref('')
let previewTimer
async function loadPreview() {
  previewError.value = ''
  try {
    const { data } = await http.get(`/service-requests/${props.request.id}/invoice-preview`, { params: { discount: form.discount || 0 } })
    preview.value = data.data
  } catch (e) {
    previewError.value = apiError(e).errors?.discount?.[0] ?? apiError(e).message
  }
}
function onDiscountInput() {
  clearTimeout(previewTimer)
  previewTimer = setTimeout(loadPreview, 300)
}
async function issueInvoice() {
  busy.value = true
  errors.value = {}
  message.value = ''
  try {
    const payload = { service_request_id: props.request.id, discount: form.discount || 0, due_date: form.due_date || undefined, notes: form.notes || undefined }
    const { data } = await http.post('/invoices', payload)
    toast.show(data.message)
    dialog.value = null
    router.push({ name: 'invoice-details', params: { id: data.data.id } })
  } catch (e) {
    const err = apiError(e)
    errors.value = err.errors
    message.value = err.status === 422 && Object.keys(err.errors).length ? '' : err.message
  } finally {
    busy.value = false
  }
}
const money = (v) => `${Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ج.م`

const parts = ref([])
const partSearch = ref('')
let partTimer
async function loadParts() {
  loadingList.value = true
  try {
    const { data } = await http.get('/spare-parts', { params: { active: 1, per_page: 50, search: partSearch.value || undefined } })
    parts.value = data.data.items
  } finally {
    loadingList.value = false
  }
}
function onPartSearch() {
  clearTimeout(partTimer)
  partTimer = setTimeout(loadParts, 300)
}
const selectedPart = computed(() => parts.value.find((p) => p.id === form.spare_part_id) ?? null)
const partOverStock = computed(() => selectedPart.value && form.quantity > selectedPart.value.stock_quantity)

async function run(url, payload = {}, method = 'post') {
  busy.value = true
  errors.value = {}
  message.value = ''
  try {
    const { data } = await http[method](url, payload)
    toast.show(data.message)
    dialog.value = null
    if (method === 'delete') emit('deleted')
    else emit('updated', data.data)
  } catch (e) {
    const err = apiError(e)
    errors.value = err.errors
    message.value = err.status === 422 && Object.keys(err.errors).length ? '' : err.message
    if (!dialog.value) toast.show(err.message, 'late')
  } finally {
    busy.value = false
  }
}

const base = () => `/service-requests/${props.request.id}`
const submit = {
  assign: () => run(`${base()}/assign`, form),
  manage_parts: () => run(`${base()}/parts`, form),
  pause: () => run(`${base()}/pause`, form),
  log_work: () => run(`${base()}/work-logs`, form),
  complete: () => run(`${base()}/complete`, form),
  cancel: () => run(`${base()}/cancel`, form),
  update: () => run(base(), { ...form, preferred_date: form.preferred_date || null, address: form.address || null }, 'put'),
  delete: () => run(base(), {}, 'delete'),
}

const err = (field) => errors.value[field]?.[0]
const canComplete = computed(() => hasWork.value && (hasParts.value || form.no_parts_confirmed))
const HOURS = [0.25, 0.5, 0.75, 1, 1.25, 1.5, 2, 2.5, 3, 4, 5, 6, 8]
</script>

<template>
  <div v-if="buttons.length" class="actions">
    <button
      v-for="b in buttons"
      :key="b.key"
      type="button"
      class="btn-sf"
      :class="{ signal: b.primary?.(), danger: b.danger }"
      :disabled="busy"
      @click="open(b.key)"
    >{{ b.label() }}</button>
  </div>
  <p v-else class="faint none">لا يوجد إجراء متاح لدورك في هذه المرحلة.</p>

  <AppDialog :open="dialog === 'assign'" title="اختيار الفني" width="520px" @close="close">
    <p class="muted">الفنيون مرتبون بالأقل انشغالًا. الرقم هو عدد طلباتهم المفتوحة الآن.</p>
    <div v-if="loadingList" class="skeleton"></div>
    <fieldset v-else class="tech-list">
      <legend class="visually-hidden">الفني</legend>
      <label v-for="t in technicians" :key="t.id" class="tech" :class="{ on: form.technician_id === t.id }">
        <input v-model="form.technician_id" type="radio" name="technician" :value="t.id" />
        <span class="tech-name"><b>{{ t.name }}</b><small>{{ t.specialization }}</small></span>
        <span class="load">
          <span class="num">{{ t.open_requests_count }}</span> مفتوحة
          <span v-if="t.overdue_requests_count" class="chip late tiny"><span class="num">{{ t.overdue_requests_count }}</span> متأخرة</span>
        </span>
      </label>
    </fieldset>
    <div class="invalid-feedback d-block">{{ err('technician_id') }}</div>
    <div>
      <label class="form-label" for="as-note">ملاحظة للفني <span class="faint">(اختياري)</span></label>
      <input id="as-note" v-model.trim="form.note" class="form-control" placeholder="مثال: العميل متاح بعد 3 العصر" />
    </div>
    <p v-if="message" class="alert-sf late">{{ message }}</p>
    <template #footer>
      <button type="button" class="btn-sf signal" :disabled="busy || !form.technician_id" @click="submit.assign">{{ request.technician ? 'إعادة التعيين' : 'تعيين الفني' }}</button>
      <button type="button" class="btn-sf quiet" @click="close">تراجع</button>
    </template>
  </AppDialog>

  <AppDialog :open="dialog === 'manage_parts'" title="صرف قطعة غيار من المخزن" width="560px" @close="close">
    <div>
      <label class="form-label" for="mp-search">ابحث بالاسم أو الكود</label>
      <input id="mp-search" v-model.trim="partSearch" class="form-control" autocomplete="off" placeholder="مثال: فريون أو AC-" @input="onPartSearch" />
    </div>
    <div v-if="loadingList" class="skeleton"></div>
    <fieldset v-else class="tech-list" :class="{ 'is-invalid': err('spare_part_id') }">
      <legend class="visually-hidden">القطعة</legend>
      <label v-for="p in parts" :key="p.id" class="tech" :class="{ on: form.spare_part_id === p.id, out: p.stock_quantity === 0 }">
        <input v-model="form.spare_part_id" type="radio" name="part" :value="p.id" :disabled="p.stock_quantity === 0" />
        <span class="tech-name"><b>{{ p.name }}</b><small class="num">{{ p.sku }}</small></span>
        <span class="load">
          <span class="num">{{ Number(p.unit_price).toLocaleString('en-US') }}</span> ج.م
          <span class="chip tiny" :class="{ late: p.stock_quantity === 0, wait: p.is_low_stock && p.stock_quantity > 0 }">متاح <span class="num">{{ p.stock_quantity }}</span></span>
        </span>
      </label>
      <p v-if="!parts.length" class="faint m-0">مفيش قطع بالاسم ده.</p>
    </fieldset>
    <div class="invalid-feedback d-block">{{ err('spare_part_id') }}</div>
    <div class="qty-row">
      <div>
        <label class="form-label" for="mp-qty">الكمية</label>
        <input id="mp-qty" v-model.number="form.quantity" type="number" min="1" max="100" class="form-control num" :class="{ 'is-invalid': err('quantity') || partOverStock }" />
      </div>
      <p v-if="selectedPart" class="line-total">
        الإجمالي <b class="num">{{ (Number(selectedPart.unit_price) * (form.quantity || 0)).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}</b> ج.م
      </p>
    </div>
    <p v-if="partOverStock" class="alert-sf wait">المتاح من «{{ selectedPart.name }}» {{ selectedPart.stock_quantity }} فقط. الخادم سيرفض كمية أكبر.</p>
    <p class="form-text">السعر يُنقل من المخزن ويُثبَّت لحظة الصرف، والكمية تُخصم من الرصيد في نفس العملية.</p>
    <p v-if="message" class="alert-sf late">{{ message }}</p>
    <template #footer>
      <button type="button" class="btn-sf ink" :disabled="busy || !form.spare_part_id" @click="submit.manage_parts">صرف القطعة</button>
      <button type="button" class="btn-sf quiet" @click="close">تراجع</button>
    </template>
  </AppDialog>

  <AppDialog :open="dialog === 'issue_invoice'" :title="`فاتورة الطلب ${request.code}`" width="560px" @close="close">
    <p class="muted">البنود تأتي من القطع المصروفة وساعات العمل المسجلة، والضريبة من الإعدادات. القرار الوحيد هنا هو الخصم.</p>
    <table v-if="preview" class="table-sf preview">
      <thead><tr><th>البند</th><th>الكمية</th><th>السعر</th><th>الإجمالي</th></tr></thead>
      <tbody>
        <tr v-for="(l, i) in preview.lines" :key="i"><td>{{ l.description }}</td><td class="num">{{ Number(l.quantity) }}</td><td class="num">{{ money(l.unit_price) }}</td><td class="num">{{ money(l.total) }}</td></tr>
      </tbody>
      <tfoot>
        <tr><td colspan="3">قبل الخصم والضريبة</td><td class="num">{{ money(preview.subtotal) }}</td></tr>
        <tr><td colspan="3">الخصم</td><td class="num">− {{ money(preview.discount) }}</td></tr>
        <tr><td colspan="3">ضريبة القيمة المضافة <span class="num">{{ Number(preview.tax_rate) }}%</span> بعد الخصم</td><td class="num">{{ money(preview.tax_amount) }}</td></tr>
        <tr class="grand"><td colspan="3">الإجمالي</td><td class="num">{{ money(preview.total) }}</td></tr>
      </tfoot>
    </table>
    <div v-else class="skeleton"></div>
    <div class="two">
      <div>
        <label class="form-label" for="iv-discount">الخصم (ج.م)</label>
        <input id="iv-discount" v-model="form.discount" inputmode="decimal" class="form-control num" :class="{ 'is-invalid': previewError || err('discount') }" placeholder="0" @input="onDiscountInput" />
        <div class="invalid-feedback">{{ previewError || err('discount') }}</div>
      </div>
      <div>
        <label class="form-label" for="iv-due">تاريخ الاستحقاق</label>
        <input id="iv-due" v-model="form.due_date" type="date" class="form-control" :class="{ 'is-invalid': err('due_date') }" />
        <div class="form-text">افتراضيًا بعد 7 أيام</div>
      </div>
    </div>
    <p v-if="message" class="alert-sf late">{{ message }}</p>
    <template #footer>
      <button type="button" class="btn-sf signal" :disabled="busy || !!previewError || !preview" @click="issueInvoice">إصدار الفاتورة</button>
      <button type="button" class="btn-sf quiet" @click="close">تراجع</button>
    </template>
  </AppDialog>

  <AppDialog :open="dialog === 'pause'" title="إيقاف العمل لحين وصول قطعة" @close="close">
    <div>
      <label class="form-label" for="pa-for">ما القطعة المنتظرة؟</label>
      <input id="pa-for" v-model.trim="form.waiting_for" class="form-control" :class="{ 'is-invalid': err('waiting_for') }" placeholder="مثال: موتور مروحة وحدة خارجية" />
      <div class="invalid-feedback">{{ err('waiting_for') }}</div>
      <p class="form-text">يظهر السبب للمدير في سجل الطلب، ويتوقف احتساب التنفيذ حتى تستكمل.</p>
    </div>
    <p v-if="message" class="alert-sf late">{{ message }}</p>
    <template #footer>
      <button type="button" class="btn-sf ink" :disabled="busy" @click="submit.pause">إيقاف العمل</button>
      <button type="button" class="btn-sf quiet" @click="close">تراجع</button>
    </template>
  </AppDialog>

  <AppDialog :open="dialog === 'log_work'" title="تسجيل ساعات عمل" @close="close">
    <div>
      <label class="form-label" for="lw-hours">عدد الساعات</label>
      <select id="lw-hours" v-model.number="form.hours" class="form-select" :class="{ 'is-invalid': err('hours') }">
        <option v-for="h in HOURS" :key="h" :value="h">{{ h }} ساعة</option>
      </select>
      <div class="invalid-feedback">{{ err('hours') }}</div>
    </div>
    <div>
      <label class="form-label" for="lw-note">ملاحظة فنية</label>
      <textarea id="lw-note" v-model.trim="form.note" rows="3" class="form-control" placeholder="مثال: تغيير مكثف وشحن فريون وتنظيف الوحدة"></textarea>
    </div>
    <p class="form-text">تُحسب الساعات بسعر ساعتك الحالي، ويُحفظ السعر مع التسجيل فلا يتغير لاحقًا.</p>
    <p v-if="message" class="alert-sf late">{{ message }}</p>
    <template #footer>
      <button type="button" class="btn-sf ink" :disabled="busy" @click="submit.log_work">تسجيل الساعات</button>
      <button type="button" class="btn-sf quiet" @click="close">تراجع</button>
    </template>
  </AppDialog>

  <AppDialog :open="dialog === 'complete'" title="إنهاء الصيانة" @close="close">
    <ul class="checklist">
      <li :class="{ ok: hasWork }"><span class="tick" aria-hidden="true">{{ hasWork ? '✓' : '' }}</span>ساعات العمل مسجلة<span class="faint"> ({{ hasWork ? `${request.totals.hours} س` : 'لم تُسجَّل بعد' }})</span></li>
      <li :class="{ ok: hasParts || form.no_parts_confirmed }">
        <span class="tick" aria-hidden="true">{{ hasParts || form.no_parts_confirmed ? '✓' : '' }}</span>
        <template v-if="hasParts">قطع الغيار مسجلة ({{ request.parts.length }} صنف)</template>
        <label v-else class="form-check m-0">
          <input v-model="form.no_parts_confirmed" class="form-check-input" type="checkbox" />
          <span class="form-check-label">أؤكد أن الصيانة لم تحتج أي قطع غيار</span>
        </label>
      </li>
    </ul>
    <div>
      <label class="form-label" for="cm-note">ملاحظة ختامية <span class="faint">(اختياري)</span></label>
      <input id="cm-note" v-model.trim="form.note" class="form-control" placeholder="مثال: تمت التجربة أمام العميل" />
    </div>
    <p v-if="!hasWork" class="alert-sf wait">سجّل ساعات العمل أولًا من زر «تسجيل ساعات عمل».</p>
    <p v-if="message" class="alert-sf late">{{ message }}</p>
    <template #footer>
      <button type="button" class="btn-sf signal" :disabled="busy || !canComplete" @click="submit.complete">إنهاء الصيانة</button>
      <button type="button" class="btn-sf quiet" @click="close">تراجع</button>
    </template>
  </AppDialog>

  <AppDialog :open="dialog === 'cancel'" title="إلغاء الطلب" @close="close">
    <p v-if="hasParts" class="alert-sf wait">سيُعاد للمخزن: {{ request.parts.map((p) => `${p.quantity} × ${p.name}`).join('، ') }}</p>
    <div>
      <label class="form-label" for="cn-reason">سبب الإلغاء</label>
      <textarea id="cn-reason" v-model.trim="form.reason" rows="2" class="form-control" :class="{ 'is-invalid': err('reason') }" placeholder="مثال: العميل اعتذر عن الزيارة"></textarea>
      <div class="invalid-feedback">{{ err('reason') }}</div>
    </div>
    <p class="form-text">الإلغاء نهائي، ويظهر السبب في سجل الطلب وفي صفحة متابعة العميل.</p>
    <p v-if="message" class="alert-sf late">{{ message }}</p>
    <template #footer>
      <button type="button" class="btn-sf danger" :disabled="busy" @click="submit.cancel">إلغاء الطلب</button>
      <button type="button" class="btn-sf quiet" @click="close">تراجع</button>
    </template>
  </AppDialog>

  <AppDialog :open="dialog === 'update'" title="تعديل بيانات الطلب" width="540px" @close="close">
    <div class="two">
      <div>
        <label class="form-label" for="up-priority">الأولوية</label>
        <select id="up-priority" v-model="form.priority" class="form-select">
          <option value="urgent">عاجلة (4 ساعات)</option>
          <option value="high">عالية (24 ساعة)</option>
          <option value="normal">عادية (48 ساعة)</option>
          <option value="low">منخفضة (72 ساعة)</option>
        </select>
      </div>
      <div>
        <label class="form-label" for="up-cat">نوع الخدمة</label>
        <select id="up-cat" v-model.number="form.service_category_id" class="form-select">
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>
    </div>
    <div>
      <label class="form-label" for="up-desc">وصف العطل</label>
      <textarea id="up-desc" v-model.trim="form.description" rows="3" class="form-control" :class="{ 'is-invalid': err('description') }"></textarea>
      <div class="invalid-feedback">{{ err('description') }}</div>
    </div>
    <div class="two">
      <div>
        <label class="form-label" for="up-addr">العنوان</label>
        <input id="up-addr" v-model.trim="form.address" class="form-control" />
      </div>
      <div>
        <label class="form-label" for="up-date">الموعد المفضل</label>
        <input id="up-date" v-model="form.preferred_date" type="date" class="form-control" />
      </div>
    </div>
    <p class="form-text">تغيير الأولوية يعيد حساب الموعد النهائي من وقت استلام الطلب.</p>
    <p v-if="message" class="alert-sf late">{{ message }}</p>
    <template #footer>
      <button type="button" class="btn-sf ink" :disabled="busy" @click="submit.update">حفظ التعديلات</button>
      <button type="button" class="btn-sf quiet" @click="close">تراجع</button>
    </template>
  </AppDialog>

  <AppDialog :open="dialog === 'delete'" title="حذف الطلب" @close="close">
    <p>سيُحذف الطلب <b class="num">{{ request.code }}</b> من القوائم. استخدم الحذف للطلبات المكررة أو المسجلة بالخطأ فقط.</p>
    <p v-if="message" class="alert-sf late">{{ message }}</p>
    <template #footer>
      <button type="button" class="btn-sf danger" :disabled="busy" @click="submit.delete">حذف الطلب</button>
      <button type="button" class="btn-sf quiet" @click="close">تراجع</button>
    </template>
  </AppDialog>
</template>

<style scoped>
.actions { display: grid; gap: 8px; margin-top: 14px; }
.none { margin-top: 12px; font-size: var(--fs-sm); }
.btn-sf.danger { color: var(--late); }
.btn-sf.danger:hover { border-color: var(--late); }
.tech-list { display: grid; gap: 6px; border: 0; padding: 0; margin: 0; max-height: 320px; overflow-y: auto; }
.tech { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid var(--line); border-radius: var(--r-sm); cursor: pointer; }
.tech.on { border-color: var(--ink); box-shadow: inset 0 0 0 1px var(--ink); }
.tech input { accent-color: var(--ink); }
.tech-name { display: grid; line-height: 1.35; }
.tech-name small { color: var(--ink-3); }
.load { margin-inline-start: auto; font-size: var(--fs-sm); color: var(--ink-2); display: flex; gap: 6px; align-items: center; white-space: nowrap; }
.tiny { font-size: var(--fs-xs); padding: 0 8px; }
.tech.out { opacity: .5; cursor: not-allowed; }
.preview td, .preview th { padding: 7px 8px; }
.preview tfoot td { border-bottom: 0; color: var(--ink-2); padding-block: 4px; }
.preview tfoot tr:first-child td { border-top: 1px solid var(--line); padding-top: 10px; }
.preview .grand td { color: var(--ink); font-weight: 700; font-size: var(--fs-lg); border-top: 1px solid var(--ink); padding-top: 8px; }
.qty-row { display: flex; align-items: flex-end; gap: 16px; }
.qty-row > div { width: 120px; }
.line-total { margin: 0 0 10px; color: var(--ink-2); }
.checklist { list-style: none; padding: 0; margin: 0; display: grid; gap: 10px; }
.checklist li { display: flex; gap: 10px; align-items: center; color: var(--ink-2); }
.checklist li.ok { color: var(--done); }
.tick { width: 22px; height: 22px; border-radius: 50%; border: 1.5px solid currentColor; display: grid; place-items: center; flex: none; font-size: 12px; font-weight: 700; }
.two { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 520px) { .two { grid-template-columns: 1fr; } }
</style>
