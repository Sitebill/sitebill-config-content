<script setup lang="ts">
import type { CmPublication } from '../../../utils/content'

// Строка публикации в списках обзора и календаря; клик открывает карточку. В слот можно положить свой «хвост».
const props = defineProps<{ pub: CmPublication }>()
const { t, locale } = useI18n()
const ws = useContentWorkspace()
const openPub = inject<(p: CmPublication | null, d?: Record<string, any>) => void>('cmOpenPub')

const when = computed(() => {
  const p = props.pub
  return p.planned_date ? cmDayTitle(locale.value, p.planned_date, false) + (p.planned_time ? ' ' + p.planned_time : '') : t('content.no_date')
})
const sub = computed(() => [
  when.value,
  props.pub.rubric,
  ws.projects.value.length > 1 ? ws.projectById.value.get(Number(props.pub.project_id))?.name : null,
].filter(Boolean).join(' · '))
</script>

<template>
  <div class="flex cursor-pointer items-center gap-3 border-t border-default py-2.5 first:border-t-0" @click="openPub?.(pub)">
    <span class="size-2.5 flex-none rounded-full" :style="{ background: ws.projectById.value.get(Number(pub.project_id))?.color || '#94a3b8' }" />
    <div class="min-w-0 flex-1">
      <b class="block truncate font-semibold text-highlighted">{{ pub.title }}</b>
      <span class="text-xs text-muted">{{ sub }}</span>
    </div>
    <slot><ConfigsContentStatusChip :status="pub.status" /></slot>
  </div>
</template>
