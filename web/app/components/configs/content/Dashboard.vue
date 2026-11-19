<script setup lang="ts">
import type { CmReviewItem } from '../../../utils/content'
import { CM_REVIEW } from '../../../graphql/content/documents'

/**
 * Обзор: выполнение плана, задачи на сегодня, ближайшие 7 дней, материалы в работе / на согласовании /
 * «размещено не везде» и находки проверки плана. Используется как дашборд /admin и как главная для вошедших.
 */
const { t, locale } = useI18n()
const ws = useContentWorkspace()

const reviews = ref<CmReviewItem[]>([])
async function loadReview() {
  if (ws.denied.value || ws.loading.value) return
  try {
    const data = await ws.query(CM_REVIEW, { project_id: ws.filter.value ? Number(ws.filter.value) : null, today: cmToday() })
    reviews.value = data.contentReview
  } catch { reviews.value = [] }
}
watch(() => [ws.loading.value, ws.filter.value, ws.pubs.value], loadReview, { immediate: true })

const TODO_KEY: Record<string, string> = { idea: 'todo_prepare', draft: 'todo_finish', in_work: 'todo_finish', review: 'todo_finish', ready: 'todo_publish' }

const d = computed(() => {
  const today = cmToday()
  const pubs = ws.scoped.value
  const week = pubs.filter(p => p.planned_date && p.planned_date >= today && p.planned_date < cmAddDays(today, 7))
  const overdue = pubs.filter(p => cmIsOverdue(p, today)).sort((a, b) => a.planned_date!.localeCompare(b.planned_date!))
  const todayItems = pubs.filter(p => p.planned_date === today && p.status !== 'published')
  const todo = [
    ...overdue.map(p => ({ p, key: 'todo_overdue' })),
    ...todayItems.map(p => ({ p, key: TODO_KEY[p.status] || 'todo_publish' })),
  ]
  const upcoming = pubs
    .filter(p => p.planned_date && p.planned_date > today && p.planned_date <= cmAddDays(today, 7) && p.status !== 'published')
    .sort((a, b) => (a.planned_date! + (a.planned_time || '')).localeCompare(b.planned_date! + (b.planned_time || '')))
  const byDay = new Map<string, typeof upcoming>()
  upcoming.forEach(p => byDay.set(p.planned_date!, [...(byDay.get(p.planned_date!) || []), p]))
  return {
    week, overdue, todo, upcoming, byDay: [...byDay.entries()],
    completion: cmPlanCompletion(pubs, today),
    published30: pubs.filter(p => p.status === 'published' && p.planned_date && p.planned_date >= cmAddDays(today, -30)).length,
    inWork: pubs.filter(p => p.status === 'in_work'),
    // «На согласовании» — и новый статус review, и «готов к публикации», пока не вышел
    inReview: pubs.filter(p => p.status === 'review' || p.status === 'ready'),
    partial: pubs.filter(p => p.placement_state === 'partial'),
  }
})

const ring = (pct: number) => ({ background: `conic-gradient(var(--ui-primary) ${pct}%, var(--ui-border) 0)` })
</script>

<template>
  <ConfigsContentFrame title-key="content.nav_dashboard">
    <template #actions="{ openPub }">
      <UButton v-if="ws.canWrite.value" icon="i-lucide-plus" :label="t('content.pub_new')" @click="openPub(null)" />
    </template>

    <div class="grid gap-4">
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <UCard>
          <div class="flex items-center gap-3">
            <div class="grid size-16 flex-none place-items-center rounded-full" :style="ring(d.completion.pct ?? 0)">
              <span class="grid size-12 place-items-center rounded-full bg-default text-sm font-bold">{{ d.completion.pct ?? 0 }}%</span>
            </div>
            <div>
              <div class="text-xs text-muted">{{ t('content.kpi_completion') }}</div>
              <div class="text-xs text-muted">{{ d.completion.pct == null ? t('content.kpi_no_data') : t('content.kpi_completion_note', { done: d.completion.done, due: d.completion.due }) }}</div>
            </div>
          </div>
        </UCard>
        <UCard>
          <div class="text-xs text-muted">{{ t('content.kpi_published') }}</div>
          <div class="text-3xl font-bold text-highlighted">{{ d.published30 }}</div>
          <div class="text-xs text-muted">{{ t('content.kpi_30d') }}</div>
        </UCard>
        <UCard>
          <div class="text-xs text-muted">{{ t('content.kpi_week') }}</div>
          <div class="text-3xl font-bold text-highlighted">{{ d.week.length }}</div>
          <div class="text-xs text-muted">{{ t('content.kpi_next7') }}</div>
        </UCard>
        <UCard>
          <div class="text-xs text-muted">{{ t('content.kpi_overdue') }}</div>
          <div class="text-3xl font-bold" :class="d.overdue.length ? 'text-error' : 'text-highlighted'">{{ d.overdue.length }}</div>
          <div class="text-xs text-muted">{{ d.overdue.length ? t('content.kpi_overdue_bad') : t('content.kpi_overdue_ok') }}</div>
        </UCard>
      </div>

      <div class="grid gap-4 lg:grid-cols-[minmax(0,1.25fr)_minmax(0,1fr)]">
        <div class="grid content-start gap-4">
          <UCard>
            <h3 class="mb-3 flex items-center justify-between font-semibold text-highlighted">{{ t('content.todo_today') }}<small class="font-medium text-muted">{{ d.todo.length }}</small></h3>
            <template v-if="d.todo.length">
              <ConfigsContentPubRow v-for="({ p, key }) in d.todo" :key="p.id + key" :pub="p">
                <UBadge :color="key === 'todo_overdue' ? 'error' : 'primary'" variant="subtle" :label="t('content.' + key)" />
              </ConfigsContentPubRow>
            </template>
            <p v-else class="text-sm text-muted">🎉 {{ t('content.todo_none') }}</p>
          </UCard>
          <UCard>
            <h3 class="mb-3 flex items-center justify-between font-semibold text-highlighted">{{ t('content.next7') }}<small class="font-medium text-muted">{{ d.upcoming.length }}</small></h3>
            <template v-if="d.byDay.length">
              <div v-for="[day, items] in d.byDay" :key="day">
                <div class="mb-0.5 mt-3 text-xs font-bold uppercase tracking-wide text-muted first:mt-0">{{ cmDayTitle(locale, day) }}</div>
                <ConfigsContentPubRow v-for="p in items" :key="p.id" :pub="p" />
              </div>
            </template>
            <p v-else class="text-sm text-muted">{{ t('content.next7_none') }}</p>
          </UCard>
        </div>

        <div class="grid content-start gap-4">
          <UCard>
            <h3 class="mb-3 flex items-center justify-between font-semibold text-highlighted">✨ {{ t('content.ai_recs') }}<small class="font-medium text-muted">{{ t('content.ai_rule_based') }}</small></h3>
            <ConfigsContentReviewList :reviews="reviews" :limit="7" />
          </UCard>
          <UCard>
            <h3 class="mb-3 flex items-center justify-between font-semibold text-highlighted">{{ t('content.in_work') }}<small class="font-medium text-muted">{{ d.inWork.length }}</small></h3>
            <template v-if="d.inWork.length">
              <ConfigsContentPubRow v-for="p in d.inWork.slice(0, 5)" :key="p.id" :pub="p"><ConfigsContentChannelChips :pub="p" /></ConfigsContentPubRow>
            </template>
            <p v-else class="text-sm text-muted">{{ t('content.none') }}</p>
          </UCard>
          <UCard>
            <h3 class="mb-3 flex items-center justify-between font-semibold text-highlighted">{{ t('content.in_review') }}<small class="font-medium text-muted">{{ d.inReview.length }}</small></h3>
            <template v-if="d.inReview.length"><ConfigsContentPubRow v-for="p in d.inReview.slice(0, 5)" :key="p.id" :pub="p" /></template>
            <p v-else class="text-sm text-muted">{{ t('content.none') }}</p>
          </UCard>
          <UCard>
            <h3 class="mb-3 flex items-center justify-between font-semibold text-highlighted">{{ t('content.not_everywhere') }}<small class="font-medium text-muted">{{ d.partial.length }}</small></h3>
            <template v-if="d.partial.length">
              <ConfigsContentPubRow v-for="p in d.partial.slice(0, 5)" :key="p.id" :pub="p"><ConfigsContentChannelChips :pub="p" /></ConfigsContentPubRow>
            </template>
            <p v-else class="text-sm text-muted">{{ t('content.none') }}</p>
          </UCard>
        </div>
      </div>
    </div>
  </ConfigsContentFrame>
</template>
