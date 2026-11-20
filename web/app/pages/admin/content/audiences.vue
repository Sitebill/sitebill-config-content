<script setup lang="ts">
import type { CmMaterial } from '../../../utils/content'
import { CM_DELETE_MATERIAL } from '../../../graphql/content/documents'

// Целевые аудитории: отдельный раздел (раньше — вкладка библиотеки). Записи хранятся как материалы вида `audience`,
// из них аудиторию выбирают в карточках проектов и площадок.
definePageMeta({ layout: 'admin', middleware: ['auth'] })

const { t, locale } = useI18n()
const ws = useContentWorkspace()
const { confirmDialog } = useConfirmDialog()

const filter = useState('cm-aud-filter', () => ({ q: '', sphere: '' }))
const editing = ref<{ item: CmMaterial | null } | null>(null)

const list = computed(() => {
  const q = filter.value.q.trim().toLowerCase()
  return ws.materials.value
    .filter(m => m.kind === 'audience' && (!filter.value.sphere || m.sphere === filter.value.sphere)
      && (!q || `${m.title} ${m.body || ''}`.toLowerCase().includes(q)))
    .sort((a, b) => (b.entry_date || '').localeCompare(a.entry_date || '') || Number(b.id) - Number(a.id))
})

async function remove(m: CmMaterial) {
  if (await confirmDialog({ title: t('content.confirm_delete'), color: 'error' })) await ws.run(CM_DELETE_MATERIAL, { id: m.id }, 'content.deleted')
}
</script>

<template>
  <ConfigsContentFrame title-key="content.nav_audiences" no-select always>
    <template #actions>
      <UButton v-if="ws.canWrite.value" icon="i-lucide-plus" :label="t('content.new_audience')" @click="editing = { item: null }" />
    </template>

    <template #default>
      <div class="mb-3 flex flex-wrap items-center gap-2">
        <input v-model="filter.q" :class="CM_FIELD" class="!w-52" :placeholder="t('content.search')">
        <select v-model="filter.sphere" :class="CM_FIELD" class="!w-auto">
          <option value="">{{ t('content.all_kinds') }}</option>
          <option v-for="v in ['work', 'art']" :key="v" :value="v">{{ t('content.sphere_' + v) }}</option>
        </select>
      </div>

      <UCard v-if="!list.length"><p class="py-6 text-center text-muted">{{ t('content.audiences_empty') }}</p></UCard>
      <div v-else class="grid gap-3.5 sm:grid-cols-2 lg:grid-cols-3">
        <UCard v-for="m in list" :key="m.id">
          <div class="flex h-full flex-col gap-2">
            <div class="flex items-center gap-2.5">
              <b class="flex-1 text-[15px] text-highlighted">{{ m.title }}</b>
              <UBadge v-if="m.sphere" variant="subtle" color="neutral" :style="{ color: CM_SPHERE_COLOR[m.sphere] }" :label="t('content.sphere_' + m.sphere)" />
            </div>
            <div v-if="m.entry_date" class="text-xs text-muted">{{ cmDayTitle(locale, m.entry_date) }}</div>
            <p v-if="m.body" class="whitespace-pre-wrap text-sm text-muted">{{ cmPlain(m.body).length > 260 ? cmPlain(m.body).slice(0, 260) + '…' : cmPlain(m.body) }}</p>
            <div class="mt-auto flex flex-wrap gap-2 pt-1">
              <UButton v-if="ws.canWrite.value" size="xs" color="neutral" variant="outline" :label="t('content.edit')" @click="editing = { item: m }" />
              <UButton v-if="ws.canWrite.value" size="xs" color="error" variant="outline" :label="t('content.delete')" @click="remove(m)" />
            </div>
          </div>
        </UCard>
      </div>

      <ConfigsContentMaterialModal v-if="editing" :key="editing.item?.id || 'new'" :item="editing.item" kind="audience" @close="editing = null" />
    </template>
  </ConfigsContentFrame>
</template>
