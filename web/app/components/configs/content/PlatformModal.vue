<script setup lang="ts">
import type { CmPlatform } from '../../../utils/content'
import { CM_SAVE_PLATFORM } from '../../../graphql/content/documents'

// Площадка проекта: тип, адрес, формат, предел длины текста и желаемая частота публикаций.
const props = defineProps<{ item: Partial<CmPlatform>; projectId: number }>()
const emit = defineEmits<{ close: [] }>()

const { t } = useI18n()
const ws = useContentWorkspace()
const it = props.item

const f = reactive({
  name: it.name || '', kind: it.kind || 'telegram', url: it.url || '', audience: it.audience || '', formats: it.formats || '',
  max_length: (it.max_length ?? '') as number | '', per_week: (it.per_week ?? '') as number | '', api_status: it.api_status || 'manual',
})

async function submit() {
  const input = {
    project_id: props.projectId, name: f.name.trim(), kind: f.kind, url: f.url || null, audience: f.audience || null, formats: f.formats || null,
    max_length: f.max_length === '' ? null : Number(f.max_length), per_week: f.per_week === '' ? null : Number(f.per_week), api_status: f.api_status,
  }
  if (await ws.run(CM_SAVE_PLATFORM, { id: it.id || null, input })) emit('close')
}
</script>

<template>
  <ConfigsContentExpandableModal :title="it.id ? t('content.channel_edit') : t('content.channel_new')" @close="emit('close')">
      <form class="space-y-4" @submit.prevent="submit">
        <UFormField :label="t('content.f_name')" required><UInput v-model="f.name" class="w-full" maxlength="120" /></UFormField>
        <div class="grid gap-3 sm:grid-cols-2">
          <UFormField :label="t('content.f_channel_kind')">
            <select v-model="f.kind" :class="CM_FIELD"><option v-for="k in CM_PLATFORM_KINDS" :key="k" :value="k">{{ CM_PLATFORM_ICON[k] }} {{ t('content.pkind_' + k) }}</option></select>
          </UFormField>
          <UFormField :label="t('content.f_api')">
            <select v-model="f.api_status" :class="CM_FIELD"><option v-for="k in ['manual', 'connected']" :key="k" :value="k">{{ t('content.api_' + k) }}</option></select>
          </UFormField>
        </div>
        <UFormField :label="t('content.f_url')"><input v-model="f.url" type="url" maxlength="300" placeholder="https://" :class="CM_FIELD"></UFormField>
        <UFormField :label="t('content.f_audience')"><ConfigsContentAudiencePicker v-model="f.audience" :max="300" /></UFormField>
        <UFormField :label="t('content.f_formats')"><UInput v-model="f.formats" class="w-full" maxlength="200" /></UFormField>
        <div class="grid gap-3 sm:grid-cols-2">
          <UFormField :label="t('content.f_max_length')"><input v-model="f.max_length" type="number" min="1" :class="CM_FIELD"></UFormField>
          <UFormField :label="t('content.f_per_week')"><input v-model="f.per_week" type="number" min="0" max="100" :class="CM_FIELD"></UFormField>
        </div>
        <div class="flex gap-2">
          <UButton type="submit" :label="t('content.save')" />
          <UButton color="neutral" variant="outline" :label="t('content.cancel')" @click="emit('close')" />
        </div>
      </form>
  </ConfigsContentExpandableModal>
</template>
