<script setup lang="ts">
import type { CmReviewItem } from '../../../utils/content'

// Находки проверки плана (правила бэкенда PlanAgent::review). Бэкенд отдаёт код и короткую мету, текст — здесь.
const props = withDefaults(defineProps<{ reviews: CmReviewItem[]; limit?: number }>(), { limit: 8 })
const { t } = useI18n()
const ws = useContentWorkspace()
const openPub = inject<(p: any, d?: Record<string, any>) => void>('cmOpenPub')

function text(item: CmReviewItem): { title: string; text: string } {
  const parts = String(item.meta ?? '').split('|')
  const r = (k: string, vars: Record<string, unknown> = {}) => t('content.review_' + k, vars)
  switch (item.code) {
    case 'overdue': case 'unprepared': return { title: r(item.code), text: parts[0]! }
    case 'review': return { title: r('review'), text: r('n', { n: parts[0] }) }
    case 'unplaced': return { title: r('unplaced'), text: `${parts[0]} → ${parts[1] || ''}` }
    case 'stale_rubric': return { title: r('stale_rubric'), text: r('stale_rubric_t', { rubric: parts[0] }) }
    case 'empty_days': return { title: r('empty_days'), text: r('empty_days_t', { n: parts[0] }) }
    case 'monotony': return { title: r('monotony'), text: r('monotony_t', { kind: t('content.kind_' + parts[0]) }) }
    case 'low_plan': return { title: r('low_plan'), text: r('low_plan_t', { pct: parts[0] }) }
    case 'weak_channel': return { title: r('weak_channel'), text: r('weak_channel_t', { name: parts[0], pct: parts[1] }) }
    default: return { title: item.code, text: item.meta || '' }
  }
}

const rows = computed(() => props.reviews.slice(0, props.limit).map(r => ({
  r,
  ...text(r),
  pub: r.publication_id ? ws.pubs.value.find(p => Number(p.id) === Number(r.publication_id)) ?? null : null,
})))
</script>

<template>
  <div v-if="rows.length" class="space-y-2">
    <div
      v-for="(row, i) in rows" :key="i"
      class="rounded-xl border-l-[3px] px-3 py-2.5"
      :class="row.pub ? 'cursor-pointer' : ''"
      :style="{ borderColor: CM_SEVERITY_COLOR[row.r.severity], background: `color-mix(in srgb, ${CM_SEVERITY_COLOR[row.r.severity]} 11%, transparent)` }"
      @click="row.pub && openPub?.(row.pub)"
    >
      <b class="block font-semibold text-highlighted">{{ row.title }}</b>
      <span class="text-[13px] text-muted">{{ row.text }}</span>
    </div>
  </div>
  <p v-else class="text-sm text-muted">✅ {{ t('content.review_ok') }}</p>
</template>
