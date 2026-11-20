<script setup lang="ts">
import type { CmMaterial } from '../../../utils/content'
import { CM_DELETE_MATERIAL, CM_SAVE_MATERIAL } from '../../../graphql/content/documents'

// Библиотека: книги, дневник, заметки, идеи, хештеги и кейсы. Целевые аудитории вынесены в свой раздел (audiences.vue).
definePageMeta({ layout: 'admin', middleware: ['auth'] })

const { t, locale } = useI18n()
const ws = useContentWorkspace()
const toast = useToast()
const { confirmDialog } = useConfirmDialog()

const KINDS = CM_MATERIAL_KINDS.filter(k => k !== 'audience')
const filter = useState('cm-lib-filter', () => ({ q: '', kind: 'book', sub: '' }))
if (!KINDS.includes(filter.value.kind as any)) filter.value = { ...filter.value, kind: 'book', sub: '' }
const editing = ref<{ kind: string; item: CmMaterial | null } | null>(null)

/** Подфильтр вкладки: у книг и заметок это статус, у аудитории и хештегов — сфера. */
const SUB: Record<string, { field: 'state' | 'sphere'; values: readonly string[]; prefix: string }> = {
  book: { field: 'state', values: CM_BOOK_STATES, prefix: 'bstate_' },
  note: { field: 'state', values: CM_NOTE_STATES, prefix: 'nstate_' },
  audience: { field: 'sphere', values: ['work', 'art'], prefix: 'sphere_' },
  hashtags: { field: 'sphere', values: ['work', 'art'], prefix: 'sphere_' },
}
const sub = computed(() => SUB[filter.value.kind])

function setKind(k: string) { filter.value = { ...filter.value, kind: k, sub: '' } }

const list = computed(() => {
  const f = filter.value
  const q = f.q.trim().toLowerCase()
  return ws.materials.value
    .filter(m => m.kind === f.kind && (!f.sub || !sub.value || m[sub.value.field] === f.sub)
      && (!q || `${m.title} ${m.body || ''} ${m.topic || ''}`.toLowerCase().includes(q)))
    .sort((a, b) => (b.entry_date || '').localeCompare(a.entry_date || '') || Number(b.id) - Number(a.id))
})

async function copy(m: CmMaterial) {
  try { await navigator.clipboard.writeText(m.body || m.title); toast.add({ title: t('content.copied'), color: 'success' }) } catch { toast.add({ title: t('content.error'), color: 'error' }) }
}
async function remove(m: CmMaterial) {
  if (await confirmDialog({ title: t('content.confirm_delete'), color: 'error' })) await ws.run(CM_DELETE_MATERIAL, { id: m.id }, 'content.deleted')
}
const toggleNote = (m: CmMaterial) => ws.run(CM_SAVE_MATERIAL, {
  id: m.id, input: { kind: m.kind, title: m.title, body: m.body, topic: m.topic, entry_date: m.entry_date, state: m.state === 'done' ? 'todo' : 'done', sphere: m.sphere },
}, null)

const bookColor = (s?: string | null) => s === 'best' ? '#f59e0b' : s === 'read' ? '#10b981' : '#64748b'
</script>

<template>
  <ConfigsContentFrame title-key="content.nav_library" no-select always>
    <template #actions>
      <UButton v-if="ws.canWrite.value" icon="i-lucide-plus" :label="t('content.new_' + filter.kind)" @click="editing = { kind: filter.kind, item: null }" />
    </template>

    <template #default="{ openPub }">
      <div class="mb-3 flex flex-wrap gap-1 rounded-xl bg-elevated p-0.5">
        <button v-for="k in KINDS" :key="k" type="button" class="rounded-lg px-3 py-1 text-sm font-semibold" :class="filter.kind === k ? 'bg-default shadow-sm' : 'text-muted'" @click="setKind(k)">
          {{ CM_MATERIAL_ICON[k] }} {{ t('content.mkind_' + k) }}
        </button>
      </div>
      <div class="mb-3 flex flex-wrap items-center gap-2">
        <input v-model="filter.q" :class="CM_FIELD" class="!w-52" :placeholder="t('content.search')">
        <select v-if="sub" v-model="filter.sub" :class="CM_FIELD" class="!w-auto">
          <option value="">{{ t('content.all_kinds') }}</option>
          <option v-for="v in sub.values" :key="v" :value="v">{{ t('content.' + sub.prefix + v) }}</option>
        </select>
      </div>

      <UCard v-if="!list.length"><p class="py-6 text-center text-muted">{{ t('content.library_empty') }}</p></UCard>
      <div v-else class="grid gap-3.5 sm:grid-cols-2 lg:grid-cols-3">
        <UCard v-for="m in list" :key="m.id">
          <div class="flex h-full flex-col gap-2">
            <div class="flex items-center gap-2.5">
              <input v-if="m.kind === 'note'" type="checkbox" :checked="m.state === 'done'" :disabled="!ws.canWrite.value" @change="toggleNote(m)">
              <b class="flex-1 text-[15px] text-highlighted" :class="m.kind === 'note' && m.state === 'done' ? 'line-through opacity-60' : ''">{{ m.title }}</b>
              <UBadge v-if="m.kind === 'book' && m.state" variant="subtle" color="neutral" :style="{ color: bookColor(m.state) }" :label="t('content.bstate_' + m.state)" />
              <UBadge v-if="['audience', 'hashtags'].includes(m.kind) && m.sphere" variant="subtle" color="neutral" :style="{ color: CM_SPHERE_COLOR[m.sphere] }" :label="t('content.sphere_' + m.sphere)" />
            </div>
            <div class="text-xs text-muted">{{ [m.entry_date ? cmDayTitle(locale, m.entry_date) : '', m.topic ? (m.kind === 'book' ? '✍ ' : '# ') + m.topic : ''].filter(Boolean).join(' · ') }}</div>
            <p v-if="m.body" class="whitespace-pre-wrap text-sm text-muted">{{ cmPlain(m.body).length > 260 ? cmPlain(m.body).slice(0, 260) + '…' : cmPlain(m.body) }}</p>
            <div class="mt-auto flex flex-wrap gap-2 pt-1">
              <UButton v-if="['idea', 'case'].includes(m.kind) && ws.canWrite.value" size="xs" color="neutral" variant="outline" :label="'→ ' + t('content.make_pub')" @click="openPub(null, { title: m.title, body: m.body || '' })" />
              <UButton v-if="m.kind === 'hashtags'" size="xs" color="neutral" variant="outline" :label="t('content.copy')" @click="copy(m)" />
              <UButton v-if="ws.canWrite.value" size="xs" color="neutral" variant="outline" :label="t('content.edit')" @click="editing = { kind: m.kind, item: m }" />
              <UButton v-if="ws.canWrite.value" size="xs" color="error" variant="outline" :label="t('content.delete')" @click="remove(m)" />
            </div>
          </div>
        </UCard>
      </div>

      <ConfigsContentMaterialModal v-if="editing" :key="editing.item?.id || 'new'" :item="editing.item" :kind="editing.kind" @close="editing = null" />
    </template>
  </ConfigsContentFrame>
</template>
