const timeFmt = new Intl.DateTimeFormat('ar-EG-u-nu-latn', { hour: 'numeric', minute: '2-digit' })
const dateFmt = new Intl.DateTimeFormat('ar-EG-u-nu-latn', { day: 'numeric', month: 'long' })
const dateTimeFmt = new Intl.DateTimeFormat('ar-EG-u-nu-latn', { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' })

export const formatTime = (iso) => (iso ? timeFmt.format(new Date(iso)) : '')
export const formatDate = (iso) => (iso ? dateFmt.format(new Date(iso)) : '')
export const formatDateTime = (iso) => (iso ? dateTimeFmt.format(new Date(iso)) : '')

export function formatMoney(value) {
  const n = Number(value || 0)
  return `${n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ج.م`
}

/** "3 س 20 د" style duration. */
export function formatDuration(ms) {
  const mins = Math.round(Math.abs(ms) / 60000)
  const h = Math.floor(mins / 60)
  const m = mins % 60
  if (h >= 48) return `${Math.floor(h / 24)} يوم`
  return h ? `${h} س${m ? ` ${m} د` : ''}` : `${m} د`
}

/** SLA state for a due date: { late, text }. */
export function slaState(dueIso, now = Date.now()) {
  const left = new Date(dueIso).getTime() - now
  return left < 0
    ? { late: true, text: `متأخر ${formatDuration(left)}` }
    : { late: false, text: `متبقٍ ${formatDuration(left)}` }
}

export function initials(name = '') {
  return name.split(' ').filter(Boolean).slice(0, 2).map((w) => w[0]).join(' ')
}
