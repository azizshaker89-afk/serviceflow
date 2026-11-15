import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { http, fetchCsrfCookie } from '@/lib/http'

/**
 * The signed-in staff member. Permissions come from the API and are used only
 * to choose what to show; the API re-checks every request with Policies.
 */
export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const checked = ref(false)

  const isAuthenticated = computed(() => user.value !== null)
  const role = computed(() => user.value?.role ?? null)

  const can = (permission) => !!user.value?.permissions?.includes(permission)

  /** Where each role lands after login. */
  const homeRoute = computed(() => {
    if (can('reports.operational')) return { name: 'dashboard' }
    if (can('invoices.manage')) return { name: 'invoices' }
    if (can('service-requests.view-all')) return { name: 'requests' }
    return { name: 'my-tasks' }
  })

  async function fetchUser() {
    try {
      const { data } = await http.get('/auth/me')
      user.value = data.data
    } catch {
      user.value = null
    } finally {
      checked.value = true
    }
  }

  async function login(credentials) {
    await fetchCsrfCookie()
    const { data } = await http.post('/auth/login', credentials)
    user.value = data.data
    checked.value = true
    return data
  }

  async function logout() {
    try {
      await http.post('/auth/logout')
    } finally {
      user.value = null
    }
  }

  function clear() {
    user.value = null
  }

  return { user, checked, isAuthenticated, role, can, homeRoute, fetchUser, login, logout, clear }
})
