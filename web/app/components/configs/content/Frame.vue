<script setup lang="ts">
import type { CmPublication } from '../../../utils/content'

/**
 * Каркас страниц контент-фабрики: заголовок, выбор проекта, загрузка/«нет доступа»/пустое пространство и
 * карточка публикации. Страницы кладут своё содержимое в слот; открыть карточку можно через inject('cmOpenPub').
 */
const props = defineProps<{ titleKey: string; noSelect?: boolean; noAll?: boolean; always?: boolean }>()
const { t } = useI18n()
const ws = useContentWorkspace()

const modal = ref<{ pub: CmPublication | null; defaults: Record<string, any>; key: string } | null>(null)
function openPub(pub: CmPublication | null, defaults: Record<string, any> = {}) {
  modal.value = { pub, defaults, key: String(Date.now()) + Math.random() }
}
provide('cmOpenPub', openPub)

useSeoMeta({ title: () => t(props.titleKey) })
onMounted(() => ws.reload())

// Страницы без «Все проекты» (ассистент) всегда работают с конкретным проектом.
watchEffect(() => {
  if (props.noAll && !ws.filter.value && ws.projects.value.length) ws.filter.value = String(ws.projects.value[0]!.id)
})
</script>

<template>
  <div class="mx-auto max-w-[1240px] space-y-4 pb-10">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h2 class="text-xl font-bold text-highlighted">{{ t(titleKey) }}</h2>
      <div class="flex flex-wrap items-center gap-2">
        <select v-if="ws.projects.value.length && !noSelect" v-model="ws.filter.value" :class="CM_FIELD" class="!w-auto min-w-44">
          <option v-if="!noAll" value="">{{ t('content.all_projects') }}</option>
          <option v-for="p in ws.projects.value" :key="p.id" :value="String(p.id)">{{ p.name }}</option>
        </select>
        <slot name="actions" :open-pub="openPub" />
      </div>
    </div>

    <p v-if="ws.loading.value" class="py-16 text-center text-muted">{{ t('content.loading') }}</p>
    <p v-else-if="ws.denied.value" class="py-16 text-center text-muted">{{ t('content.denied') }}</p>
    <UCard v-else-if="ws.isEmpty.value && !always" class="text-center">
      <div class="py-6">
        <div class="mb-2 text-4xl">🗓️</div>
        <h3 class="mb-1 text-lg font-semibold text-highlighted">{{ t('content.empty_title') }}</h3>
        <p class="mx-auto mb-4 max-w-md text-sm text-muted">{{ t('content.demo_text') }}</p>
        <UButton v-if="ws.canManage.value" icon="i-lucide-sparkles" :label="t('content.demo_button')" @click="ws.seed()" />
      </div>
    </UCard>
    <slot v-else :open-pub="openPub" />

    <ConfigsContentPubModal
      v-if="modal" :key="modal.key" :pub="modal.pub" :defaults="modal.defaults"
      @close="modal = null" @open="(p: CmPublication) => openPub(p)"
    />
  </div>
</template>
