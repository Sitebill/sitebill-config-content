<script setup lang="ts">
import type { CmPainSection } from '../../../utils/content'

/**
 * Конструктор «Болей аудитории»: разделы (боли частных риелторов, зависимость от площадок…) со своими таблицами
 * «вид боли — описание» и «вопрос — ответ». Нумерацию ведёт сама таблица; строки и разделы добавляются свободно,
 * потому что у разных аудиторий число болей и вопросов разное. Результат — JSON, который хранит бэкенд и из которого
 * ИИ берёт отмеченные в публикации боли.
 */
const sections = defineModel<CmPainSection[]>({ required: true })
defineProps<{ disabled?: boolean }>()

const { t } = useI18n()
const { confirmDialog } = useConfirmDialog()

const uid = () => Math.random().toString(36).slice(2, 10)
const PRESETS = ['pains', 'interests', 'pay', 'objections'] as const

function addSection(preset?: typeof PRESETS[number]) {
  sections.value = [...sections.value, {
    id: uid(), title: preset ? t('content.pains_preset_' + preset) : '',
    pains: [{ id: uid(), title: '', description: '' }], questions: [],
  }]
}

async function removeSection(i: number) {
  if (await confirmDialog({ title: t('content.pains_confirm_section'), color: 'error' })) sections.value = sections.value.filter((_, k) => k !== i)
}

function move(i: number, d: -1 | 1) {
  const a = [...sections.value]
  const j = i + d
  if (j < 0 || j >= a.length) return;
  [a[i], a[j]] = [a[j]!, a[i]!]
  sections.value = a
}

const addPain = (s: CmPainSection) => s.pains.push({ id: uid(), title: '', description: '' })
const addQuestion = (s: CmPainSection) => s.questions.push({ id: uid(), question: '', answer: '' })
const removeAt = (list: unknown[], i: number) => list.splice(i, 1)

const GRID = 'grid grid-cols-[2rem_minmax(0,1fr)_2rem] gap-2 sm:grid-cols-[2.25rem_minmax(0,1fr)_minmax(0,1.6fr)_2rem]'
const AREA = 'w-full resize-y rounded-lg border border-default bg-default px-3 py-1.5 text-sm leading-5'
</script>

<template>
  <div class="space-y-4">
    <UCard v-for="(s, i) in sections" :key="s.id">
      <div class="mb-3 flex items-center gap-2">
        <input v-model="s.title" :class="CM_FIELD" class="!font-semibold" :placeholder="t('content.pains_section_ph')" maxlength="200" :disabled="disabled">
        <template v-if="!disabled">
          <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-arrow-up" :disabled="i === 0" :title="t('content.pains_move_up')" @click="move(i, -1)" />
          <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-arrow-down" :disabled="i === sections.length - 1" :title="t('content.pains_move_down')" @click="move(i, 1)" />
          <UButton size="xs" color="error" variant="ghost" icon="i-lucide-trash-2" :title="t('content.pains_remove_section')" @click="removeSection(i)" />
        </template>
      </div>

      <!-- боли -->
      <div :class="GRID" class="mb-1 text-xs font-medium text-muted">
        <span>{{ t('content.pains_col_n') }}</span><span>{{ t('content.pains_col_pain') }}</span><span class="hidden sm:block">{{ t('content.pains_col_desc') }}</span><span />
      </div>
      <div v-for="(p, k) in s.pains" :key="p.id" :class="GRID" class="mb-2 items-start">
        <span class="pt-1.5 text-sm text-muted">{{ k + 1 }}</span>
        <textarea v-model="p.title" rows="1" maxlength="200" :class="AREA" :disabled="disabled" :placeholder="t('content.pains_col_pain')" />
        <textarea v-model="p.description" rows="2" maxlength="4000" :class="AREA" class="col-span-2 sm:col-span-1" :disabled="disabled" :placeholder="t('content.pains_col_desc')" />
        <UButton v-if="!disabled" size="xs" color="neutral" variant="ghost" icon="i-lucide-x" :title="t('content.pains_remove_row')" @click="removeAt(s.pains, k)" />
      </div>
      <UButton v-if="!disabled" size="xs" color="neutral" variant="outline" icon="i-lucide-plus" :label="t('content.pains_add_pain')" @click="addPain(s)" />

      <!-- вопросы и ответы -->
      <template v-if="s.questions.length || !disabled">
        <div v-if="s.questions.length" :class="GRID" class="mb-1 mt-5 text-xs font-medium text-muted">
          <span>{{ t('content.pains_col_n') }}</span><span>{{ t('content.pains_col_q') }}</span><span class="hidden sm:block">{{ t('content.pains_col_a') }}</span><span />
        </div>
        <div v-for="(q, k) in s.questions" :key="q.id" :class="GRID" class="mb-2 items-start">
          <span class="pt-1.5 text-sm text-muted">{{ k + 1 }}</span>
          <textarea v-model="q.question" rows="1" maxlength="500" :class="AREA" :disabled="disabled" :placeholder="t('content.pains_col_q')" />
          <textarea v-model="q.answer" rows="2" maxlength="4000" :class="AREA" class="col-span-2 sm:col-span-1" :disabled="disabled" :placeholder="t('content.pains_col_a')" />
          <UButton v-if="!disabled" size="xs" color="neutral" variant="ghost" icon="i-lucide-x" :title="t('content.pains_remove_row')" @click="removeAt(s.questions, k)" />
        </div>
        <UButton v-if="!disabled" size="xs" color="neutral" variant="outline" icon="i-lucide-plus" :label="t('content.pains_add_q')" @click="addQuestion(s)" />
      </template>
    </UCard>

    <p v-if="!sections.length" class="py-4 text-center text-sm text-muted">{{ t('content.pains_empty') }}</p>

    <div v-if="!disabled" class="flex flex-wrap items-center gap-2">
      <span class="text-sm text-muted">{{ t('content.pains_add_section') }}:</span>
      <UButton v-for="p in PRESETS" :key="p" size="xs" color="neutral" variant="soft" icon="i-lucide-plus" :label="t('content.pains_preset_' + p)" @click="addSection(p)" />
      <UButton size="xs" color="neutral" variant="outline" icon="i-lucide-plus" :label="t('content.pains_preset_custom')" @click="addSection()" />
    </div>
  </div>
</template>
