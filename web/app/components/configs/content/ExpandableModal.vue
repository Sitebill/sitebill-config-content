<script setup lang="ts">
/**
 * Модалка форм контент-фабрики с кнопкой «Развернуть»: длинные тексты удобнее вводить на весь экран.
 * Выбор запоминается на вкладке (sessionStorage), чтобы не разворачивать каждое окно заново.
 */
const props = withDefaults(defineProps<{ title: string; maxWidth?: string }>(), { maxWidth: 'max-w-lg' })
const emit = defineEmits<{ close: [] }>()

const { t } = useI18n()
const KEY = 'content.modal.expanded'
const expanded = ref(false)
onMounted(() => { try { expanded.value = sessionStorage.getItem(KEY) === '1' } catch { /* без хранилища — по умолчанию свёрнуто */ } })
function toggle() {
  expanded.value = !expanded.value
  try { sessionStorage.setItem(KEY, expanded.value ? '1' : '0') } catch { /* не критично */ }
}
// В развёрнутом виде поля текста тянутся по высоте экрана.
const ui = computed(() => ({ content: expanded.value ? '[&_textarea]:!min-h-[45vh]' : props.maxWidth }))
</script>

<template>
  <UModal :open="true" :title="title" :fullscreen="expanded" :ui="ui" @update:open="(v: boolean) => !v && emit('close')">
    <template #actions>
      <UButton
        color="neutral" variant="ghost" size="sm" :icon="expanded ? 'i-lucide-minimize-2' : 'i-lucide-maximize-2'"
        :aria-label="t(expanded ? 'content.modal_collapse' : 'content.modal_expand')" :title="t(expanded ? 'content.modal_collapse' : 'content.modal_expand')" @click="toggle"
      />
    </template>
    <template #body><slot /></template>
  </UModal>
</template>
