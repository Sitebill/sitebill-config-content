<script setup lang="ts">
import type { CmProject } from '../../../utils/content'
import { CM_SAVE_PROJECT } from '../../../graphql/content/documents'

// Проект — бренд или клиент, для которого делается контент: тематика, аудитория, тон и ключевые слова.
// Из этой карточки берут исходные данные ассистент и (в следующих фазах) ИИ-генераторы.
const props = defineProps<{ item: Partial<CmProject> }>()
const emit = defineEmits<{ close: []; saved: [id: string] }>()

const { t } = useI18n()
const ws = useContentWorkspace()
const it = props.item

const f = reactive({
  name: it.name || '', description: it.description || '', topic: it.topic || '', audience: it.audience || '', tone: it.tone || '',
  keywords: it.keywords || '', competitors: it.competitors || '', manager: it.manager || '', color: it.color || CM_COLORS[0]!,
})

async function submit() {
  const input = Object.fromEntries(Object.entries(f).map(([k, v]) => [k, typeof v === 'string' ? (v.trim() || null) : v]))
  input.name = f.name.trim()
  const data = await ws.run(CM_SAVE_PROJECT, { id: it.id || null, input })
  if (data) { emit('saved', data.contentSaveProject.id); emit('close') }
}
</script>

<template>
  <ConfigsContentExpandableModal :title="it.id ? t('content.project_edit') : t('content.project_new')" max-width="max-w-2xl" @close="emit('close')">
      <form class="space-y-4" @submit.prevent="submit">
        <UFormField :label="t('content.f_name')" required><UInput v-model="f.name" class="w-full" maxlength="120" /></UFormField>
        <UFormField :label="t('content.f_description')"><UTextarea v-model="f.description" :rows="3" class="w-full" /></UFormField>
        <div class="grid gap-3 sm:grid-cols-2">
          <UFormField :label="t('content.f_topic')"><UInput v-model="f.topic" class="w-full" maxlength="200" /></UFormField>
          <UFormField :label="t('content.f_manager')"><UInput v-model="f.manager" class="w-full" maxlength="120" /></UFormField>
        </div>
        <UFormField :label="t('content.f_audience')"><UInput v-model="f.audience" class="w-full" maxlength="1000" /></UFormField>
        <div class="grid gap-3 sm:grid-cols-2">
          <UFormField :label="t('content.f_tone')"><UInput v-model="f.tone" class="w-full" maxlength="200" /></UFormField>
          <UFormField :label="t('content.f_keywords')"><UInput v-model="f.keywords" class="w-full" maxlength="300" /></UFormField>
        </div>
        <UFormField :label="t('content.f_competitors')"><UInput v-model="f.competitors" class="w-full" maxlength="300" /></UFormField>
        <UFormField :label="t('content.f_color')">
          <div class="flex flex-wrap gap-1.5">
            <button v-for="c in CM_COLORS" :key="c" type="button" class="size-6 rounded-full border-[3px]" :class="f.color === c ? 'border-highlighted' : 'border-transparent'" :style="{ background: c }" @click="f.color = c" />
          </div>
        </UFormField>
        <div class="flex gap-2">
          <UButton type="submit" :label="t('content.save')" />
          <UButton color="neutral" variant="outline" :label="t('content.cancel')" @click="emit('close')" />
        </div>
      </form>
  </ConfigsContentExpandableModal>
</template>
