<template>
  <div class="container-sf state" style="min-height: 70vh; align-content: center">
    <h1 class="num" style="font-size: var(--fs-3xl)">404</h1>
    <h3>الصفحة دي مش موجودة</h3>
    <p>يمكن الرابط اتكتب غلط أو الصفحة اتنقلت.</p>
    <RouterLink :to="{ name: 'home' }" class="btn-sf signal mt-2">الرجوع للرئيسية</RouterLink>
  </div>
</template>
