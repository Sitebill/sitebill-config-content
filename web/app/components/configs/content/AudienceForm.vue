<script setup lang="ts">
import type { CmMaterial, CmPainSection } from '../../../utils/content'
import { CM_SAVE_MATERIAL } from '../../../graphql/content/documents'

/**
 * Страница аудитории (не модалка): название, сфера, описание и конструктор «Болей аудитории». Рендерится внутри Frame,
 * то есть уже после загрузки библиотеки, поэтому запись можно искать в ws.materials сразу.
 */
const props = defineProps<{ id: string }>()

const { t } = useI18n()
const ws = useContentWorkspace()
const router = useRouter()

const item = computed<CmMaterial | null>(() => props.id === 'new' ? null : ws.materials.value.find(m => m.kind === 'audience' && String(m.id) === props.id) ?? null)
const missing = computed(() => props.id !== 'new' && !item.value)

const f = reactive({
  title: item.value?.title || '', sphere: item.value?.sphere || 'work', entry_date: item.value?.entry_date || cmToday(),
  body: item.value?.body || '', pains: cmParsePains(item.value?.pains),
})
const busy = ref(false)
const readonly = computed(() => !ws.canWrite.value)

async function save() {
  if (readonly.value) return
  if (!f.title.trim()) { useToast().add({ title: t('content.aud_need_title'), color: 'warning' }); return }
  busy.value = true
  const data = await ws.run(CM_SAVE_MATERIAL, {
    id: item.value?.id || null,
    input: {
      kind: 'audience', title: f.title.trim(), body: f.body || null, sphere: f.sphere || null, entry_date: f.entry_date || null,
      pains: JSON.stringify(f.pains as CmPainSection[]),
    },
  })
  busy.value = false
  // После создания остаёмся на странице уже сохранённой аудитории: так её можно сразу дописывать.
  if (data && !item.value) await router.replace(`/admin/content/audiences/${data.contentSaveMaterial.id}`)
}
</script>

<template>
  <div v-if="missing" class="space-y-3 py-10 text-center text-muted">
    <p>{{ t('content.aud_not_found') }}</p>
    <UButton to="/admin/content/audiences" color="neutral" variant="outline" :label="t('content.aud_back')" />
  </div>

  <form v-else class="space-y-5" @submit.prevent="save">
    <NuxtLink to="/admin/content/audiences" class="inline-flex items-center gap-1 text-sm text-muted hover:text-highlighted">
      <UIcon name="i-lucide-arrow-left" /> {{ t('content.aud_back') }}
    </NuxtLink>

    <UCard>
      <div class="space-y-4">
        <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_180px_160px]">
          <UFormField :label="t('content.lf_title_audience')" required><UInput v-model="f.title" class="w-full" maxlength="200" :disabled="readonly" /></UFormField>
          <UFormField :label="t('content.f_audience_kind')">
            <select v-model="f.sphere" :class="CM_FIELD" :disabled="readonly"><option v-for="s in ['work', 'art']" :key="s" :value="s">{{ t('content.sphere_' + s) }}</option></select>
          </UFormField>
          <UFormField :label="t('content.lf_date_audience')"><input v-model="f.entry_date" type="date" :class="CM_FIELD" :disabled="readonly"></UFormField>
        </div>
        <UFormField :label="t('content.lf_body_audience')"><ConfigsContentRichText v-model="f.body" :disabled="readonly" /></UFormField>
      </div>
    </UCard>

    <section class="space-y-3">
      <div>
        <h3 class="text-lg font-semibold text-highlighted">{{ t('content.pains_title') }}</h3>
        <p class="text-sm text-muted">{{ t('content.pains_hint') }}</p>
      </div>
      <ConfigsContentAudiencePains v-model="f.pains" :disabled="readonly" />
    </section>

    <div class="flex gap-2">
      <UButton type="submit" :loading="busy" :disabled="readonly" :label="t('content.save')" />
      <UButton to="/admin/content/audiences" color="neutral" variant="outline" :label="t('content.cancel')" />
    </div>
  </form>
</template>
