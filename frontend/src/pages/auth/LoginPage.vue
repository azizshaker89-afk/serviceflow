<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { apiError } from '@/lib/http'
import BrandMark from '@/components/BrandMark.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const form = reactive({ email: '', password: '', remember: true })
const errors = ref({})
const message = ref('')
const loading = ref(false)

const demoAccounts = [
  { role: 'مدير النظام', email: 'admin@serviceflow.test', can: 'يرى كل الطلبات ويعيّن الفنيين' },
  { role: 'موظف استقبال', email: 'reception@serviceflow.test', can: 'يستقبل الطلبات ويسجل العملاء' },
  { role: 'فني صيانة', email: 'technician@serviceflow.test', can: 'يرى المهام المعيّنة له فقط' },
  { role: 'محاسب', email: 'accountant@serviceflow.test', can: 'يراجع الطلبات المكتملة ويصدر الفواتير' },
]

function useDemo(account) {
  form.email = account.email
  form.password = 'password'
}

async function submit() {
  loading.value = true
  errors.value = {}
  message.value = ''
  try {
    await auth.login(form)
    const redirect = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/app') ? route.query.redirect : null
    router.replace(redirect ?? auth.homeRoute)
  } catch (e) {
    const err = apiError(e)
    errors.value = err.errors
    message.value = err.status === 422 ? '' : err.message
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login">
    <div class="form-side">
      <BrandMark />
      <div class="form-wrap">
        <h1>دخول فريق العمل</h1>
        <p class="muted">لوحة التحكم للمديرين والاستقبال والفنيين والمحاسبين. لو أنت عميل، <RouterLink :to="{ name: 'home', hash: '#request' }">اطلب الصيانة من هنا</RouterLink> من غير تسجيل.</p>
        <form class="fields" novalidate @submit.prevent="submit">
          <div>
            <label class="form-label" for="l-email">البريد الإلكتروني</label>
            <input id="l-email" v-model.trim="form.email" type="email" class="form-control num" :class="{ 'is-invalid': errors.email }" autocomplete="username" required />
            <div class="invalid-feedback">{{ errors.email?.[0] }}</div>
          </div>
          <div>
            <label class="form-label" for="l-password">كلمة المرور</label>
            <input id="l-password" v-model="form.password" type="password" class="form-control" :class="{ 'is-invalid': errors.password }" autocomplete="current-password" required />
            <div class="invalid-feedback">{{ errors.password?.[0] }}</div>
          </div>
          <div class="form-check">
            <input id="l-remember" v-model="form.remember" class="form-check-input" type="checkbox" />
            <label class="form-check-label" for="l-remember">تذكرني على هذا الجهاز</label>
          </div>
          <p v-if="message" class="alert-sf late" role="alert">{{ message }}</p>
          <button type="submit" class="btn-sf ink lg" :disabled="loading">{{ loading ? 'جارٍ الدخول…' : 'تسجيل الدخول' }}</button>
        </form>
      </div>
    </div>
    <aside class="demo-side" aria-labelledby="demo-title">
      <h2 id="demo-title">حسابات تجريبية</h2>
      <p>كل حساب يفتح لوحة مختلفة حسب صلاحياته. كلمة المرور لكلها: <b class="num">password</b></p>
      <ul>
        <li v-for="a in demoAccounts" :key="a.email">
          <button type="button" @click="useDemo(a)">
            <b>{{ a.role }}</b>
            <span class="num">{{ a.email }}</span>
            <small>{{ a.can }}</small>
          </button>
        </li>
      </ul>
    </aside>
  </div>
</template>

<style scoped>
.login { min-height: 100vh; display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 440px); }
.form-side { padding: 28px max(20px, 5vw); display: grid; grid-template-rows: auto 1fr; }
.form-wrap { align-self: center; width: 100%; max-width: 400px; display: grid; gap: 14px; padding-block: 40px; }
.form-wrap h1 { font-size: var(--fs-2xl); }
.fields { display: grid; gap: 16px; margin-top: 10px; }
.demo-side { background: var(--ink); color: var(--paper); padding: 48px 32px; display: grid; align-content: center; gap: 14px; }
.demo-side h2 { color: var(--paper); font-size: var(--fs-xl); }
.demo-side p { color: color-mix(in srgb, var(--paper) 72%, transparent); }
.demo-side ul { list-style: none; padding: 0; margin: 8px 0 0; display: grid; gap: 8px; }
.demo-side button {
  width: 100%; text-align: start; display: grid; gap: 2px; padding: 12px 14px; border-radius: var(--r-sm); cursor: pointer;
  background: color-mix(in srgb, var(--paper) 6%, var(--ink)); border: 1px solid color-mix(in srgb, var(--paper) 18%, var(--ink)); color: var(--paper);
}
.demo-side button:hover { border-color: var(--signal); }
.demo-side small { color: color-mix(in srgb, var(--paper) 62%, transparent); }
@media (max-width: 860px) { .login { grid-template-columns: minmax(0, 1fr); } .demo-side { padding: 32px 20px; } }
@media (prefers-color-scheme: dark) {
  .demo-side { background: var(--surface); color: var(--ink); border-inline-start: 1px solid var(--line); }
  .demo-side h2 { color: var(--ink); }
  .demo-side p, .demo-side small { color: var(--ink-2); }
  .demo-side button { background: var(--paper); border-color: var(--line); color: var(--ink); }
}
</style>
