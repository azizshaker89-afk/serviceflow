import { onBeforeUnmount, onMounted, ref } from 'vue'

/**
 * Reads the chart colors from CSS tokens so light and dark mode are each a selected palette,
 * and bumps a version counter when the OS theme flips so charts redraw with the new values.
 */
export function useChartTheme() {
  const version = ref(0)
  let query

  const bump = () => { version.value++ }

  onMounted(() => {
    query = window.matchMedia('(prefers-color-scheme: dark)')
    query.addEventListener('change', bump)
  })
  onBeforeUnmount(() => query?.removeEventListener('change', bump))

  const token = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim()

  const theme = () => ({
    series: [token('--series-1'), token('--series-2')],
    ink: token('--ink'),
    ink2: token('--ink-2'),
    surface: token('--surface'),
    grid: token('--chart-grid'),
    axis: token('--chart-axis'),
    font: token('--font'),
    late: token('--late'),
    done: token('--done'),
    wait: token('--wait'),
    signal: token('--signal'),
    rail: token('--rail'),
  })

  return { version, theme }
}

/** Shared recessive axes and an RTL tooltip in the identity's ink. */
export function baseOptions(t, { money = false } = {}) {
  const fmt = (v) => (money ? `${Number(v).toLocaleString('en-US')} ج.م` : Number(v).toLocaleString('en-US'))

  return {
    responsive: true,
    maintainAspectRatio: false,
    locale: 'ar-EG-u-nu-latn',
    animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 500 },
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: { display: false },
      tooltip: {
        rtl: true,
        textDirection: 'rtl',
        backgroundColor: t.ink,
        titleColor: t.surface,
        bodyColor: t.surface,
        titleFont: { family: t.font, weight: '600' },
        bodyFont: { family: t.font },
        padding: 10,
        cornerRadius: 6,
        displayColors: true,
        boxPadding: 4,
        callbacks: { label: (ctx) => ` ${ctx.dataset.label}: ${fmt(ctx.parsed.y ?? ctx.parsed.x)}` },
      },
    },
    scales: {
      x: {
        reverse: true,
        grid: { display: false },
        border: { color: t.grid },
        ticks: { color: t.axis, font: { family: t.font, size: 11 }, maxRotation: 0, autoSkipPadding: 12 },
      },
      y: {
        position: 'right',
        beginAtZero: true,
        grid: { color: t.grid },
        border: { display: false },
        ticks: { color: t.axis, font: { family: t.font, size: 11 }, precision: 0, maxTicksLimit: 5, callback: (v) => (money ? `${Number(v) / 1000}k` : v) },
      },
    },
  }
}
