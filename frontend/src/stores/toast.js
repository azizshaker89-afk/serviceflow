import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useToastStore = defineStore('toast', () => {
  const items = ref([])
  let nextId = 1

  function show(message, tone = 'default') {
    const id = nextId++
    items.value.push({ id, message, tone })
    setTimeout(() => dismiss(id), 3800)
  }

  function dismiss(id) {
    items.value = items.value.filter((t) => t.id !== id)
  }

  return { items, show, dismiss }
})
