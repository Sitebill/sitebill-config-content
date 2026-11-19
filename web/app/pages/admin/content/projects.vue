<script setup lang="ts">
import type { CmPlatform, CmProject } from '../../../utils/content'
import { CM_DELETE_PROJECT, CM_DELETE_PLATFORM } from '../../../graphql/content/documents'

// Проекты и площадки: карточка проекта (тематика, аудитория, тон, ключевые слова) и список его площадок.
definePageMeta({ layout: 'admin', middleware: ['auth'] })

const { t } = useI18n()
const ws = useContentWorkspace()
const { confirmDialog } = useConfirmDialog()

const selected = ref<string | null>(null)
const projModal = ref<Partial<CmProject> | null>(null)
const platModal = ref<Partial<CmPlatform> | null>(null)

const current = computed(() => ws.projects.value.find(p => String(p.id) === String(selected.value)) || ws.projects.value[0] || null)
const myPlatforms = computed(() => current.value ? ws.platforms.value.filter(p => Number(p.project_id) === Number(current.value!.id)) : [])
const countOf = (id: string) => ws.pubs.value.filter(p => Number(p.project_id) === Number(id)).length
const facts = (p: CmProject) => ([['f_topic', p.topic], ['f_audience', p.audience], ['f_tone', p.tone], ['f_keywords', p.keywords], ['f_competitors', p.competitors], ['f_manager', p.manager]] as const).filter(([, v]) => v)

async function removeProject() {
  if (!await confirmDialog({ title: t('content.confirm_delete_project'), color: 'error' })) return
  await ws.run(CM_DELETE_PROJECT, { id: current.value!.id }, 'content.deleted')
  selected.value = null
}
async function removePlatform(pf: CmPlatform) {
  if (await confirmDialog({ title: t('content.confirm_delete'), color: 'error' })) await ws.run(CM_DELETE_PLATFORM, { id: pf.id }, 'content.deleted')
}
</script>

<template>
  <ConfigsContentFrame title-key="content.nav_projects" no-select always>
    <template #actions>
      <UButton v-if="ws.canManage.value" icon="i-lucide-plus" :label="t('content.project_new')" @click="projModal = {}" />
    </template>

    <div v-if="!ws.projects.value.length" class="text-center">
      <UCard><p class="py-6 text-muted">{{ t('content.empty_title') }}</p><UButton v-if="ws.canManage.value" icon="i-lucide-sparkles" :label="t('content.demo_button')" @click="ws.seed()" /></UCard>
    </div>
    <div v-else class="grid items-start gap-4 lg:grid-cols-[260px_minmax(0,1fr)]">
      <UCard :ui="{ body: 'p-2.5 sm:p-2.5' }">
        <div
          v-for="p in ws.projects.value" :key="p.id" class="flex cursor-pointer items-center gap-2.5 rounded-xl border px-3 py-2.5 hover:bg-primary/5"
          :class="current && Number(current.id) === Number(p.id) ? 'border-primary bg-primary/10' : 'border-transparent'" @click="selected = String(p.id)"
        >
          <span class="size-2.5 flex-none rounded-full" :style="{ background: p.color }" />
          <div class="min-w-0"><b class="block truncate font-semibold text-highlighted">{{ p.name }}</b><span class="text-xs text-muted">{{ t('content.n_publications', { n: countOf(p.id) }) }}</span></div>
        </div>
      </UCard>

      <div v-if="current" class="grid gap-4">
        <UCard>
          <h3 class="mb-3 flex items-center justify-between font-semibold text-highlighted">
            {{ current.name }}
            <span v-if="ws.canManage.value" class="flex gap-2">
              <UButton size="xs" color="neutral" variant="outline" :label="t('content.edit')" @click="projModal = current" />
              <UButton size="xs" color="error" variant="outline" :label="t('content.delete')" @click="removeProject" />
            </span>
          </h3>
          <p v-if="current.description" class="mb-3 text-sm text-muted">{{ current.description }}</p>
          <dl class="grid gap-x-3.5 gap-y-1.5 text-sm sm:grid-cols-[130px_minmax(0,1fr)]">
            <template v-for="[k, v] in facts(current)" :key="k"><dt class="text-muted">{{ t('content.' + k) }}</dt><dd>{{ v }}</dd></template>
          </dl>
        </UCard>

        <UCard>
          <h3 class="mb-3 flex items-center justify-between font-semibold text-highlighted">
            {{ t('content.channels') }}
            <UButton v-if="ws.canManage.value" size="xs" icon="i-lucide-plus" :label="t('content.channel_new')" @click="platModal = {}" />
          </h3>
          <div v-if="myPlatforms.length" class="overflow-x-auto">
            <table class="w-full border-collapse text-sm">
              <thead>
                <tr class="text-left text-xs font-semibold text-muted">
                  <th v-for="k in ['col_channel', 'f_url', 'f_max_length', 'f_per_week', 'f_api']" :key="k" class="whitespace-nowrap border-b border-default px-2.5 py-2">{{ t('content.' + k) }}</th>
                  <th class="border-b border-default" />
                </tr>
              </thead>
              <tbody>
                <tr v-for="pf in myPlatforms" :key="pf.id">
                  <td class="border-b border-default px-2.5 py-2.5"><b class="font-semibold text-highlighted">{{ CM_PLATFORM_ICON[pf.kind] }} {{ pf.name }}</b><br><span class="text-xs text-muted">{{ [t('content.pkind_' + pf.kind), pf.audience].filter(Boolean).join(' · ') }}</span></td>
                  <td class="border-b border-default px-2.5 py-2.5"><a v-if="pf.url" :href="pf.url" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">{{ pf.url.replace(/^https?:\/\//, '').slice(0, 32) }}</a><span v-else>—</span></td>
                  <td class="border-b border-default px-2.5 py-2.5">{{ pf.max_length || '—' }}</td>
                  <td class="border-b border-default px-2.5 py-2.5">{{ pf.per_week ?? '—' }}</td>
                  <td class="border-b border-default px-2.5 py-2.5"><UBadge variant="subtle" :color="pf.api_status === 'connected' ? 'success' : 'neutral'" :label="t('content.api_' + pf.api_status)" /></td>
                  <td v-if="ws.canManage.value" class="whitespace-nowrap border-b border-default px-2.5 py-2.5 text-right">
                    <UButton size="xs" color="neutral" variant="outline" :label="t('content.edit')" @click="platModal = pf" />
                    <UButton size="xs" color="error" variant="ghost" icon="i-lucide-x" :aria-label="t('content.delete')" @click="removePlatform(pf)" />
                  </td>
                  <td v-else class="border-b border-default" />
                </tr>
              </tbody>
            </table>
          </div>
          <p v-else class="text-sm text-muted">{{ t('content.no_channels') }}</p>
        </UCard>
      </div>
    </div>

    <ConfigsContentProjectModal v-if="projModal" :key="projModal.id || 'new'" :item="projModal" @close="projModal = null" @saved="(id: string) => selected = String(id)" />
    <ConfigsContentPlatformModal v-if="platModal && current" :key="platModal.id || 'new'" :item="platModal" :project-id="Number(current.id)" @close="platModal = null" />
  </ConfigsContentFrame>
</template>
