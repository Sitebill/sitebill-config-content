<script setup lang="ts">
import type { CmPlatform } from '../../../utils/content'
import { CM_AI_GENERATE, CM_AI_PROMPT, CM_AI_STATUS } from '../../../graphql/content/documents'

/**
 * ИИ-помощник в карточке публикации: черновик, улучшение, заголовки, хештеги. Ничего не сохраняет сам — результат
 * показывается как предложение, и пользователь применяет его к полям карточки. Модель, ключ, лимиты и журнал —
 * в общем ИИ-шлюзе платформы (свой ключ тенанта или платформенная модель), здесь только интерфейс.
 */
const props = defineProps<{
  projectId: number
  title: string
  kind: string
  rubric: string
  body: string
  audienceId?: number | null
  painIds?: string[]
  platforms: CmPlatform[]
}>()
const emit = defineEmits<{ apply: [patch: { title?: string; body?: string; hashtags?: string }] }>()

const { t, locale } = useI18n()
const ws = useContentWorkspace()

const status = ref<{ available: boolean; source: string | null } | null>(null)
const instruction = ref('')
const platformId = ref('')
const rewriteMode = ref('improve')
const busy = ref('')
// Режим «только промпт»: кнопки собирают готовый промпт для другого чат-бота, модель не вызывается. Без подключённого ИИ он единственный.
const promptOnly = ref(false)
const asPrompt = computed(() => promptOnly.value || (!!status.value && !status.value.available))
const result = ref<{ action: string; text: string; items: string[] } | null>(null)

onMounted(async () => {
  try { status.value = (await ws.query(CM_AI_STATUS)).aiStatus } catch { status.value = { available: false, source: null } }
})

const REWRITE_MODES = ['improve', 'shorten', 'expand', 'lighter', 'formal'] as const

async function run(action: 'draft' | 'rewrite' | 'titles' | 'hashtags') {
  if (busy.value) return
  // Без проекта ИИ не знает бренд-память: раньше кнопка молча ничего не делала.
  if (!props.projectId) { useToast().add({ title: t('content.ai_need_project'), color: 'warning' }); return }
  busy.value = action
  result.value = null
  try {
    const wish = action === 'rewrite' ? [t('content.ai_mode_' + rewriteMode.value), instruction.value].filter(Boolean).join('. ') : instruction.value
    const res = await ws.mutate(asPrompt.value ? CM_AI_PROMPT : CM_AI_GENERATE, {
      input: {
        action, project_id: props.projectId, platform_id: platformId.value ? Number(platformId.value) : null,
        title: props.title || null, kind: props.kind || null, rubric: props.rubric || null,
        text: props.body || null, instruction: wish || null, locale: String(locale.value).slice(0, 2),
        audience_id: props.audienceId || null, pain_ids: props.audienceId ? props.painIds || [] : [],
      },
    })
    result.value = asPrompt.value ? { action: 'prompt', text: res.contentAiPrompt, items: [] } : { action, ...res.contentAiGenerate }
  } catch (e) {
    useToast().add({ title: ws.errorText(e), color: 'error' })
  } finally {
    busy.value = ''
  }
}

const needsText = (a: string) => a !== 'draft' && !props.body.trim() && !(a === 'titles' && props.title.trim())

function applyText(mode: 'replace' | 'append') {
  const text = result.value!.text
  emit('apply', { body: mode === 'replace' || !props.body ? text : `${props.body}\n\n${text}` })
  result.value = null
}
async function copyPrompt() {
  try {
    await navigator.clipboard.writeText(result.value!.text)
    useToast().add({ title: t('content.ai_prompt_copied'), color: 'success' })
  } catch {
    useToast().add({ title: t('content.ai_prompt_copy_failed'), color: 'warning' })
  }
}
async function copyHashtags() {
  try {
    await navigator.clipboard.writeText(result.value!.items.join(' '))
    useToast().add({ title: t('content.ai_hashtags_copied'), color: 'success' })
  } catch {
    useToast().add({ title: t('content.ai_prompt_copy_failed'), color: 'warning' })
  }
}
function applyTitle(title: string) { emit('apply', { title }); result.value = null }
function applyHashtags() { emit('apply', { hashtags: result.value!.items.join(' ') }); result.value = null }
</script>

<template>
  <section v-if="ws.canWrite.value" class="rounded-xl border border-dashed border-primary/40 bg-primary/5 p-3">
    <div class="mb-2 flex flex-wrap items-center gap-2">
      <b class="text-highlighted">✨ {{ t('content.ai_title') }}</b>
      <span v-if="status?.available" class="text-xs text-muted">{{ t('content.ai_source_' + (status.source === 'platform' ? 'platform' : 'own')) }}</span>
    </div>

    <p v-if="status && !status.available" class="mb-2 text-sm text-muted">
      {{ t('content.ai_unavailable') }}
      <NuxtLink v-if="ws.canTeam.value" to="/admin/settings" class="text-primary hover:underline">{{ t('content.ai_settings_link') }}</NuxtLink>
      {{ t('content.ai_prompt_still') }}
    </p>

    <template v-if="status">
      <label v-if="status.available" class="mb-2 inline-flex cursor-pointer select-none items-center gap-2 text-sm">
        <input v-model="promptOnly" type="checkbox"> {{ t('content.ai_prompt_mode') }}
      </label>
      <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_180px]">
        <input v-model="instruction" maxlength="500" :class="CM_FIELD" :placeholder="t('content.ai_instruction_ph')">
        <select v-model="platformId" :class="CM_FIELD" :title="t('content.ai_for_channel')">
          <option value="">{{ t('content.ai_any_channel') }}</option>
          <option v-for="pf in platforms" :key="pf.id" :value="String(pf.id)">{{ pf.name }}</option>
        </select>
      </div>

      <div class="mt-2 flex flex-wrap items-center gap-2">
        <UButton size="xs" icon="i-lucide-wand-sparkles" :loading="busy === 'draft'" :disabled="!!busy" :label="t('content.ai_draft')" @click="run('draft')" />
        <span class="inline-flex items-center gap-1">
          <UButton size="xs" color="neutral" variant="outline" icon="i-lucide-pencil-line" :loading="busy === 'rewrite'" :disabled="!!busy || needsText('rewrite')" :label="t('content.ai_rewrite')" @click="run('rewrite')" />
          <select v-model="rewriteMode" :class="CM_FIELD" class="!h-7 !w-auto !text-xs">
            <option v-for="m in REWRITE_MODES" :key="m" :value="m">{{ t('content.ai_mode_' + m) }}</option>
          </select>
        </span>
        <UButton size="xs" color="neutral" variant="outline" icon="i-lucide-heading" :loading="busy === 'titles'" :disabled="!!busy || needsText('titles')" :label="t('content.ai_titles')" @click="run('titles')" />
        <UButton size="xs" color="neutral" variant="outline" icon="i-lucide-hash" :loading="busy === 'hashtags'" :disabled="!!busy || needsText('hashtags')" :label="t('content.ai_hashtags')" @click="run('hashtags')" />
      </div>

      <div v-if="result" class="mt-3 space-y-2 rounded-lg bg-default p-3">
        <template v-if="result.action === 'titles'">
          <button v-for="item in result.items" :key="item" type="button" class="block w-full rounded-lg border border-default px-3 py-1.5 text-left text-sm hover:border-primary" @click="applyTitle(item)">{{ item }}</button>
        </template>
        <template v-else-if="result.action === 'hashtags'">
          <p class="text-sm">{{ result.items.join(' ') }}</p>
          <UButton size="xs" :label="t('content.ai_apply')" @click="applyHashtags" />
        </template>
        <template v-else>
          <pre class="max-h-56 overflow-auto whitespace-pre-wrap font-sans text-sm">{{ result.text }}</pre>
          <div v-if="result.action === 'prompt'" class="flex flex-wrap items-center gap-2">
            <UButton size="xs" icon="i-lucide-copy" :label="t('content.ai_prompt_copy')" @click="copyPrompt" />
            <span class="text-xs text-muted">{{ t('content.ai_prompt_hint') }}</span>
          </div>
          <div v-if="result.action !== 'prompt' && result.items.length" class="flex flex-wrap items-center gap-2 rounded-lg bg-elevated/60 px-3 py-2">
            <span class="min-w-0 flex-1 break-words text-sm">{{ result.items.join(' ') }}</span>
            <UButton size="xs" color="neutral" variant="outline" icon="i-lucide-copy" :label="t('content.ai_hashtags_copy')" @click="copyHashtags" />
            <UButton size="xs" color="neutral" variant="outline" icon="i-lucide-hash" :label="t('content.ai_apply')" @click="emit('apply', { hashtags: result!.items.join(' ') })" />
          </div>
          <div v-if="result.action !== 'prompt'" class="flex flex-wrap gap-2">
            <UButton size="xs" :label="t('content.ai_replace')" @click="applyText('replace')" />
            <UButton v-if="body" size="xs" color="neutral" variant="outline" :label="t('content.ai_append')" @click="applyText('append')" />
          </div>
        </template>
        <div class="flex items-center gap-3 text-xs text-muted">
          <span v-if="result.action !== 'prompt'">{{ t('content.ai_check_note') }}</span>
          <button type="button" class="hover:underline" @click="result = null">{{ t('content.cancel') }}</button>
        </div>
      </div>
    </template>
  </section>
</template>
