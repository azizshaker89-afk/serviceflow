<script setup>
import BrandMark from '@/components/BrandMark.vue'
</script>

<template>
  <div class="public">
    <a href="#main" class="visually-hidden-focusable skip">تخطي إلى المحتوى</a>
    <header class="site-header">
      <div class="container-sf bar">
        <BrandMark />
        <nav class="links" aria-label="القائمة الرئيسية">
          <RouterLink :to="{ name: 'home', hash: '#services' }">الخدمات</RouterLink>
          <RouterLink :to="{ name: 'home', hash: '#how' }">إزاي بنشتغل</RouterLink>
          <RouterLink :to="{ name: 'home', hash: '#promise' }">المواعيد والأسعار</RouterLink>
          <RouterLink :to="{ name: 'home', hash: '#faq' }">الأسئلة</RouterLink>
          <RouterLink :to="{ name: 'track' }">تابع طلبك</RouterLink>
        </nav>
        <RouterLink :to="{ name: 'home', hash: '#request' }" class="btn-sf signal cta">اطلب فني</RouterLink>
      </div>
    </header>
    <main id="main"><RouterView /></main>
    <footer class="site-footer">
      <div class="container-sf foot">
        <div class="foot-brand">
          <BrandMark />
          <p class="muted">صيانة التكييف والكهرباء والسباكة والأجهزة المنزلية في القاهرة الكبرى.</p>
        </div>
        <dl class="contact">
          <div><dt>خدمة العملاء</dt><dd class="num">0100 000 0000</dd></div>
          <div><dt>مواعيد العمل</dt><dd>يوميًا من 9 صباحًا إلى 9 مساءً</dd></div>
        </dl>
        <RouterLink :to="{ name: 'login' }" class="staff">دخول فريق العمل</RouterLink>
      </div>
      <p class="container-sf legal faint">ServiceFlow مشروع تجريبي للعرض. البيانات المعروضة بيانات تجريبية.</p>
    </footer>
  </div>
</template>

<style scoped>
.skip { position: absolute; inset-inline-start: 12px; top: 8px; z-index: 50; background: var(--surface); padding: 6px 12px; border-radius: var(--r-sm); }
.site-header { position: sticky; top: 0; z-index: 40; background: color-mix(in srgb, var(--paper) 92%, transparent); backdrop-filter: blur(8px); border-bottom: 1px solid var(--line); }
.bar { display: flex; align-items: center; gap: 28px; min-height: 68px; }
.links { display: flex; gap: 22px; margin-inline-start: auto; }
.links a { text-decoration: none; color: var(--ink-2); font-weight: 500; }
.links a:hover, .links a.router-link-exact-active { color: var(--ink); }
.cta { min-height: 40px; padding-inline: 16px; }
.site-footer { border-top: 1px solid var(--line); margin-top: 24px; padding-block: 36px 24px; }
.foot { display: grid; grid-template-columns: 1.4fr 1fr auto; gap: 28px; align-items: start; }
.foot-brand { display: grid; gap: 10px; max-width: 40ch; }
.contact { display: grid; gap: 10px; margin: 0; font-size: var(--fs-sm); }
.contact dt { color: var(--ink-3); font-weight: 500; }
.contact dd { margin: 0; font-weight: 600; user-select: all; }
.staff { font-size: var(--fs-sm); color: var(--ink-2); }
.legal { font-size: var(--fs-xs); margin-top: 28px; }
@media (max-width: 760px) {
  .links { display: none; }
  .cta { margin-inline-start: auto; }
  .foot { grid-template-columns: 1fr; }
}
</style>
