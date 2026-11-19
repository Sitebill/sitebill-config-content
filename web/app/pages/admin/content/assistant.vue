<script setup lang="ts">
import type { CmProposal, CmReviewItem } from '../../../utils/content'
import { CM_IDEAS, CM_PLAN_PROPOSAL, CM_REVIEW, CM_SAVE_PUBLICATION } from '../../../graphql/content/documents'

// Ассистент: проверка плана, идеи и черновик плана на период. Правила считает бэкенд (PlanAgent),
// ИИ-генераторы подключатся к этим же точкам в следующих фазах.
definePageMeta({ layout: 'admin', middleware: ['auth'] })

const { t, locale } = useI18n()
const ws = useContentWorkspace()
const toast = useToast()

const lang = computed(() => String(locale.value).slice(0, 2))
const projectId = computed(() => Number(ws.filter.value || ws.projects.value[0]?.id || 0))
const reviews = ref<CmReviewItem[] | null>(null)
const proposals = ref<(CmProposal & { saved?: boolean })[]>([])
const proposalKind = ref<'ideas' | 'plan' | ''>('')
const busy = ref('')
const days = ref(14)

async function runReview() {
  busy.value = 'review'
  try {
    const d = await ws.query(CM_REVIEW, { project_id: projectId.value || null, today: cmToday() })
    reviews.value = d.contentReview
  } catch (e) { toast.add({ title: ws.errorText(e), color: 'error' }) } finally { busy.value = '' }
}

async function propose(kind: 'ideas' | 'plan') {
  if (!projectId.value) return
  busy.value = kind
  try {
    const d = kind === 'ideas'
      ? await ws.query(CM_IDEAS, { project_id: projectId.value, count: 6, locale: lang.value })
      : await ws.query(CM_PLAN_PROPOSAL, { project_id: projectId.value, days: Number(days.value) || 14, locale: lang.value, today: cmToday() })
    proposals.value = (kind === 'ideas' ? d.contentIdeas : d.contentPlanProposal).map((p: CmProposal) => ({ ...p }))
    proposalKind.value = kind
  } catch (e) { toast.add({ title: ws.errorText(e), color: 'error' }) } finally { busy.value = '' }
}

async function accept(p: CmProposal & { saved?: boolean }) {
  const res = await ws.run(CM_SAVE_PUBLICATION, {
    id: null,
    input: {
      project_id: projectId.value, title: p.title, kind: p.kind || 'post', rubric: p.rubric || null, body: p.body || null, hashtags: p.hashtags || null,
      planned_date: p.planned_date || null, planned_time: p.planned_time || null, platform_ids: p.platform_ids || [],
      status: proposalKind.value === 'ideas' ? 'idea' : 'draft',
    },
  })
  if (res) p.saved = true
}
async function acceptAll() {
  for (const p of proposals.value.filter(x => !x.saved)) await accept(p)
}
</script>

<template>
  <ConfigsContentFrame title-key="content.nav_assistant" no-all>
    <template #default>
      <div class="grid items-start gap-4 lg:grid-cols-2">
        <UCard>
          <h3 class="mb-1 font-semibold text-highlighted">{{ t('content.as_review_title') }}</h3>
          <p class="mb-3 text-sm text-muted">{{ t('content.as_review_hint') }}</p>
          <UButton :loading="busy === 'review'" icon="i-lucide-search-check" :label="t('content.as_review_run')" @click="runReview" />
          <div v-if="reviews" class="mt-3">
            <ConfigsContentReviewList :reviews="reviews" :limit="50" />
          </div>
        </UCard>

        <UCard v-if="ws.canWrite.value">
          <h3 class="mb-1 font-semibold text-highlighted">{{ t('content.as_make_title') }}</h3>
          <p class="mb-3 text-sm text-muted">{{ t('content.as_make_hint') }}</p>
          <div class="flex flex-wrap items-end gap-2">
            <UButton :loading="busy === 'ideas'" icon="i-lucide-lightbulb" color="neutral" variant="outline" :label="t('content.as_ideas')" @click="propose('ideas')" />
            <span class="flex items-end gap-2">
              <UFormField :label="t('content.as_days')"><input v-model="days" type="number" min="3" max="60" :class="CM_FIELD" class="!w-20"></UFormField>
              <UButton :loading="busy === 'plan'" icon="i-lucide-calendar-plus" :label="t('content.as_plan')" @click="propose('plan')" />
            </span>
          </div>
        </UCard>
      </div>

      <UCard v-if="proposals.length" class="mt-4">
        <div class="mb-3 flex items-center justify-between">
          <h3 class="font-semibold text-highlighted">{{ t(proposalKind === 'ideas' ? 'content.as_ideas' : 'content.as_plan') }} · {{ proposals.length }}</h3>
          <UButton size="xs" icon="i-lucide-check-check" :label="t('content.as_accept_all')" @click="acceptAll" />
        </div>
        <div class="space-y-2">
          <div v-for="(p, i) in proposals" :key="i" class="flex items-start gap-3 rounded-xl border border-default px-3 py-2.5" :class="p.saved ? 'opacity-50' : ''">
            <div class="min-w-0 flex-1">
              <b class="block font-semibold text-highlighted">{{ p.title }}</b>
              <span class="text-xs text-muted">{{ [t('content.kind_' + (p.kind || 'post')), p.rubric, p.planned_date ? cmDayTitle(locale, p.planned_date) + (p.planned_time ? ' ' + p.planned_time : '') : ''].filter(Boolean).join(' · ') }}</span>
              <p v-if="p.body" class="mt-1 whitespace-pre-wrap text-sm text-muted">{{ p.body.length > 220 ? p.body.slice(0, 220) + '…' : p.body }}</p>
            </div>
            <UButton v-if="!p.saved" size="xs" icon="i-lucide-plus" :label="t('content.as_accept')" @click="accept(p)" />
            <UBadge v-else variant="subtle" color="success" :label="t('content.as_added')" />
          </div>
        </div>
      </UCard>
    </template>
  </ConfigsContentFrame>
</template>
