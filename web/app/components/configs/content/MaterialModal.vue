<script setup lang="ts">
import type { CmMaterial } from '../../../utils/content'
import { CM_SAVE_MATERIAL } from '../../../graphql/content/documents'

// Запись библиотеки: книга, дневник, заметка, аудитория, идея, хештеги или кейс. Набор полей зависит от вида.
const props = defineProps<{ item: Partial<CmMaterial> | null; kind: string }>()
const emit = defineEmits<{ close: [] }>()

const { t, locale } = useI18n()
const ws = useContentWorkspace()

const it = props.item || {}
const kind = it.kind || props.kind || 'idea'
const f = reactive({
  title: it.title || '', body: it.body || '', topic: it.topic || '', entry_date: it.entry_date || cmToday(),
  state: it.state || (kind === 'book' ? 'planned' : kind === 'note' ? 'todo' : ''),
  sphere: it.sphere || (['audience', 'hashtags'].includes(kind) ? 'work' : ''),
})

async function submit() {
  // У записи дневника заголовок необязателен: подставляем дату.
  const title = f.title.trim() || (kind === 'diary' ? cmDayTitle(locale.value, f.entry_date) : '')
  if (!title) return
  const data = await ws.run(CM_SAVE_MATERIAL, {
    id: it.id || null,
    input: { kind, title, body: f.body || null, topic: f.topic.trim() || null, entry_date: f.entry_date || null, state: f.state || null, sphere: f.sphere || null },
  })
  if (data) emit('close')
}
</script>

<template>
  <ConfigsContentExpandableModal :title="CM_MATERIAL_ICON[kind] + ' ' + t(it.id ? 'content.ed_' + kind : 'content.new_' + kind)" @close="emit('close')">
      <form class="space-y-4" @submit.prevent="submit">
        <UFormField :label="t('content.lf_title_' + kind)" :required="kind !== 'diary'"><UInput v-model="f.title" class="w-full" maxlength="200" /></UFormField>
        <div class="grid gap-3 sm:grid-cols-2">
          <UFormField :label="t('content.lf_date_' + kind)"><input v-model="f.entry_date" type="date" :class="CM_FIELD"></UFormField>
          <UFormField v-if="kind === 'book'" :label="t('content.f_state')">
            <select v-model="f.state" :class="CM_FIELD"><option v-for="s in CM_BOOK_STATES" :key="s" :value="s">{{ t('content.bstate_' + s) }}</option></select>
          </UFormField>
          <UFormField v-else-if="kind === 'note'" :label="t('content.f_state')">
            <select v-model="f.state" :class="CM_FIELD"><option v-for="s in CM_NOTE_STATES" :key="s" :value="s">{{ t('content.nstate_' + s) }}</option></select>
          </UFormField>
          <UFormField v-else-if="kind === 'audience' || kind === 'hashtags'" :label="t('content.f_audience_kind')">
            <select v-model="f.sphere" :class="CM_FIELD"><option v-for="s in ['work', 'art']" :key="s" :value="s">{{ t('content.sphere_' + s) }}</option></select>
          </UFormField>
        </div>
        <UFormField v-if="kind === 'book'" :label="t('content.f_author')"><UInput v-model="f.topic" class="w-full" maxlength="160" /></UFormField>
        <UFormField v-if="kind === 'hashtags'" :label="t('content.f_hashtag_topic')"><UInput v-model="f.topic" class="w-full" maxlength="160" /></UFormField>
        <UFormField :label="t('content.lf_body_' + kind)" :required="kind === 'diary'"><UTextarea v-model="f.body" :rows="7" class="w-full" /></UFormField>
        <div class="flex gap-2">
          <UButton type="submit" :label="t('content.save')" />
          <UButton color="neutral" variant="outline" :label="t('content.cancel')" @click="emit('close')" />
        </div>
      </form>
  </ConfigsContentExpandableModal>
</template>
