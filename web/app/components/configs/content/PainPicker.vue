<script setup lang="ts">
/**
 * Выбор аудитории и её болей в карточке публикации: отмеченные боли уходят в ИИ-генератор (черновик, улучшение,
 * адаптация). При смене аудитории отметки сбрасываются — боли привязаны к своей аудитории.
 */
const audienceId = defineModel<string>('audience', { required: true })
const painIds = defineModel<string[]>('pains', { required: true })
defineProps<{ disabled?: boolean }>()

const { t } = useI18n()
const ws = useContentWorkspace()

const audiences = computed(() => ws.materials.value.filter(m => m.kind === 'audience'))
const sections = computed(() => cmParsePains(audiences.value.find(m => String(m.id) === audienceId.value)?.pains))
const hasPains = computed(() => sections.value.some(s => s.pains.some(p => p.title.trim())))

function toggle(id: string) {
  painIds.value = painIds.value.includes(id) ? painIds.value.filter(x => x !== id) : [...painIds.value, id]
}
function pick(e: Event) {
  audienceId.value = (e.target as HTMLSelectElement).value
  painIds.value = []
}
</script>

<template>
  <div class="space-y-2">
    <UFormField :label="t('content.pub_audience')">
      <select :value="audienceId" :class="CM_FIELD" :disabled="disabled" @change="pick">
        <option value="">{{ t('content.pub_audience_none') }}</option>
        <option v-for="a in audiences" :key="a.id" :value="String(a.id)">{{ a.title }}</option>
      </select>
    </UFormField>

    <template v-if="audienceId">
      <div v-if="hasPains" class="space-y-2">
        <b class="block text-sm text-highlighted">{{ t('content.pub_pains') }}</b>
        <div v-for="s in sections" :key="s.id">
          <div v-if="s.pains.some(p => p.title.trim())" class="mb-1 text-xs text-muted">{{ s.title }}</div>
          <div class="flex flex-wrap gap-1.5">
            <label
              v-for="p in s.pains.filter(p => p.title.trim())" :key="p.id" :title="p.description"
              class="inline-flex cursor-pointer select-none items-center gap-1.5 rounded-lg border px-2.5 py-1 text-sm"
              :class="painIds.includes(p.id) ? 'border-primary bg-primary/10' : 'border-default'"
            >
              <input type="checkbox" class="hidden" :checked="painIds.includes(p.id)" :disabled="disabled" @change="toggle(p.id)">
              {{ p.title }}
            </label>
          </div>
        </div>
      </div>
      <p v-else class="text-sm text-muted">
        {{ t('content.pub_pains_empty') }}
        <NuxtLink :to="`/admin/content/audiences/${audienceId}`" class="text-primary hover:underline">{{ t('content.pub_pains_edit') }}</NuxtLink>
      </p>
    </template>
  </div>
</template>
