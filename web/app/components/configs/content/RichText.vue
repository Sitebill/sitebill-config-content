<script setup lang="ts">
import type { EditorToolbarItem } from '@nuxt/ui'

/**
 * Редактор текста контент-фабрики: заголовки, жирный/курсив/зачёркнутый, списки, цитата, ссылка.
 * Хранится markdown, а не HTML: его понимает и пишет модель ИИ, он читается в БД и превью без разметки, и в нём
 * нет произвольных тегов, которые пришлось бы чистить от XSS. Подчёркивания в markdown нет, поэтому его нет и в панели.
 */
const model = defineModel<string>({ default: '' })
const props = withDefaults(defineProps<{ disabled?: boolean; placeholder?: string; compact?: boolean }>(), { disabled: false, compact: false })

const items: EditorToolbarItem[][] = [
  [{ kind: 'undo', icon: 'i-lucide-undo' }, { kind: 'redo', icon: 'i-lucide-redo' }],
  [{ kind: 'heading', level: 2, icon: 'i-lucide-heading-2' }, { kind: 'heading', level: 3, icon: 'i-lucide-heading-3' }],
  [
    { kind: 'mark', mark: 'bold', icon: 'i-lucide-bold' },
    { kind: 'mark', mark: 'italic', icon: 'i-lucide-italic' },
    { kind: 'mark', mark: 'strike', icon: 'i-lucide-strikethrough' },
  ],
  [
    { kind: 'bulletList', icon: 'i-lucide-list' },
    { kind: 'orderedList', icon: 'i-lucide-list-ordered' },
    { kind: 'blockquote', icon: 'i-lucide-text-quote' },
    { kind: 'link', icon: 'i-lucide-link' },
  ],
]
</script>

<template>
  <UEditor
    v-model="model" content-type="markdown" :editable="!props.disabled" :placeholder="props.placeholder"
    class="cm-rich overflow-y-auto rounded-md border border-default" :class="props.compact ? 'min-h-24 max-h-64' : 'min-h-48 max-h-[28rem]'"
    :ui="{ content: 'px-3 py-2 text-sm' }"
  >
    <template #default="{ editor }">
      <UEditorToolbar v-if="!props.disabled" :editor="editor" :items="items" class="sticky top-0 z-10 flex-wrap border-b border-default bg-default px-2 py-1" />
    </template>
  </UEditor>
</template>
