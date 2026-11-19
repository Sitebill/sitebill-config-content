<script setup lang="ts">
import type { CmPublication } from '../../../utils/content'

// Площадки публикации с подсветкой размещённых и «в работе».
const props = defineProps<{ pub: CmPublication }>()
const ws = useContentWorkspace()

const chips = computed(() => (props.pub.placements || []).flatMap((pl) => {
  const pf = ws.platformById.value.get(Number(pl.platform_id))
  return pf ? [{ id: pl.id, status: pl.status, icon: CM_PLATFORM_ICON[pf.kind] || '🔗', name: pf.name }] : []
}))

function cls(status: string) {
  if (status === 'published') return 'border-transparent bg-success/15 text-success'
  if (status === 'in_work' || status === 'scheduled') return 'border-transparent bg-warning/15 text-warning'
  if (status === 'skipped') return 'border-default text-muted line-through opacity-60'
  return 'border-default text-muted'
}
</script>

<template>
  <span class="flex flex-wrap gap-1">
    <span v-for="c in chips" :key="c.id" class="inline-flex items-center gap-1 rounded-lg border px-1.5 py-px text-[11.5px]" :class="cls(c.status)" :title="c.status">
      {{ c.icon }} {{ c.name }}
    </span>
  </span>
</template>
