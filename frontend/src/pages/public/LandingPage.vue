<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { http } from '@/lib/http'
import ServiceRequestForm from '@/components/ServiceRequestForm.vue'
import StatusRail from '@/components/StatusRail.vue'
import CategoryIcon from '@/components/CategoryIcon.vue'

const router = useRouter()
const categories = ref([])
const categoriesFailed = ref(false)
const stats = ref(null)
const preselected = ref(null)
const track = reactive({ code: '', phone: '' })

onMounted(async () => {
  const [cats, numbers] = await Promise.allSettled([http.get('/public/service-categories'), http.get('/public/stats')])
  if (cats.status === 'fulfilled') categories.value = cats.value.data.data
  else categoriesFailed.value = true
  if (numbers.status === 'fulfilled') stats.value = numbers.value.data.data
})

function requestService(category) {
  preselected.value = category.id
  document.getElementById('request')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

function goTrack() {
  router.push({ name: 'track', query: { ...track } })
}

/* An illustration of the real tracking page, drawn with the same rail component the app uses. */
const hour = 3600e3
const t0 = Date.now() - 3.2 * hour
const sampleHistory = [
  { to_status: 'new', changed_by: 'طلب أونلاين', created_at: new Date(t0).toISOString() },
  { to_status: 'assigned', changed_by: 'مدير التشغيل', created_at: new Date(t0 + 0.4 * hour).toISOString() },
  { to_status: 'in_progress', changed_by: 'الفني', created_at: new Date(t0 + 2.1 * hour).toISOString() },
]

const steps = [
  { title: 'تكتب طلبك', text: 'نوع العطل والعنوان والميعاد اللي يناسبك، وتاخد رقم طلب على طول.', who: 'أنت' },
  { title: 'نعيّن فني متخصص', text: 'مدير التشغيل يختار فني في نفس التخصص وعنده وقت، حسب أولوية العطل.', who: 'مدير التشغيل' },
  { title: 'الفني ينفّذ ويسجّل', text: 'كل قطعة غيار بتتسجل بسعرها، وكل ساعة شغل بتتسجل باسم الفني.', who: 'الفني' },
  { title: 'فاتورة مفصلة', text: 'القطع والمصنعية والضريبة كل واحدة في سطر، وتقدر تدفع على دفعات.', who: 'المحاسب' },
]

const SLA = [
  { key: 'urgent', label: 'عاجل', example: 'ماس كهربائي، تسريب مياه شديد، تكييف لمريض' },
  { key: 'high', label: 'عالي', example: 'ثلاجة لا تبرد، سخان لا يعمل' },
  { key: 'normal', label: 'عادي', example: 'غسالة لا تصرف، صوت في التكييف' },
  { key: 'low', label: 'غير عاجل', example: 'معاينة، تركيب جديد بميعاد مرن' },
]
const slaText = (key) => {
  const h = stats.value?.sla_hours?.[key]
  if (!h) return '—'
  return h < 24 ? `${h} ساعات` : h === 24 ? 'يوم' : `${h / 24} أيام`
}

const faqs = [
  { q: 'لازم أعمل حساب عشان أطلب فني؟', a: 'لأ. الطلب بالاسم ورقم الموبايل بس. رقم الموبايل هو اللي بيربط طلباتك ببعض، وبيه مع رقم الطلب تتابع حالته.' },
  { q: 'إزاي أعرف الطلب وصل لفين؟', a: 'من صفحة «تابع طلبك» برقم الطلب ورقم الموبايل. هتشوف كل مرحلة ووقتها: الاستلام، تعيين الفني، التنفيذ، والإنهاء.' },
  { q: 'الفاتورة بتتحسب إزاي؟', a: 'قطع الغيار بسعرها وقت تركيبها، والمصنعية بعدد ساعات الشغل أو سعر ثابت لبعض الخدمات زي التركيب. الخصم (لو فيه) بيتطرح الأول، وبعدين تتحسب ضريبة القيمة المضافة.' },
  { q: 'ينفع أدفع على دفعات؟', a: 'أيوه. كل دفعة بتتسجل في الفاتورة، والمتبقي بيبان لك في صفحة المتابعة لحد ما الفاتورة تتقفل.' },
  { q: 'لو الفني محتاج قطعة مش معاه؟', a: 'الطلب بيتحول لـ «بانتظار قطع غيار» وبتشوف ده في صفحة المتابعة، ولما القطعة توصل الفني بيكمّل.' },
  { q: 'ممكن ألغي الطلب؟', a: 'كلّم خدمة العملاء قبل ما الفني يبدأ. لو كان اتركّب قطع غيار، بترجع للمخزن ومش بتتحاسب عليها.' },
]

const hasStats = computed(() => stats.value && stats.value.completed_requests > 0)
</script>

<template>
  <!-- HERO -->
  <section class="hero" aria-labelledby="hero-title">
    <div class="container-sf hero-grid">
      <div class="pitch">
        <h1 id="hero-title">اطلب فني صيانة لبيتك، وتابع طلبك خطوة بخطوة.</h1>
        <p class="lead">تكييف، كهرباء، سباكة، وأجهزة منزلية. اكتب المشكلة في دقيقتين من غير حساب ولا مكالمات، وخد رقم طلب تشوف بيه مين ماسك طلبك ووصل لفين.</p>
        <div class="ctas">
          <a href="#request" class="btn-sf signal lg">اطلب فني الآن</a>
          <RouterLink :to="{ name: 'track' }" class="btn-sf lg">تابع طلب سابق</RouterLink>
        </div>
      </div>

      <figure class="preview" aria-label="مثال على صفحة متابعة الطلب">
        <div class="preview-card panel">
          <div class="preview-head">
            <div>
              <span class="num faint">SR-2026-00128</span>
              <b>صيانة تكييف</b>
            </div>
            <span class="chip signal"><span class="dot"></span>جاري التنفيذ</span>
          </div>
          <StatusRail status="in_progress" :history="sampleHistory" />
          <div class="preview-foot">
            <span><b>الخطوة الجاية:</b> الفني بيخلص الصيانة ويسجّل القطع المستخدمة.</span>
          </div>
        </div>
        <figcaption class="faint">مثال لصفحة متابعة الطلب اللي هتوصلها برقم طلبك.</figcaption>
      </figure>
    </div>

    <div v-if="hasStats" class="container-sf">
      <dl class="stats">
        <div><dt>طلب صيانة اتنفذ</dt><dd class="num">{{ stats.completed_requests.toLocaleString('en-US') }}</dd></div>
        <div><dt>متوسط وقت الإنجاز</dt><dd><span class="num">{{ stats.average_completion_hours }}</span> ساعة</dd></div>
        <div><dt>اتنفذت في موعدها</dt><dd class="num">{{ stats.on_time_rate }}%</dd></div>
        <div><dt>فني متخصص</dt><dd class="num">{{ stats.technicians }}</dd></div>
      </dl>
      <p class="stats-note faint">أرقام محسوبة مباشرة من الطلبات المسجلة في النظام.</p>
    </div>
  </section>

  <!-- SERVICES -->
  <section id="services" class="section" aria-labelledby="services-title">
    <div class="container-sf">
      <header class="sec-head">
        <h2 id="services-title">الخدمات</h2>
        <p class="muted">اختار الخدمة وهتتحدد في فورم الطلب على طول.</p>
      </header>
      <ul v-if="categories.length" class="services">
        <li v-for="c in categories" :key="c.id">
          <span class="icon"><CategoryIcon :name="c.name" /></span>
          <div class="svc-text">
            <h3>{{ c.name }}</h3>
            <p v-if="c.description" class="muted">{{ c.description }}</p>
          </div>
          <button type="button" class="svc-btn" @click="requestService(c)" :aria-label="`اطلب ${c.name}`">اطلب</button>
        </li>
      </ul>
      <ul v-else-if="!categoriesFailed" class="services" aria-busy="true">
        <li v-for="n in 8" :key="n"><span class="icon"></span><div class="svc-text w-100"><div class="skeleton w-50 mb-2"></div><div class="skeleton w-75"></div></div></li>
      </ul>
      <p v-else class="alert-sf late">تعذر تحميل الخدمات. حدّث الصفحة أو اتصل بخدمة العملاء.</p>
    </div>
  </section>

  <!-- HOW -->
  <section id="how" class="section alt" aria-labelledby="how-title">
    <div class="container-sf">
      <header class="sec-head">
        <h2 id="how-title">من الطلب لحد ما الفني يخلص</h2>
        <p class="muted">نفس المراحل اللي هتشوفها في صفحة المتابعة، وكل مرحلة متسجلة باسم المسؤول عنها ووقتها.</p>
      </header>
      <ol class="how">
        <li v-for="(s, i) in steps" :key="s.title">
          <span class="node num" aria-hidden="true">{{ i + 1 }}</span>
          <h3>{{ s.title }}</h3>
          <p>{{ s.text }}</p>
          <span class="who">{{ s.who }}</span>
        </li>
      </ol>
    </div>
  </section>

  <!-- PROMISES: SLA + INVOICE -->
  <section id="promise" class="section" aria-labelledby="promise-title">
    <div class="container-sf">
      <header class="sec-head">
        <h2 id="promise-title">مواعيد واضحة، وفاتورة واضحة</h2>
        <p class="muted">كل طلب له موعد نهائي حسب أولويته، ومدير التشغيل بيشوف أي طلب يتأخر عن موعده.</p>
      </header>
      <div class="promise">
        <div class="panel">
          <div class="panel-section">
            <h3 class="mini-title">أقصى وقت للإنجاز حسب الأولوية</h3>
            <table class="table-sf sla">
              <thead><tr><th>الأولوية</th><th>خلال</th><th>أمثلة</th></tr></thead>
              <tbody>
                <tr v-for="row in SLA" :key="row.key">
                  <td><b>{{ row.label }}</b></td>
                  <td class="num"><b>{{ slaText(row.key) }}</b></td>
                  <td class="muted">{{ row.example }}</td>
                </tr>
              </tbody>
            </table>
            <p class="faint fine">الأولوية بيحددها فريق الاستقبال بعد مراجعة الطلب.</p>
          </div>
        </div>

        <div class="panel invoice-sample">
          <div class="panel-section">
            <div class="inv-head">
              <h3 class="mini-title">شكل الفاتورة</h3>
              <span class="faint">مثال</span>
            </div>
            <dl class="inv">
              <div><dt>فريون R410 × 2</dt><dd class="num">520.00</dd></div>
              <div><dt>مكثف تكييف 35 ميكرو × 1</dt><dd class="num">150.00</dd></div>
              <div><dt>مصنعية: 2.5 ساعة × 120</dt><dd class="num">300.00</dd></div>
              <div class="sub"><dt>الإجمالي قبل الضريبة</dt><dd class="num">970.00</dd></div>
              <div><dt>خصم</dt><dd class="num">− 70.00</dd></div>
              <div><dt>ضريبة 14% (بعد الخصم)</dt><dd class="num">126.00</dd></div>
              <div class="grand"><dt>الإجمالي</dt><dd><span class="num">1,026.00</span> ج.م</dd></div>
            </dl>
            <p class="faint fine">سعر القطعة بيتثبت لحظة تركيبها، فمحدش يقدر يغيّره بعدين.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- REQUEST -->
  <section id="request" class="section alt request" aria-labelledby="request-title">
    <div class="container-sf request-grid">
      <div class="request-side">
        <h2 id="request-title">اطلب فني</h2>
        <p class="muted">املا الخطوات التلاتة وهتاخد رقم طلب فورًا.</p>
        <ul class="assure">
          <li>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
            <span><b>من غير حساب.</b> الاسم والموبايل كفاية.</span>
          </li>
          <li>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
            <span><b>فني في نفس التخصص.</b> بيتعيّن حسب نوع العطل وضغط الشغل.</span>
          </li>
          <li>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
            <span><b>بياناتك محمية.</b> محدش يشوف طلبك غير برقم الطلب ورقم موبايلك مع بعض.</span>
          </li>
        </ul>
        <div class="hotline">
          <span class="faint">تفضّل تكلمنا؟</span>
          <b class="num">0100 000 0000</b>
          <span class="faint">يوميًا من 9 ص لـ 9 م</span>
        </div>
      </div>
      <div class="form-slot">
        <ServiceRequestForm :categories="categories" :preselected-category="preselected" />
      </div>
    </div>
  </section>

  <!-- FAQ -->
  <section id="faq" class="section" aria-labelledby="faq-title">
    <div class="container-sf faq-grid">
      <header class="sec-head">
        <h2 id="faq-title">أسئلة بتتسأل كتير</h2>
        <p class="muted">لو سؤالك مش هنا، كلّم خدمة العملاء.</p>
      </header>
      <div class="faq">
        <details v-for="f in faqs" :key="f.q">
          <summary>{{ f.q }}</summary>
          <p>{{ f.a }}</p>
        </details>
      </div>
    </div>
  </section>

  <!-- TRACK -->
  <section class="track-band" aria-labelledby="track-title">
    <div class="container-sf band-grid">
      <div>
        <h2 id="track-title">عندك طلب قبل كده؟</h2>
        <p>اكتب رقم الطلب ورقم الموبايل اللي سجلت بيه، وشوف طلبك وصل لفين.</p>
      </div>
      <form class="band-form" @submit.prevent="goTrack">
        <label class="visually-hidden" for="lt-code">رقم الطلب</label>
        <input id="lt-code" v-model.trim="track.code" class="form-control num" placeholder="SR-2026-00014" required />
        <label class="visually-hidden" for="lt-phone">رقم الموبايل</label>
        <input id="lt-phone" v-model.trim="track.phone" class="form-control num" inputmode="tel" placeholder="01XXXXXXXXX" required />
        <button type="submit" class="btn-sf signal">تابع الطلب</button>
      </form>
    </div>
  </section>
</template>

<style scoped>
/* ---------- hero ---------- */
.hero { padding-block: 64px 40px; }
.hero-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 560px); gap: 56px; align-items: center; }
.pitch { display: grid; gap: 20px; }
.pitch h1 { font-size: clamp(2rem, 4.2vw, 3.1rem); line-height: 1.22; letter-spacing: -.01em; }
.lead { font-size: var(--fs-lg); color: var(--ink-2); max-width: 48ch; }
.ctas { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 4px; }

.preview { margin: 0; display: grid; gap: 10px; }
.preview-card { padding: 18px 16px 12px; box-shadow: 0 18px 40px -24px color-mix(in srgb, var(--ink) 45%, transparent); }
.preview-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 0 6px 16px; border-bottom: 1px dashed var(--line); margin-bottom: 18px; }
.preview-head div { display: grid; line-height: 1.4; }
.preview-head b { font-size: var(--fs-lg); }
.preview-foot { border-top: 1px dashed var(--line); margin-top: 8px; padding: 12px 6px 4px; font-size: var(--fs-sm); color: var(--ink-2); }
.preview figcaption { font-size: var(--fs-xs); text-align: center; }

.stats { margin: 48px 0 0; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); border-block: 1px solid var(--line); }
.stats div { padding: 20px 4px; display: grid; gap: 2px; }
.stats div + div { border-inline-start: 1px solid var(--line); padding-inline-start: 24px; }
.stats dt { order: 2; color: var(--ink-2); font-size: var(--fs-sm); font-weight: 500; }
.stats dd { order: 1; margin: 0; font-size: var(--fs-2xl); font-weight: 700; line-height: 1.2; }
.stats-note { font-size: var(--fs-xs); margin-top: 8px; }

/* ---------- sections ---------- */
.section { padding-block: 80px; border-top: 1px solid var(--line); scroll-margin-top: 70px; }
.section.alt { background: var(--surface); }
.sec-head { display: grid; gap: 8px; margin-bottom: 36px; max-width: 60ch; }
.sec-head h2 { font-size: var(--fs-2xl); }
.mini-title { font-size: var(--fs-md); margin-bottom: 12px; }
.fine { font-size: var(--fs-xs); margin-top: 12px; }

/* services: a ruled grid, not floating cards */
.services { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); border-top: 1px solid var(--line); border-inline-start: 1px solid var(--line); }
.services li { display: grid; grid-template-rows: auto 1fr auto; gap: 12px; padding: 22px; border-bottom: 1px solid var(--line); border-inline-end: 1px solid var(--line); background: var(--surface); }
.icon { width: 44px; height: 44px; border-radius: 10px; background: var(--paper); color: var(--ink); display: grid; place-items: center; box-shadow: inset 0 0 0 1px var(--line); }
.icon svg { width: 24px; height: 24px; }
.svc-text h3 { font-size: var(--fs-lg); margin-bottom: 4px; }
.svc-text p { font-size: var(--fs-sm); }
.svc-btn { justify-self: start; border: 0; background: none; padding: 0; color: var(--signal); font-weight: 700; font-size: var(--fs-sm); }
.svc-btn:hover { text-decoration: underline; }
.services li:hover .icon { background: var(--ink); color: var(--paper); box-shadow: none; }

/* how */
.how { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 28px; position: relative; }
.how::before { content: ""; position: absolute; top: 21px; inset-inline: 24px; height: 6px; border-radius: 3px; background: repeating-linear-gradient(-45deg, var(--rail) 0 8px, transparent 8px 12px); }
.how li { position: relative; display: grid; gap: 8px; align-content: start; }
.how .node { width: 48px; height: 48px; border-radius: 50%; display: grid; place-items: center; background: var(--ink); color: var(--paper); font-weight: 700; font-size: var(--fs-lg); margin-bottom: 8px; }
.how li:first-child .node { background: var(--signal); color: var(--signal-ink); box-shadow: 0 0 0 6px var(--signal-soft); }
.how h3 { font-size: var(--fs-lg); }
.how p { color: var(--ink-2); }
.how .who { justify-self: start; font-size: var(--fs-xs); color: var(--ink-2); border: 1px dashed var(--line); padding: 1px 8px; border-radius: 4px; }

/* promises */
.promise { display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr); gap: 24px; align-items: start; }
.sla td, .sla th { padding: 12px 10px; }
.inv-head { display: flex; justify-content: space-between; align-items: baseline; }
.inv { margin: 0; display: grid; gap: 8px; font-size: var(--fs-sm); }
.inv div { display: flex; justify-content: space-between; gap: 12px; color: var(--ink-2); }
.inv dd { margin: 0; }
.inv .sub { border-top: 1px solid var(--line); padding-top: 8px; color: var(--ink); }
.inv .grand { border-top: 2px solid var(--ink); padding-top: 10px; color: var(--ink); font-weight: 700; font-size: var(--fs-lg); }

/* request */
.request-grid { display: grid; grid-template-columns: minmax(0, 360px) minmax(0, 1fr); gap: 56px; align-items: start; }
.request-side { display: grid; gap: 14px; position: sticky; top: 96px; }
.request-side h2 { font-size: var(--fs-2xl); }
.assure { list-style: none; padding: 0; margin: 8px 0 0; display: grid; gap: 14px; }
.assure li { display: flex; gap: 10px; align-items: flex-start; color: var(--ink-2); }
.assure svg { width: 20px; height: 20px; color: var(--done); flex: none; margin-top: 3px; }
.assure b { color: var(--ink); }
.hotline { display: grid; gap: 2px; margin-top: 8px; padding: 14px 16px; border: 1px dashed var(--ink-3); border-radius: var(--r-sm); }
.hotline b { font-size: var(--fs-xl); user-select: all; }
.form-slot { scroll-margin-top: 90px; }

/* faq */
.faq-grid { display: grid; grid-template-columns: minmax(0, 340px) minmax(0, 1fr); gap: 56px; align-items: start; }
.faq { border-top: 1px solid var(--line); }
.faq details { border-bottom: 1px solid var(--line); }
.faq summary { list-style: none; cursor: pointer; padding: 18px 0; font-weight: 600; font-size: var(--fs-lg); display: flex; justify-content: space-between; gap: 16px; }
.faq summary::-webkit-details-marker { display: none; }
.faq summary::after { content: "+"; font-weight: 400; color: var(--ink-3); font-size: 1.4rem; line-height: 1; }
.faq details[open] summary::after { content: "−"; }
.faq p { color: var(--ink-2); padding-bottom: 18px; max-width: 65ch; }

/* track band */
.track-band { background: var(--ink); color: var(--paper); padding-block: 48px; }
.track-band h2 { color: var(--paper); font-size: var(--fs-xl); }
.track-band p { color: color-mix(in srgb, var(--paper) 72%, transparent); margin-top: 6px; }
.band-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr); gap: 32px; align-items: center; }
.band-form { display: grid; grid-template-columns: 1fr 1fr auto; gap: 10px; }
.band-form .form-control { background: color-mix(in srgb, var(--paper) 10%, var(--ink)); border-color: color-mix(in srgb, var(--paper) 25%, var(--ink)); color: var(--paper); }
.band-form .form-control::placeholder { color: color-mix(in srgb, var(--paper) 50%, transparent); }

@media (max-width: 1080px) {
  .services { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 960px) {
  .hero-grid, .promise, .request-grid, .faq-grid, .band-grid { grid-template-columns: minmax(0, 1fr); }
  .hero-grid { gap: 36px; }
  .how { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
  .how::before { display: none; }
  .request-side { position: static; }
  .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .stats div:nth-child(3) { border-inline-start: 0; padding-inline-start: 4px; }
  .stats div:nth-child(n+3) { border-top: 1px solid var(--line); }
}
@media (max-width: 560px) {
  .hero { padding-block: 32px 24px; }
  .section { padding-block: 56px; }
  .services, .how { grid-template-columns: minmax(0, 1fr); }
  .band-form { grid-template-columns: minmax(0, 1fr); }
  .ctas .btn-sf { flex: 1; }
}
@media (prefers-color-scheme: dark) {
  .track-band { background: var(--surface); border-block: 1px solid var(--line); color: var(--ink); }
  .track-band h2 { color: var(--ink); }
  .track-band p { color: var(--ink-2); }
  .band-form .form-control { background: var(--paper); border-color: var(--line); color: var(--ink); }
  .band-form .form-control::placeholder { color: var(--ink-3); }
}
</style>
