<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { http, apiError } from '@/lib/http'
import { useToastStore } from '@/stores/toast'
import AppDialog from '@/components/AppDialog.vue'

const toast = useToastStore()
const ROLES = [
  { value: 'admin', label: 'مدير النظام' },
  { value: 'receptionist', label: 'موظف استقبال' },
  { value: 'technician', label: 'فني صيانة' },
  { value: 'accountant', label: 'محاسب' },
]

const users = ref([])
const loading = ref(true)
const error = ref('')
const roleFilter = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await http.get('/users')
    users.value = data.data
  } catch (e) {
    error.value = apiError(e).message
  } finally {
    loading.value = false
  }
}
onMounted(load)

const shown = computed(() => (roleFilter.value ? users.value.filter((u) => u.role === roleFilter.value) : users.value))
const countFor = (role) => users.value.filter((u) => u.role === role && u.is_active).length

const dialog = ref(null)
const target = ref(null)
const form = reactive({})
const errors = ref({})
const message = ref('')
const busy = ref(false)

function openCreate() {
  target.value = null
  Object.keys(form).forEach((k) => delete form[k])
  Object.assign(form, { name: '', email: '', phone: '', password: '', role: 'technician', specialization: '', hourly_rate: '' })
  errors.value = {}
  message.value = ''
  dialog.value = 'form'
}
function openEdit(u) {
  target.value = u
  Object.keys(form).forEach((k) => delete form[k])
  Object.assign(form, { name: u.name, email: u.email, phone: u.phone ?? '', password: '', role: u.role, specialization: u.specialization ?? '', hourly_rate: u.hourly_rate ?? '' })
  errors.value = {}
  message.value = ''
  dialog.value = 'form'
}

async function send(request, after) {
  busy.value = true
  errors.value = {}
  message.value = ''
  try {
    const { data } = await request()
    toast.show(data.message)
    dialog.value = null
    after?.()
    await load()
  } catch (e) {
    const err = apiError(e)
    errors.value = err.errors
    message.value = err.status === 422 && Object.keys(err.errors).length ? '' : err.message
    if (!dialog.value) toast.show(err.message, 'late')
  } finally {
    busy.value = false
  }
}

function save() {
  const payload = Object.fromEntries(Object.entries(form).filter(([k, v]) => v !== '' || ['phone'].includes(k)).map(([k, v]) => [k, v === '' ? null : v]))
  if (payload.role !== 'technician') { delete payload.specialization; delete payload.hourly_rate }
  return target.value
    ? send(() => http.put(`/users/${target.value.id}`, payload))
    : send(() => http.post('/users', payload))
}

const toggleActive = (u) => send(() => http.put(`/users/${u.id}`, { is_active: !u.is_active }))
const err = (f) => errors.value[f]?.[0]
</script>

<template>
  <header class="page-head">
    <div>
      <h1>المستخدمون والصلاحيات</h1>
      <p class="muted">الحسابات لا تُحذف، بل تُوقف: اسم الموظف يفضل على كل سجل عمله في النظام.</p>
    </div>
    <button type="button" class="btn-sf signal" @click="openCreate">حساب جديد</button>
  </header>

  <div class="roles" role="tablist" aria-label="تصفية حسب الدور">
    <button type="button" role="tab" :aria-selected="!roleFilter" :class="{ on: !roleFilter }" @click="roleFilter = ''">الكل <span class="num">{{ users.filter((u) => u.is_active).length }}</span></button>
    <button v-for="r in ROLES" :key="r.value" type="button" role="tab" :aria-selected="roleFilter === r.value" :class="{ on: roleFilter === r.value }" @click="roleFilter = r.value">{{ r.label }} <span class="num">{{ countFor(r.value) }}</span></button>
  </div>

  <div class="panel">
    <div v-if="error" class="state"><h3>تعذر التحميل</h3><p>{{ error }}</p></div>
    <div v-else class="table-wrap">
      <table class="table-sf">
        <thead><tr><th>الاسم</th><th>الدور</th><th>التخصص / سعر الساعة</th><th>طلبات مفتوحة</th><th>الحالة</th><th><span class="visually-hidden">إجراءات</span></th></tr></thead>
        <tbody v-if="loading"><tr v-for="n in 6" :key="n"><td v-for="c in 6" :key="c"><div class="skeleton"></div></td></tr></tbody>
        <tbody v-else>
          <tr v-for="u in shown" :key="u.id" :class="{ inactive: !u.is_active }">
            <td><b class="d-block">{{ u.name }} <span v-if="u.is_me" class="chip tiny">أنت</span></b><span class="faint num">{{ u.email }}</span></td>
            <td>{{ u.role_label }}</td>
            <td>{{ u.role === 'technician' ? `${u.specialization} · ${Number(u.hourly_rate)} ج.م/س` : '—' }}</td>
            <td class="num">{{ u.role === 'technician' ? u.open_requests_count : '—' }}</td>
            <td><span class="chip" :class="u.is_active ? 'done' : ''"><span class="dot"></span>{{ u.is_active ? 'نشط' : 'موقوف' }}</span></td>
            <td class="text-end nowrap">
              <button type="button" class="btn-sf mini quiet" @click="openEdit(u)">تعديل</button>
              <button v-if="!u.is_me" type="button" class="btn-sf mini quiet" :class="{ danger: u.is_active }" @click="toggleActive(u)">{{ u.is_active ? 'إيقاف' : 'تفعيل' }}</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <AppDialog :open="dialog === 'form'" :title="target ? `تعديل حساب ${target.name}` : 'حساب جديد'" width="540px" @close="dialog = null">
    <div class="grid2">
      <div><label class="form-label" for="u-n">الاسم</label><input id="u-n" v-model.trim="form.name" class="form-control" :class="{ 'is-invalid': err('name') }" /><div class="invalid-feedback">{{ err('name') }}</div></div>
      <div><label class="form-label" for="u-e">البريد الإلكتروني</label><input id="u-e" v-model.trim="form.email" type="email" class="form-control num" :class="{ 'is-invalid': err('email') }" /><div class="invalid-feedback">{{ err('email') }}</div></div>
      <div><label class="form-label" for="u-p">الموبايل</label><input id="u-p" v-model.trim="form.phone" class="form-control num" :class="{ 'is-invalid': err('phone') }" /><div class="invalid-feedback">{{ err('phone') }}</div></div>
      <div>
        <label class="form-label" for="u-pw">{{ target ? 'كلمة مرور جديدة (اختياري)' : 'كلمة المرور' }}</label>
        <input id="u-pw" v-model="form.password" type="password" autocomplete="new-password" class="form-control" :class="{ 'is-invalid': err('password') }" />
        <div class="invalid-feedback">{{ err('password') }}</div>
      </div>
      <fieldset class="full role-pick" :disabled="target?.is_me">
        <legend class="form-label">الدور</legend>
        <label v-for="r in ROLES" :key="r.value" :class="{ on: form.role === r.value }"><input v-model="form.role" type="radio" name="role" :value="r.value" />{{ r.label }}</label>
        <p v-if="target?.is_me" class="form-text w-100">لا يمكنك تغيير دورك بنفسك.</p>
      </fieldset>
      <template v-if="form.role === 'technician'">
        <div><label class="form-label" for="u-sp">التخصص</label><input id="u-sp" v-model.trim="form.specialization" class="form-control" :class="{ 'is-invalid': err('specialization') }" placeholder="تكييف وتبريد" /><div class="invalid-feedback">{{ err('specialization') }}</div></div>
        <div><label class="form-label" for="u-hr">سعر الساعة (ج.م)</label><input id="u-hr" v-model="form.hourly_rate" inputmode="decimal" class="form-control num" :class="{ 'is-invalid': err('hourly_rate') }" /><div class="invalid-feedback">{{ err('hourly_rate') }}</div></div>
        <p class="form-text full">تغيير سعر الساعة يطبق على الساعات الجديدة فقط؛ الساعات المسجلة محفوظة بسعرها وقت التسجيل.</p>
      </template>
    </div>
    <p v-if="message" class="alert-sf late">{{ message }}</p>
    <template #footer>
      <button type="button" class="btn-sf ink" :disabled="busy" @click="save">{{ target ? 'حفظ التعديلات' : 'إنشاء الحساب' }}</button>
      <button type="button" class="btn-sf quiet" @click="dialog = null">تراجع</button>
    </template>
  </AppDialog>
</template>

<style scoped>
.page-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 18px; }
.page-head h1 { font-size: var(--fs-2xl); }
.roles { display: inline-flex; background: var(--surface); border: 1px solid var(--line); border-radius: var(--r-sm); padding: 3px; margin-bottom: 14px; flex-wrap: wrap; }
.roles button { border: 0; background: transparent; padding: 6px 14px; border-radius: 4px; color: var(--ink-2); font-weight: 500; font-size: var(--fs-sm); }
.roles button.on { background: var(--ink); color: var(--paper); }
tr.inactive td { opacity: .55; }
.tiny { font-size: var(--fs-xs); padding: 0 8px; }
.mini { min-height: 32px; padding: 2px 10px; font-size: var(--fs-sm); }
.btn-sf.danger { color: var(--late); }
.nowrap { white-space: nowrap; }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.full { grid-column: 1 / -1; }
.role-pick { display: flex; flex-wrap: wrap; gap: 6px; border: 0; padding: 0; margin: 0; }
.role-pick legend { width: 100%; }
.role-pick label { display: inline-flex; gap: 6px; align-items: center; padding: 7px 12px; border: 1px solid var(--line); border-radius: var(--r-sm); cursor: pointer; font-size: var(--fs-sm); }
.role-pick label.on { border-color: var(--ink); box-shadow: inset 0 0 0 1px var(--ink); }
.role-pick input { accent-color: var(--ink); }
@media (max-width: 520px) { .grid2 { grid-template-columns: 1fr; } }
</style>
