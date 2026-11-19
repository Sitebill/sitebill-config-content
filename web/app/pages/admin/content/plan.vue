<script setup lang="ts">
import type { CmPublication } from '../../../utils/content'
import { CM_MOVE_PUBLICATION } from '../../../graphql/content/documents'

// Контент-план: таблица и канбан-доска (статус меняется перетаскиванием карточки).
definePageMeta({ layout: 'admin', middleware: ['auth'] })

const { t, locale } = useI18n()
const ws = useContentWorkspace()

const filter = useState('cm-plan-filter', () => ({ q: '', status: '', sphere: '', placed: '', platform: '', sort: 'date' }))
const mode = useState<'table' | 'board'>('cm-plan-mode', () => 'table')
onMounted(() => { try { mode.value = (localStorage.getItem('cm.view') as any) || mode.value } catch { /* ignore */ } })
watch(mode, (v) => { try { localStorage.setItem('cm.view', v) } catch { /* ignore */ } })

// Площадки у каждого проекта свои, но называются одинаково: в фильтре схлопываем их по типу.
const platformOptions = computed(() => {
  const seen = new Map<string, string>()
  ws.scopedPlatforms.value.forEach(pf => { if (!seen.has(pf.kind)) seen.set(pf.kind, pf.name) })
  return [...seen.entries()]
})

const hasPublished = (p: CmPublication) => (p.placements || []).some(pl => pl.status === 'published')

const list = computed(() => {
  const f = filter.value
  const q = f.q.trim().toLowerCase()
  const kindOf = (id: number | string) => ws.platformById.value.get(Number(id))?.kind
  const byDate = (a: CmPublication, b: CmPublication) => (a.planned_date || '9999').localeCompare(b.planned_date || '9999') || (a.planned_time || '').localeCompare(b.planned_time || '')
  const sorters: Record<string, (a: CmPublication, b: CmPublication) => number> = {
    new: (a, b) => Number(b.id) - Number(a.id),
    old: (a, b) => Number(a.id) - Number(b.id),
    date: byDate,
    title: (a, b) => a.title.localeCompare(b.title, locale.value),
  }
  return ws.scoped.value
    .filter(p => (!f.status || p.status === f.status) && (!f.sphere || p.sphere === f.sphere)
      && (!f.placed || (f.placed === 'placed' ? hasPublished(p) : !hasPublished(p)))
      && (!f.platform || (p.placements || []).some(pl => kindOf(pl.platform_id) === f.platform))
      && (!q || `${p.title} ${p.rubric || ''} ${p.body || ''} ${p.comment || ''}`.toLowerCase().includes(q)))
    .sort(sorters[f.sort] || byDate)
})

// ---- доска: перетаскивание меняет статус (бэкенд проверяет право на «готово» и «опубликовано»)
const dragId = ref<string | null>(null)
const over = ref('')
async function drop(status: string) {
  over.value = ''
  const id = dragId.value
  dragId.value = null
  const p = ws.pubs.value.find(x => String(x.id) === String(id))
  if (p && p.status !== status) await ws.run(CM_MOVE_PUBLICATION, { id, status })
}

const when = (p: CmPublication) => p.planned_date ? cmDayTitle(locale.value, p.planned_date, false) + (p.planned_time ? ' ' + p.planned_time : '') : '—'

/** Ссылки на опубликованное по площадкам; если их нет — ссылка из карточки публикации. */
function links(p: CmPublication): Array<{ key: string; href: string; label: string }> {
  const out = (p.placements || []).filter(pl => pl.url).map(pl => ({ key: pl.id, href: pl.url!, label: ws.platformById.value.get(Number(pl.platform_id))?.name || pl.url! }))
  if (!out.length && p.link) out.push({ key: 'l', href: p.link, label: p.link.replace(/^https?:\/\//, '').slice(0, 30) })
  return out
}
const projectColor = (p: CmPublication) => ws.projectById.value.get(Number(p.project_id))?.color || '#94a3b8'
</script>

<template>
  <ConfigsContentFrame title-key="content.nav_plan">
    <template #actions="{ openPub }">
      <div class="inline-flex gap-0.5 rounded-xl bg-elevated p-0.5">
        <button v-for="m in (['table', 'board'] as const)" :key="m" type="button" class="rounded-lg px-3 py-1 text-sm font-semibold" :class="mode === m ? 'bg-default shadow-sm' : 'text-muted'" @click="mode = m">
          {{ t('content.view_' + m) }}
        </button>
      </div>
      <UButton v-if="ws.canWrite.value" icon="i-lucide-plus" :label="t('content.pub_new')" @click="openPub(null)" />
    </template>

    <template #default="{ openPub }">
      <div class="mb-3 flex flex-wrap items-center gap-2">
        <input v-model="filter.q" :class="CM_FIELD" class="!w-52" :placeholder="t('content.search')">
        <select v-model="filter.sphere" :class="CM_FIELD" class="!w-auto"><option value="">{{ t('content.all_types') }}</option><option v-for="s in CM_SPHERES" :key="s" :value="s">{{ t('content.sphere_' + s) }}</option></select>
        <select v-model="filter.status" :class="CM_FIELD" class="!w-auto"><option value="">{{ t('content.all_statuses') }}</option><option v-for="s in CM_STATUSES" :key="s" :value="s">{{ t('content.status_' + s) }}</option></select>
        <select v-model="filter.placed" :class="CM_FIELD" class="!w-auto"><option value="">{{ t('content.show_all') }}</option><option v-for="s in CM_PLACED_FILTERS" :key="s" :value="s">{{ t('content.placed_' + s) }}</option></select>
        <select v-model="filter.platform" :class="CM_FIELD" class="!w-auto"><option value="">{{ t('content.all_channels') }}</option><option v-for="[k, name] in platformOptions" :key="k" :value="k">{{ name }}</option></select>
        <select v-model="filter.sort" :class="CM_FIELD" class="!w-auto"><option v-for="s in CM_SORTS" :key="s" :value="s">{{ t('content.sort_' + s) }}</option></select>
        <span class="text-sm text-muted">{{ t('content.n_items', { n: list.length }) }}</span>
      </div>

      <UCard v-if="!list.length"><p class="py-6 text-center text-muted">{{ t('content.plan_empty') }}</p></UCard>

      <UCard v-else-if="mode === 'table'" :ui="{ body: 'p-0 sm:p-0 overflow-x-auto' }">
        <table class="w-full border-collapse text-sm">
          <thead>
            <tr class="text-left text-xs font-semibold text-muted">
              <th v-for="k in ['col_num', 'col_date', 'col_title', 'col_sphere', 'col_status', 'col_channels', 'col_link', 'col_comment']" :key="k" class="whitespace-nowrap border-b border-default px-3 py-2">{{ t('content.' + k) }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(p, i) in list" :key="p.id" class="cursor-pointer hover:bg-primary/5" @click="openPub(p)">
              <td class="w-9 border-b border-default px-3 py-2.5 text-muted">{{ i + 1 }}</td>
              <td class="whitespace-nowrap border-b border-default px-3 py-2.5" :class="cmIsOverdue(p) ? 'font-semibold text-error' : ''">{{ when(p) }}</td>
              <td class="border-b border-default px-3 py-2.5">
                <b class="flex items-center gap-1.5 font-semibold text-highlighted"><span class="size-2.5 flex-none rounded-full" :style="{ background: projectColor(p) }" />{{ p.title }}</b>
                <span class="text-xs text-muted">{{ [t('content.kind_' + p.kind), p.rubric].filter(Boolean).join(' · ') }}</span>
              </td>
              <td class="border-b border-default px-3 py-2.5"><UBadge variant="subtle" color="neutral" :style="{ color: CM_SPHERE_COLOR[p.sphere] }" :label="t('content.sphere_' + (p.sphere || 'other'))" /></td>
              <td class="border-b border-default px-3 py-2.5"><ConfigsContentStatusChip :status="p.status" /></td>
              <td class="border-b border-default px-3 py-2.5"><ConfigsContentChannelChips :pub="p" /></td>
              <td class="border-b border-default px-3 py-2.5">
                <template v-if="links(p).length">
                  <a v-for="l in links(p)" :key="l.key" :href="l.href" target="_blank" rel="noopener noreferrer" class="block whitespace-nowrap text-primary hover:underline" @click.stop>↗ {{ l.label }}</a>
                </template>
                <span v-else class="text-muted">—</span>
              </td>
              <td class="max-w-56 border-b border-default px-3 py-2.5 text-xs text-muted">{{ p.comment || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </UCard>

      <div v-else class="flex items-start gap-3 overflow-x-auto pb-3">
        <div
          v-for="s in CM_STATUSES" :key="s" class="min-h-32 w-64 flex-none rounded-2xl bg-elevated/60 p-2.5" :class="over === s ? 'outline-2 outline-dashed outline-primary' : ''"
          @dragover.prevent="over = s" @dragleave="over === s && (over = '')" @drop.prevent="drop(s)"
        >
          <h3 class="mx-1 mb-2.5 flex items-center gap-2 text-[13px] font-bold">
            <span class="size-2.5 rounded-full" :style="{ background: CM_STATUS_COLOR[s] }" />{{ t('content.status_' + s) }}
            <em class="ml-auto font-semibold not-italic text-muted">{{ list.filter(p => p.status === s).length }}</em>
          </h3>
          <div
            v-for="p in list.filter(x => x.status === s)" :key="p.id" draggable="true"
            class="mb-2 flex cursor-grab flex-col gap-1.5 rounded-xl border border-default bg-default p-2.5 hover:border-primary" @dragstart="dragId = p.id" @click="openPub(p)"
          >
            <b class="font-semibold leading-snug text-highlighted">{{ p.title }}</b>
            <small class="text-xs text-muted">{{ [p.planned_date ? when(p) : t('content.no_date'), t('content.sphere_' + (p.sphere || 'other'))].join(' · ') }}</small>
            <ConfigsContentChannelChips :pub="p" />
          </div>
        </div>
      </div>
    </template>
  </ConfigsContentFrame>
</template>
