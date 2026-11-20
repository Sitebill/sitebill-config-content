<script setup lang="ts">
import type { CmEvent } from '../../../utils/content'
import { CM_SAVE_EVENT, CM_DELETE_EVENT } from '../../../graphql/content/documents'

// «Важное» в календаре: день рождения, праздник, приём у врача или другое. Набор полей зависит от вида.
const props = defineProps<{ item: Partial<CmEvent> }>()
const emit = defineEmits<{ close: [] }>()

const { t } = useI18n()
const ws = useContentWorkspace()
const { confirmDialog } = useConfirmDialog()

const it = props.item
const f = reactive({ kind: it.kind || 'birthday', title: it.title || '', event_date: it.event_date || cmToday(), event_time: it.event_time || '', comment: it.comment || '' })
const withTime = computed(() => ['doctor', 'other'].includes(f.kind))

async function submit() {
  if (!f.title.trim()) return
  const data = await ws.run(CM_SAVE_EVENT, {
    id: it.id || null,
    input: { kind: f.kind, title: f.title.trim(), event_date: f.event_date, event_time: withTime.value && f.event_time ? f.event_time : null, comment: f.comment.trim() || null },
  })
  if (data) emit('close')
}

async function remove() {
  if (await confirmDialog({ title: t('content.confirm_delete'), color: 'error' }) && await ws.run(CM_DELETE_EVENT, { id: it.id }, 'content.deleted')) emit('close')
}
</script>

<template>
  <ConfigsContentExpandableModal :title="it.id ? t('content.event_edit') : t('content.event_new')" @close="emit('close')">
      <form class="space-y-4" @submit.prevent="submit">
        <div class="flex flex-wrap gap-1 rounded-xl bg-elevated p-0.5">
          <button v-for="k in CM_EVENT_KINDS" :key="k" type="button" class="rounded-lg px-3 py-1 text-sm font-semibold" :class="f.kind === k ? 'bg-default shadow-sm' : 'text-muted'" @click="f.kind = k">
            {{ CM_EVENT_ICON[k] }} {{ t('content.ekind_' + k) }}
          </button>
        </div>
        <UFormField :label="t('content.ef_title_' + f.kind)" required><UInput v-model="f.title" class="w-full" maxlength="200" /></UFormField>
        <div class="grid gap-3" :class="withTime ? 'sm:grid-cols-2' : ''">
          <UFormField :label="t('content.ef_date_' + f.kind)" required><input v-model="f.event_date" type="date" required :class="CM_FIELD"></UFormField>
          <UFormField v-if="withTime" :label="t('content.f_time')"><input v-model="f.event_time" type="time" :class="CM_FIELD"></UFormField>
        </div>
        <UFormField :label="t('content.f_comment')"><ConfigsContentRichText v-model="f.comment" compact /></UFormField>
        <div class="flex items-center gap-2">
          <UButton type="submit" :label="t('content.save')" />
          <UButton color="neutral" variant="outline" :label="t('content.cancel')" @click="emit('close')" />
          <span class="flex-1" />
          <UButton v-if="it.id" color="error" variant="ghost" :label="t('content.delete')" @click="remove" />
        </div>
      </form>
  </ConfigsContentExpandableModal>
</template>
