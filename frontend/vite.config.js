import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

/*
 * The SPA and the API share one origin: in development Vite proxies /api and /sanctum
 * to Laravel, in production nginx does the same. Same origin means Sanctum cookie auth
 * works without CORS and the session cookie is never exposed cross-site.
 */
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
  },
  server: {
    port: 5173,
    proxy: {
      '/api': { target: 'http://127.0.0.1:8000' },
      '/sanctum': { target: 'http://127.0.0.1:8000' },
    },
  },
})
