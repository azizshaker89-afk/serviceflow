<script setup>
defineProps({ inverse: { type: Boolean, default: false } })
</script>

<template>
  <RouterLink :to="{ name: 'home' }" class="brand" :class="{ inverse }" aria-label="ServiceFlow، الصفحة الرئيسية">
    <span class="mark" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z" /></svg>
    </span>
    <span class="word">Service<b>Flow</b></span>
  </RouterLink>
</template>

<style scoped>
.brand { display: inline-flex; align-items: center; gap: 9px; text-decoration: none; color: var(--ink); direction: ltr; font-size: var(--fs-lg); font-weight: 600; }
.word b { font-weight: 700; }
.mark { width: 30px; height: 30px; border-radius: 8px; background: var(--ink); display: grid; place-items: center; }
.mark svg { width: 18px; height: 18px; stroke: var(--signal); }
.inverse { color: var(--paper); }
.inverse .mark { background: var(--paper); }
</style>
