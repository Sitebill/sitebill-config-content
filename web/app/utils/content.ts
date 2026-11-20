// Константы и хелперы контент-фабрики (configs/content). Имена с префиксом cm*/CM_*: файлы utils/ слоёв
// импортируются автоматически и не должны пересекаться с именами ядра.

// Статусы публикации. `review` — «на согласовании»: его ставит автор, переводит дальше согласующий или редактор.
export const CM_STATUSES = ['idea', 'draft', 'in_work', 'review', 'ready', 'published', 'none'] as const
export const CM_STATUS_COLOR: Record<string, string> = { none: '#94a3b8', idea: '#64748b', draft: '#0ea5e9', in_work: '#f59e0b', review: '#a855f7', ready: '#6366f1', published: '#10b981' }
// Эти статусы автор поставить не может (бэкенд: ContentAccess::APPROVED_STATUSES).
export const CM_APPROVED_STATUSES = ['ready', 'published']
export const CM_OPEN_STATUSES = ['idea', 'draft', 'in_work', 'review', 'ready']
export const CM_SPHERES = ['work', 'art', 'other'] as const
export const CM_SPHERE_COLOR: Record<string, string> = { work: '#0d9488', art: '#a855f7', other: '#94a3b8' }
export const CM_EVENT_KINDS = ['birthday', 'holiday', 'doctor', 'other'] as const
export const CM_EVENT_ICON: Record<string, string> = { birthday: '🎂', holiday: '🎉', doctor: '🩺', other: '📌' }
export const CM_PLACED_FILTERS = ['placed', 'not_placed'] as const
export const CM_SORTS = ['new', 'old', 'date', 'title'] as const
export const CM_KINDS = ['post', 'article', 'news', 'case', 'video', 'announcement'] as const
export const CM_PRIORITIES = ['low', 'normal', 'high'] as const
export const CM_PLACEMENT_STATUSES = ['todo', 'in_work', 'scheduled', 'published', 'skipped'] as const
export const CM_PLATFORM_KINDS = ['dzen', 'vk', 'avtor_today', 'max', 'ok', 'vcru', 'workspace', 'tenchat', 'looky', 'telegram', 'tiktok', 'youtube', 'rutube', 'habr', 'site', 'blog', 'email', 'other'] as const
export const CM_PLATFORM_ICON: Record<string, string> = { telegram: '✈️', vk: '💬', dzen: '📰', ok: '🟠', tiktok: '🎵', youtube: '▶️', rutube: '📺', habr: '🧑‍💻', vcru: '📈', site: '🌐', blog: '✍️', email: '✉️', avtor_today: '🖋️', max: '💠', workspace: '🗂️', tenchat: '💼', looky: '👀', other: '🔗' }
export const CM_MATERIAL_KINDS = ['book', 'diary', 'note', 'audience', 'idea', 'hashtags', 'case'] as const
export const CM_MATERIAL_ICON: Record<string, string> = { book: '📚', diary: '📔', note: '📝', audience: '🎯', idea: '💡', hashtags: '#️⃣', case: '🗂️' }
export const CM_BOOK_STATES = ['read', 'planned', 'best'] as const
export const CM_NOTE_STATES = ['todo', 'done'] as const
export const CM_COLORS = ['#0d9488', '#6366f1', '#f59e0b', '#ef4444', '#0ea5e9', '#ec4899', '#8b5cf6', '#10b981']
export const CM_SEVERITY_COLOR: Record<string, string> = { bad: '#ef4444', warn: '#f59e0b', info: '#6366f1' }
export const CM_ROLES = ['editor', 'author', 'approver', 'viewer'] as const

/** Markdown → простой текст для превью в списках (без разметки и ссылок-обёрток). */
export function cmPlain(md: string | null | undefined): string {
  return (md || '')
    .replace(/!\[[^\]]*\]\([^)]*\)/g, '')
    .replace(/\[([^\]]+)\]\([^)]*\)/g, '$1')
    .replace(/^\s{0,3}(#{1,6}|>|[-*+]|\d+\.)\s+/gm, '')
    .replace(/\\([\\`*_{}[\]()#+.!>~-])/g, '$1')
    .replace(/(\*\*|__|~~|\*|_|`)/g, '')
    .replace(/\n{3,}/g, '\n\n')
    .trim()
}

/** Общий стиль нативных select/input в формах (как в configs/appointments). */
export const CM_FIELD = 'h-9 w-full rounded-lg border border-default bg-default px-3 text-sm'

// ---------------------------------------------------------------- даты (строки Y-m-d, без часовых поясов)
const pad = (n: number) => String(n).padStart(2, '0')
export const cmIso = (d: Date) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
export const cmToday = () => cmIso(new Date())
export const cmAddDays = (s: string, n: number) => {
  const [y, m, d] = s.split('-').map(Number)
  return cmIso(new Date(y!, m! - 1, d! + n))
}
export const cmShiftYm = (ym: string, delta: number) => {
  const [y, m] = ym.split('-').map(Number)
  const d = new Date(y!, m! - 1 + delta, 1)
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}`
}
/** Первая буква заглавная (CSS capitalize портит «2026 г.» → «Г.»). */
export const cmUpFirst = (s: string) => s.charAt(0).toUpperCase() + s.slice(1)
export const cmMonthTitle = (locale: string, ym: string) => {
  const [y, m] = ym.split('-').map(Number)
  return new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' }).format(new Date(y!, m! - 1, 1))
}
export const cmDayTitle = (locale: string, s: string, withWeekday = true) => {
  const [y, m, d] = s.split('-').map(Number)
  return new Intl.DateTimeFormat(locale, withWeekday ? { weekday: 'short', day: 'numeric', month: 'long' } : { day: 'numeric', month: 'short' }).format(new Date(y!, m! - 1, d!))
}
export const cmWeekdays = (locale: string) => [...Array(7)].map((_, i) => new Intl.DateTimeFormat(locale, { weekday: 'short' }).format(new Date(2024, 0, 1 + i))) // 1 января 2024 — понедельник

export interface CmPlacement { id: string; platform_id: number; status: string; published_at?: string | null; url?: string | null; comment?: string | null }
export interface CmPublication {
  id: string; project_id: number; parent_id?: number | null; title: string; kind: string; rubric?: string | null; sphere: string; status: string
  priority: string; planned_date?: string | null; planned_time?: string | null; body?: string | null; hashtags?: string | null; link?: string | null
  assignee?: string | null; comment?: string | null; placement_state: string; placements: CmPlacement[]
}
export interface CmProject { id: string; name: string; description?: string | null; topic?: string | null; audience?: string | null; tone?: string | null; keywords?: string | null; competitors?: string | null; manager?: string | null; color: string }
export interface CmPlatform { id: string; project_id: number; name: string; kind: string; url?: string | null; audience?: string | null; formats?: string | null; max_length?: number | null; per_week?: number | null; api_status: string }
export interface CmMaterial { id: string; project_id?: number | null; kind: string; entry_date?: string | null; state?: string | null; sphere?: string | null; topic?: string | null; title: string; body?: string | null; tags?: string | null }
export interface CmEvent { id: string; kind: string; title: string; event_date: string; event_time?: string | null; comment?: string | null }
export interface CmReviewItem { code: string; severity: string; publication_id?: number | null; meta?: string | null }
export interface CmProposal { title: string; kind: string; rubric?: string | null; body?: string | null; hashtags?: string | null; planned_date?: string | null; planned_time?: string | null; platform_ids: number[] }

export const cmIsOpen = (p: CmPublication) => CM_OPEN_STATUSES.includes(p.status)
export const cmIsOverdue = (p: CmPublication, today = cmToday()) => !!p.planned_date && p.planned_date < today && !['published', 'none'].includes(p.status)

/** Доля плана, выполненная за последние 30 дней (опубликовано из того, что было назначено до сегодня). */
export function cmPlanCompletion(pubs: CmPublication[], today = cmToday()) {
  const from = cmAddDays(today, -30)
  const due = pubs.filter(p => p.planned_date && p.planned_date >= from && p.planned_date <= today)
  if (!due.length) return { pct: null as number | null, due: 0, done: 0 }
  const done = due.filter(p => p.status === 'published').length
  return { pct: Math.round(done * 100 / due.length), due: due.length, done }
}
