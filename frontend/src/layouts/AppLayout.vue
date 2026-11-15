<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { initials } from '@/lib/format'
import BrandMark from '@/components/BrandMark.vue'

const auth = useAuthStore()
const toast = useToastStore()
const route = useRoute()
const router = useRouter()
const menuOpen = ref(false)

/* Only screens that exist and that this role may open. */
const nav = computed(() => [
  { to: { name: 'dashboard' }, label: 'لوحة المتابعة', permission: ['reports.operational', 'reports.financial'] },
  { to: { name: 'requests' }, label: 'طلبات الصيانة', permission: 'service-requests.view-all' },
  { to: { name: 'my-tasks' }, label: 'مهامي', permission: 'service-requests.work' },
  { to: { name: 'invoices' }, label: 'الفواتير', permission: 'invoices.view' },
  { to: { name: 'customers' }, label: 'العملاء', permission: 'customers.view' },
  { to: { name: 'inventory' }, label: 'المخزون', permission: 'spare-parts.manage' },
  { to: { name: 'users' }, label: 'المستخدمون', permission: 'users.manage', group: 'admin' },
  { to: { name: 'activity' }, label: 'سجل النشاط', permission: 'activity-logs.view', group: 'admin' },
].filter((item) => [item.permission].flat().some((p) => auth.can(p))))

watch(() => route.fullPath, () => { menuOpen.value = false })

async function logout() {
  await auth.logout()
  toast.show('تم تسجيل الخروج')
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="shell">
    <aside class="side no-print" :class="{ open: menuOpen }">
      <div class="side-top">
        <BrandMark />
        <button type="button" class="menu-btn" :aria-expanded="menuOpen" aria-controls="side-nav" @click="menuOpen = !menuOpen">
          <span class="visually-hidden">القائمة</span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
        </button>
      </div>
      <nav id="side-nav" class="side-nav" aria-label="لوحة التحكم">
        <RouterLink v-for="item in nav" :key="item.label" :to="item.to" class="nav-link-sf" active-class="active">{{ item.label }}</RouterLink>
      </nav>
      <div class="me">
        <span class="avatar" aria-hidden="true">{{ initials(auth.user?.name) }}</span>
        <div class="me-text">
          <b>{{ auth.user?.name }}</b>
          <span>{{ auth.user?.role_label }}</span>
        </div>
        <button type="button" class="btn-sf quiet logout" @click="logout">خروج</button>
      </div>
    </aside>
    <main class="content">
      <RouterView />
    </main>
  </div>
</template>

<style scoped>
.shell { min-height: 100vh; display: grid; grid-template-columns: 248px minmax(0, 1fr); }
.side { background: var(--surface); border-inline-end: 1px solid var(--line); display: grid; grid-template-rows: auto 1fr auto; position: sticky; top: 0; height: 100vh; }
.side-top { padding: 20px 20px 12px; display: flex; justify-content: space-between; align-items: center; }
.menu-btn { display: none; border: 1px solid var(--line); background: var(--surface); border-radius: var(--r-sm); width: 40px; height: 40px; place-items: center; color: var(--ink); }
.menu-btn svg { width: 20px; height: 20px; }
.side-nav { padding: 12px; display: grid; gap: 2px; align-content: start; }
.nav-link-sf { padding: 10px 12px; border-radius: var(--r-sm); text-decoration: none; color: var(--ink-2); font-weight: 500; }
.nav-link-sf:hover { background: var(--paper); color: var(--ink); }
.nav-link-sf.active { background: var(--ink); color: var(--paper); }
.me { padding: 16px 20px; border-top: 1px solid var(--line); display: flex; align-items: center; gap: 10px; }
.avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--paper); box-shadow: inset 0 0 0 1px var(--line); display: grid; place-items: center; font-weight: 700; font-size: var(--fs-sm); flex: none; }
.me-text { display: grid; line-height: 1.35; min-width: 0; }
.me-text b { font-size: var(--fs-sm); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.me-text span { font-size: var(--fs-xs); color: var(--ink-3); }
.logout { margin-inline-start: auto; min-height: 34px; padding-inline: 10px; font-size: var(--fs-sm); }
.content { padding: 28px clamp(16px, 3vw, 40px) 48px; min-width: 0; }
@media (max-width: 900px) {
  .shell { grid-template-columns: minmax(0, 1fr); }
  .side { position: sticky; top: 0; z-index: 30; height: auto; grid-template-rows: auto; border-inline-end: 0; border-bottom: 1px solid var(--line); }
  .menu-btn { display: grid; }
  .side-nav, .me { display: none; }
  .side.open .side-nav, .side.open .me { display: grid; }
  .side.open .me { display: flex; }
}
</style>
