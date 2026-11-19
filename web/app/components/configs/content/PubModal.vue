<script setup lang="ts">
import type { CmPublication, CmPlatform, CmProposal } from '../../../utils/content'
import {
  CM_SAVE_PUBLICATION, CM_DELETE_PUBLICATION, CM_DUPLICATE_PUBLICATION, CM_SET_PLACEMENT, CM_ADAPTATION, CM_AI_GENERATE,
} from '../../../graphql/content/documents'

/**
 * Карточка публикации: весь путь материала в одном окне — текст, план, площадки со своим статусом у каждой,
 * адаптации под другие площадки и связанные версии.
 */
const props = defineProps<{ pub: CmPublication | null; defaults?: Record<string, any> }>()
const emit = defineEmits<{ close: []; open: [pub: CmPublication] }>()

const { t, locale } = useI18n()
const ws = useContentWorkspace()
const { confirmDialog } = useConfirmDialog()

const cur = ref<CmPublication | null>(props.pub)
const src: Partial<CmPublication> = props.pub || {}
const d = props.defaults || {}

const f = reactive({
  project_id: String(src.project_id || d.project_id || ws.filter.value || ws.projects.value[0]?.id || ''),
  title: src.title || d.title || '',
  kind: src.kind || d.kind || 'post',
  rubric: src.rubric || d.rubric || '',
  sphere: src.sphere || d.sphere || 'work',
  status: src.status || d.status || 'idea',
  priority: src.priority || 'normal',
  planned_date: src.planned_date || d.planned_date || '',
  planned_time: src.planned_time || d.planned_time || '',
  body: src.body || d.body || '',
  hashtags: src.hashtags || d.hashtags || '',
  link: src.link || '',
  assignee: src.assignee || '',
  comment: src.comment || '',
  parent_id: (src.parent_id || d.parent_id || null) as number | null,
  platform_ids: (src.placements || []).map(p => Number(p.platform_id)).concat(src.id ? [] : (d.platform_ids || [])) as number[],
})

const busy = ref(false)
const proposal = ref<CmProposal | null>(null)
const proposalFor = ref<CmPlatform | null>(null)

const platforms = computed(() => ws.platforms.value.filter(p => String(p.project_id) === String(f.project_id)))
const rubrics = computed(() => [...new Set(ws.pubs.value.filter(p => String(p.project_id) === String(f.project_id)).map(p => p.rubric).filter(Boolean))] as string[])
const parent = computed(() => f.parent_id ? ws.pubs.value.find(p => Number(p.id) === Number(f.parent_id)) ?? null : null)
const versions = computed(() => cur.value ? ws.pubs.value.filter(p => Number(p.parent_id) === Number(cur.value!.id)) : [])
const statusOptions = computed(() => [...new Set([...ws.allowedStatuses.value, f.status])])
// Автор только читает, если у него нет права записи (зритель, согласующий).
const readonly = computed(() => !ws.canWrite.value)

function input() {
  return {
    project_id: Number(f.project_id), parent_id: f.parent_id ? Number(f.parent_id) : null, title: f.title.trim(), kind: f.kind,
    rubric: f.rubric || null, sphere: f.sphere, status: f.status, priority: f.priority, planned_date: f.planned_date || null,
    planned_time: f.planned_time || null, body: f.body || null, hashtags: f.hashtags || null, link: f.link || null,
    assignee: f.assignee || null, comment: f.comment || null, platform_ids: f.platform_ids.map(Number),
  }
}

function adopt(p: CmPublication) {
  cur.value = p
  f.status = p.status
  f.platform_ids = p.placements.map(x => Number(x.platform_id))
}

async function save() {
  if (!f.title.trim() || !f.project_id || readonly.value) return
  busy.value = true
  const data = await ws.run(CM_SAVE_PUBLICATION, { id: cur.value?.id || null, input: input() })
  busy.value = false
  if (data) adopt(data.contentSavePublication)
}

async function remove() {
  if (!cur.value || !await confirmDialog({ title: t('content.confirm_delete'), color: 'error' })) return
  if (await ws.run(CM_DELETE_PUBLICATION, { id: cur.value.id }, 'content.deleted')) emit('close')
}

async function duplicate() {
  const data = await ws.run(CM_DUPLICATE_PUBLICATION, { id: cur.value!.id }, 'content.version_created')
  if (data) emit('open', data.contentDuplicatePublication)
}

async function setPlacement(platformId: number, patch: { status?: string; url?: string | null }) {
  const pl = cur.value!.placements.find(p => Number(p.platform_id) === platformId)!
  const data = await ws.run(CM_SET_PLACEMENT, {
    publication_id: cur.value!.id, platform_id: platformId, status: patch.status ?? pl.status, url: patch.url !== undefined ? patch.url : (pl.url ?? null),
  })
  if (data) adopt(data.contentSetPlacement)
}

function togglePlatform(id: number) {
  f.platform_ids = f.platform_ids.includes(id) ? f.platform_ids.filter(x => x !== id) : [...f.platform_ids, id]
}

async function adapt(pf: CmPlatform) {
  proposalFor.value = pf
  proposal.value = null
  try {
    const data = await ws.query(CM_ADAPTATION, { publication_id: cur.value!.id, platform_id: Number(pf.id), locale: String(locale.value).slice(0, 2) })
    proposal.value = data.contentAdaptation
  } catch (e) {
    useToast().add({ title: ws.errorText(e), color: 'error' })
    proposalFor.value = null
  }
}

/** Адаптация через ИИ: тот же формат предложения, что у правил, — принимается в связанную версию. */
async function adaptAi(pf: CmPlatform) {
  proposalFor.value = pf
  proposal.value = null
  try {
    const res = await ws.mutate(CM_AI_GENERATE, {
      input: {
        action: 'adapt', project_id: Number(f.project_id), platform_id: Number(pf.id), title: f.title, kind: f.kind, rubric: f.rubric || null,
        text: f.body, locale: String(locale.value).slice(0, 2),
      },
    })
    proposal.value = {
      title: f.title, kind: f.kind, rubric: f.rubric || null, body: res.contentAiGenerate.text, hashtags: f.hashtags || null,
      planned_date: f.planned_date || null, planned_time: f.planned_time || null, platform_ids: [Number(pf.id)],
    }
  } catch (e) {
    useToast().add({ title: ws.errorText(e), color: 'error' })
    proposalFor.value = null
  }
}

function applyAi(patch: { title?: string; body?: string; hashtags?: string }) {
  Object.assign(f, patch)
}

async function acceptProposal() {
  const p = proposal.value!
  const data = await ws.run(CM_SAVE_PUBLICATION, {
    id: null,
    input: {
      project_id: Number(f.project_id), parent_id: Number(cur.value!.id), title: p.title, kind: p.kind, rubric: p.rubric, status: 'draft',
      priority: f.priority, planned_date: p.planned_date, planned_time: p.planned_time, body: p.body, hashtags: p.hashtags,
      link: f.link || null, assignee: f.assignee || null, platform_ids: p.platform_ids,
    },
  }, 'content.version_created')
  if (data) { proposal.value = null; proposalFor.value = null }
}
</script>

<template>
  <UModal :open="true" :title="cur ? t('content.pub_edit') : t('content.pub_new')" :ui="{ content: 'max-w-3xl' }" @update:open="(v: boolean) => !v && emit('close')">
    <template #body>
      <form class="space-y-4" @submit.prevent="save">
        <p v-if="parent" class="text-sm text-muted">
          ↳ {{ t('content.version_of') }}
          <a href="#" class="text-primary hover:underline" @click.prevent="emit('open', parent!)">{{ parent.title }}</a>
        </p>

        <UFormField :label="t('content.f_title')" required>
          <UInput v-model="f.title" class="w-full" maxlength="200" :disabled="readonly" />
        </UFormField>

        <div class="grid gap-3 sm:grid-cols-3">
          <UFormField :label="t('content.f_project')">
            <select v-model="f.project_id" :class="CM_FIELD" :disabled="!!cur || readonly">
              <option v-for="p in ws.projects.value" :key="p.id" :value="String(p.id)">{{ p.name }}</option>
            </select>
          </UFormField>
          <UFormField :label="t('content.f_sphere')">
            <select v-model="f.sphere" :class="CM_FIELD" :disabled="readonly">
              <option v-for="s in CM_SPHERES" :key="s" :value="s">{{ t('content.sphere_' + s) }}</option>
            </select>
          </UFormField>
          <UFormField :label="t('content.f_kind')">
            <select v-model="f.kind" :class="CM_FIELD" :disabled="readonly">
              <option v-for="k in CM_KINDS" :key="k" :value="k">{{ t('content.kind_' + k) }}</option>
            </select>
          </UFormField>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
          <UFormField :label="t('content.f_date')"><input v-model="f.planned_date" type="date" :class="CM_FIELD" :disabled="readonly"></UFormField>
          <UFormField :label="t('content.f_time')"><input v-model="f.planned_time" type="time" :class="CM_FIELD" :disabled="readonly"></UFormField>
          <UFormField :label="t('content.f_status')">
            <select v-model="f.status" :class="CM_FIELD" :disabled="readonly">
              <option v-for="s in statusOptions" :key="s" :value="s">{{ t('content.status_' + s) }}</option>
            </select>
          </UFormField>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
          <UFormField :label="t('content.f_rubric')">
            <input v-model="f.rubric" list="cm-rubrics" maxlength="80" :class="CM_FIELD" :disabled="readonly">
            <datalist id="cm-rubrics"><option v-for="r in rubrics" :key="r" :value="r" /></datalist>
          </UFormField>
          <UFormField :label="t('content.f_priority')">
            <select v-model="f.priority" :class="CM_FIELD" :disabled="readonly">
              <option v-for="p in CM_PRIORITIES" :key="p" :value="p">{{ t('content.prio_' + p) }}</option>
            </select>
          </UFormField>
          <UFormField :label="t('content.f_assignee')"><input v-model="f.assignee" maxlength="120" :class="CM_FIELD" :disabled="readonly"></UFormField>
        </div>

        <ConfigsContentAiPanel
          v-if="!readonly" :project-id="Number(f.project_id)" :title="f.title" :kind="f.kind" :rubric="f.rubric" :body="f.body"
          :platforms="platforms" @apply="applyAi"
        />

        <UFormField :label="`${t('content.f_body')} · ${f.body.length}`">
          <UTextarea v-model="f.body" :rows="8" class="w-full" :disabled="readonly" />
        </UFormField>

        <div class="grid gap-3 sm:grid-cols-2">
          <UFormField :label="t('content.f_hashtags')"><input v-model="f.hashtags" maxlength="300" placeholder="#tag #tag" :class="CM_FIELD" :disabled="readonly"></UFormField>
          <UFormField :label="t('content.f_link')"><input v-model="f.link" maxlength="500" placeholder="https://…?utm_source=…" :class="CM_FIELD" :disabled="readonly"></UFormField>
        </div>
        <UFormField :label="t('content.f_comment')"><input v-model="f.comment" maxlength="2000" :class="CM_FIELD" :disabled="readonly"></UFormField>

        <UFormField :label="t('content.f_channels')">
          <div v-if="platforms.length" class="flex flex-wrap gap-1.5">
            <label
              v-for="pf in platforms" :key="pf.id"
              class="inline-flex cursor-pointer select-none items-center gap-1.5 rounded-lg border px-2.5 py-1 text-sm"
              :class="f.platform_ids.includes(Number(pf.id)) ? 'border-primary bg-primary/10' : 'border-default'"
            >
              <input type="checkbox" class="hidden" :checked="f.platform_ids.includes(Number(pf.id))" :disabled="readonly" @change="togglePlatform(Number(pf.id))">
              {{ CM_PLATFORM_ICON[pf.kind] || '🔗' }} {{ pf.name }}
            </label>
          </div>
          <span v-else class="text-sm text-muted">{{ t('content.no_channels') }}</span>
        </UFormField>

        <div class="flex flex-wrap items-center gap-2">
          <UButton type="submit" :loading="busy" :disabled="readonly" :label="t('content.save')" />
          <UButton v-if="cur && ws.canWrite.value" color="neutral" variant="outline" icon="i-lucide-copy" :label="t('content.new_version')" @click="duplicate" />
          <span class="flex-1" />
          <UButton v-if="cur && ws.canManage.value" color="error" variant="ghost" :label="t('content.delete')" @click="remove" />
        </div>
      </form>

      <!-- Размещение: у каждой площадки свой статус, дата и ссылка -->
      <section v-if="cur && cur.placements.length" class="mt-6">
        <div class="mb-1 flex flex-wrap items-center gap-2">
          <b class="text-highlighted">{{ t('content.placements') }}</b>
          <ConfigsContentStatusChip :status="cur.status" />
          <span class="text-xs text-muted">{{ t('content.state_' + cur.placement_state) }}</span>
        </div>
        <div v-for="pl in cur.placements" :key="pl.id" class="grid items-center gap-2 border-t border-default py-2 sm:grid-cols-[minmax(120px,1fr)_150px_minmax(0,1.4fr)]">
          <span>{{ CM_PLATFORM_ICON[ws.platformById.value.get(Number(pl.platform_id))?.kind || ''] || '🔗' }} <b>{{ ws.platformById.value.get(Number(pl.platform_id))?.name || '?' }}</b></span>
          <select :class="CM_FIELD" :value="pl.status" :disabled="!ws.canWrite.value" @change="setPlacement(Number(pl.platform_id), { status: ($event.target as HTMLSelectElement).value })">
            <option v-for="s in CM_PLACEMENT_STATUSES" :key="s" :value="s">{{ t('content.pstatus_' + s) }}</option>
          </select>
          <input
            v-if="pl.status === 'published'" type="url" :class="CM_FIELD" :placeholder="t('content.f_published_url')" :value="pl.url || ''" :disabled="!ws.canWrite.value"
            @change="setPlacement(Number(pl.platform_id), { url: ($event.target as HTMLInputElement).value || null })"
          >
          <span v-else class="text-xs text-muted">{{ ws.platformById.value.get(Number(pl.platform_id))?.api_status === 'connected' ? t('content.api_connected') : t('content.api_manual') }}</span>
        </div>
      </section>

      <!-- Адаптация под площадку: отдельная версия, связанная с этой -->
      <section v-if="cur && platforms.length && ws.canWrite.value" class="mt-6">
        <div class="mb-2 flex flex-wrap items-center gap-2">
          <b class="text-highlighted">{{ t('content.adapt_title') }}</b>
          <span class="text-xs text-muted">{{ t('content.adapt_hint') }}</span>
        </div>
        <div class="flex flex-wrap gap-2">
          <UButton v-for="pf in platforms" :key="pf.id" size="xs" color="neutral" variant="outline" icon="i-lucide-sparkles" :label="pf.name" @click="adapt(pf)" />
        </div>
        <div v-if="f.body.trim()" class="mt-2 flex flex-wrap items-center gap-2">
          <span class="text-xs text-muted">✨ {{ t('content.ai_adapt_label') }}</span>
          <UButton v-for="pf in platforms" :key="pf.id" size="xs" variant="soft" icon="i-lucide-wand-sparkles" :label="pf.name" @click="adaptAi(pf)" />
        </div>
        <p v-if="proposalFor && !proposal" class="mt-2 text-sm text-muted">{{ t('content.thinking') }}</p>
        <div v-if="proposal" class="mt-3 space-y-2 rounded-xl border border-dashed border-default bg-elevated/40 p-3">
          <b class="block text-highlighted">{{ proposalFor?.name }} — {{ proposal.title }}</b>
          <pre class="max-h-36 overflow-auto whitespace-pre-wrap font-sans text-sm text-muted">{{ proposal.body }}</pre>
          <div class="flex gap-2">
            <UButton size="xs" :label="t('content.adapt_save')" @click="acceptProposal" />
            <UButton size="xs" color="neutral" variant="outline" :label="t('content.cancel')" @click="proposal = null; proposalFor = null" />
          </div>
        </div>
      </section>

      <section v-if="versions.length" class="mt-6">
        <b class="text-highlighted">{{ t('content.versions') }}</b>
        <ConfigsContentPubRow v-for="v in versions" :key="v.id" :pub="v" />
      </section>
    </template>
  </UModal>
</template>
