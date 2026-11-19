<script setup lang="ts">
// Чип статуса публикации: цвет берётся из CM_STATUS_COLOR, подпись — content.status_<статус>.
const props = defineProps<{ status: string }>()
const { t } = useI18n()
const color = computed(() => CM_STATUS_COLOR[props.status] || '#64748b')
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold"
    :style="{ color, background: `color-mix(in srgb, ${color} 16%, transparent)` }"
  >
    <i class="size-1.5 rounded-full" :style="{ background: color }" />
    {{ t('content.status_' + status) }}
  </span>
</template>
