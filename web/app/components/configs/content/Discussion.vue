<script setup lang="ts">
import { CM_COMMENTS, CM_ADD_COMMENT, CM_DECIDE } from '../../../graphql/content/documents'

/**
 * Обсуждение публикации и согласование: лента комментариев и решений. Кнопки решения видны согласующим
 * и только пока публикация «на согласовании» — то же правило проверяет бэкенд.
 */
const props = defineProps<{ publicationId: number | string; status: string }>()
const emit = defineEmits<{ decided: [status: string] }>()

const { t, locale } = useI18n()
const ws = useContentWorkspace()
const toast = useToast()

type Item = { id: number | string; kind: 'comment' | 'approve' | 'return'; body: string | null; author: string | null; created_at: string }
const items = ref<Item[]>([])
const text = ref('')
const busy = ref(false)

async function load() {
  try { items.value = (await ws.query(CM_COMMENTS, { publication_id: props.publicationId })).contentComments } catch { items.value = [] }
}
onMounted(load)

async function send() {
  const body = text.value.trim()
  if (!body) return
  busy.value = true
  try {
    items.value = [...items.value, (await ws.mutate(CM_ADD_COMMENT, { publication_id: props.publicationId, body })).contentAddComment]
    text.value = ''
  } catch (e) { toast.add({ title: ws.errorText(e), color: 'error' }) } finally { busy.value = false }
}

async function decide(decision: 'approve' | 'return') {
  busy.value = true
  try {
    const res = await ws.mutate(CM_DECIDE, { publication_id: props.publicationId, decision, comment: text.value.trim() || null })
    items.value = [...items.value, res.contentDecide]
    text.value = ''
    await ws.reload()
    emit('decided', decision === 'approve' ? 'ready' : 'in_work')
  } catch (e) { toast.add({ title: ws.errorText(e), color: 'error' }) } finally { busy.value = false }
}

const when = (iso: string) => new Intl.DateTimeFormat(String(locale.value), { dateStyle: 'short', timeStyle: 'short' }).format(new Date(iso))
</script>

<template>
  <section class="mt-6">
    <b class="text-highlighted">{{ t('content.discuss_title') }}</b>
    <p v-if="!items.length" class="mt-1 text-sm text-muted">{{ t('content.discuss_empty') }}</p>
    <ul class="mt-2 space-y-2">
      <li v-for="c in items" :key="c.id" class="rounded-xl border border-default px-3 py-2 text-sm">
        <div class="flex flex-wrap items-center gap-2 text-xs text-muted">
          <b class="text-highlighted">{{ c.author || '—' }}</b>
          <UBadge v-if="c.kind !== 'comment'" size="sm" variant="subtle" :color="c.kind === 'approve' ? 'success' : 'warning'" :label="t('content.discuss_k_' + c.kind)" />
          <span>{{ when(c.created_at) }}</span>
        </div>
        <p v-if="c.body" class="mt-1 whitespace-pre-wrap">{{ c.body }}</p>
      </li>
    </ul>
    <div v-if="ws.canMove.value" class="mt-3 space-y-2">
      <UTextarea v-model="text" :rows="2" class="w-full" maxlength="2000" :placeholder="t('content.discuss_placeholder')" />
      <div class="flex flex-wrap gap-2">
        <UButton size="xs" :loading="busy" :disabled="!text.trim()" icon="i-lucide-send" :label="t('content.discuss_send')" @click="send" />
        <template v-if="ws.canApprove.value && status === 'review'">
          <UButton size="xs" color="success" :loading="busy" icon="i-lucide-check" :label="t('content.discuss_approve')" @click="decide('approve')" />
          <UButton size="xs" color="warning" variant="soft" :loading="busy" :disabled="!text.trim()" icon="i-lucide-undo-2" :label="t('content.discuss_return')" @click="decide('return')" />
        </template>
      </div>
    </div>
  </section>
</template>
