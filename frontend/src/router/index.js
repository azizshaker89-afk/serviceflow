import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { http } from '@/lib/http'

const routes = [
  {
    path: '/',
    component: () => import('@/layouts/PublicLayout.vue'),
    children: [
      { path: '', name: 'home', component: () => import('@/pages/public/LandingPage.vue') },
      { path: 'track', name: 'track', component: () => import('@/pages/public/TrackPage.vue') },
    ],
  },
  { path: '/login', name: 'login', component: () => import('@/pages/auth/LoginPage.vue'), meta: { guestOnly: true } },
  {
    path: '/app',
    component: () => import('@/layouts/AppLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', name: 'app', redirect: () => useAuthStore().homeRoute },
      {
        path: 'dashboard',
        name: 'dashboard',
        component: () => import('@/pages/app/DashboardPage.vue'),
        meta: { permission: ['reports.operational', 'reports.financial'], title: 'لوحة المتابعة' },
      },
      {
        path: 'customers',
        name: 'customers',
        component: () => import('@/pages/app/CustomersPage.vue'),
        meta: { permission: 'customers.view', title: 'العملاء' },
      },
      {
        path: 'customers/:id(\d+)',
        name: 'customer-details',
        component: () => import('@/pages/app/CustomerDetailsPage.vue'),
        meta: { permission: 'customers.view', title: 'عميل' },
        props: true,
      },
      {
        path: 'users',
        name: 'users',
        component: () => import('@/pages/app/UsersPage.vue'),
        meta: { permission: 'users.manage', title: 'المستخدمون' },
      },
      {
        path: 'activity',
        name: 'activity',
        component: () => import('@/pages/app/ActivityLogPage.vue'),
        meta: { permission: 'activity-logs.view', title: 'سجل النشاط' },
      },
      {
        path: 'requests',
        name: 'requests',
        component: () => import('@/pages/app/RequestsPage.vue'),
        meta: { permission: 'service-requests.view-all', title: 'طلبات الصيانة' },
      },
      {
        path: 'requests/new',
        name: 'request-new',
        component: () => import('@/pages/app/NewRequestPage.vue'),
        meta: { permission: 'service-requests.create', title: 'طلب صيانة جديد' },
      },
      {
        path: 'invoices',
        name: 'invoices',
        component: () => import('@/pages/app/InvoicesPage.vue'),
        meta: { permission: 'invoices.view', title: 'الفواتير' },
      },
      {
        path: 'invoices/:id(\\d+)',
        name: 'invoice-details',
        component: () => import('@/pages/app/InvoiceDetailsPage.vue'),
        meta: { permission: 'invoices.view', title: 'فاتورة' },
        props: true,
      },
      {
        path: 'inventory',
        name: 'inventory',
        component: () => import('@/pages/app/InventoryPage.vue'),
        meta: { permission: 'spare-parts.manage', title: 'المخزون' },
      },
      {
        path: 'my-tasks',
        name: 'my-tasks',
        component: () => import('@/pages/app/MyTasksPage.vue'),
        meta: { permission: 'service-requests.work', title: 'مهامي' },
      },
      {
        path: 'requests/:id(\d+)',
        name: 'request-details',
        component: () => import('@/pages/app/RequestDetailsPage.vue'),
        meta: { title: 'تفاصيل الطلب' },
        props: true,
      },
    ],
  },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@/pages/NotFoundPage.vue') },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, from, saved) {
    if (saved) return saved
    if (to.hash) return { el: to.hash, behavior: 'smooth', top: 80 }
    return { top: 0 }
  },
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  const needsUser = to.matched.some((r) => r.meta.requiresAuth) || to.meta.guestOnly

  if (needsUser && !auth.checked) {
    await auth.fetchUser()
  }

  if (to.matched.some((r) => r.meta.requiresAuth) && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return auth.homeRoute
  }

  const required = [to.meta.permission ?? []].flat()
  if (required.length && !required.some((p) => auth.can(p))) {
    useToastStore().show('هذه الشاشة غير متاحة لدورك', 'late')
    return auth.homeRoute
  }

  return true
})

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} | ServiceFlow` : 'ServiceFlow | صيانة منزلية تتابعها خطوة بخطوة'
})

/* A session that expires mid-use sends the user back to login instead of failing silently. */
http.interceptors.response.use(undefined, (error) => {
  if (error?.response?.status === 401 && router.currentRoute.value.matched.some((r) => r.meta.requiresAuth)) {
    useAuthStore().clear()
    useToastStore().show('انتهت الجلسة. سجّل الدخول مرة أخرى.')
    router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } })
  }
  return Promise.reject(error)
})

export default router
