<script setup lang="ts">
/**
 * Выбор целевых аудиторий из библиотеки (раздел «Целевая аудитория»). В поле проекта/площадки хранится строка:
 * названия записей через «; » — так её читает бренд-память ИИ (бэкенд подставляет описание по названию).
 * Значения, которых нет в библиотеке (старые, введённые вручную), остаются «своими» метками и снимаются крестиком.
 */
const model = defineModel<string>({ default: '' })
const props = withDefaults(defineProps<{ max?: number }>(), { max: 300 })

const { t } = useI18n()
const ws = useContentWorkspace()
const SEP = '; '

const library = computed(() => ws.materials.value.filter(m => m.kind === 'audience'))
const parts = computed(() => (model.value || '').split(';').map(s => s.trim()).filter(Boolean))
const titles = computed(() => new Set(library.value.map(m => m.title)))
const custom = computed(() => parts.value.filter(p => !titles.value.has(p)))

function write(next: string[]) { model.value = next.join(SEP) }
function toggle(title: string) {
  if (parts.value.includes(title)) { write(parts.value.filter(p => p !== title)); return }
  const next = [...parts.value, title]
  if (next.join(SEP).length <= props.max) write(next)
}
const full = (title: string) => !parts.value.includes(title) && [...parts.value, title].join(SEP).length > props.max
</script>

<template>
  <div class="space-y-2">
    <div v-if="library.length" class="flex flex-wrap gap-1.5">
      <button
        v-for="m in library" :key="m.id" type="button" :disabled="full(m.title)" :title="cmPlain(m.body)"
        class="inline-flex items-center gap-1 rounded-lg border px-2.5 py-1 text-sm disabled:cursor-not-allowed disabled:opacity-40"
        :class="parts.includes(m.title) ? 'border-primary bg-primary/10' : 'border-default hover:bg-elevated'" @click="toggle(m.title)"
      >
        <UIcon v-if="parts.includes(m.title)" name="i-lucide-check" class="size-3.5" />{{ m.title }}
      </button>
    </div>
    <p v-else class="text-sm text-muted">
      {{ t('content.audience_empty') }}
      <NuxtLink to="/admin/content/audiences" class="text-primary hover:underline">{{ t('content.audience_open_library') }}</NuxtLink>
    </p>
    <div v-if="custom.length" class="flex flex-wrap items-center gap-1.5 text-xs text-muted">
      <span>{{ t('content.audience_custom') }}:</span>
      <button v-for="c in custom" :key="c" type="button" class="inline-flex items-center gap-1 rounded-md border border-dashed border-default px-2 py-0.5" :title="t('content.audience_remove')" @click="write(parts.filter(p => p !== c))">
        {{ c }}<UIcon name="i-lucide-x" class="size-3" />
      </button>
    </div>
  </div>
</template>
