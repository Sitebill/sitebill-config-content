<script setup lang="ts">
// Страница одной аудитории (id или `new`): форма и конструктор болей. Загрузку и «нет доступа» берёт на себя Frame.
definePageMeta({ layout: 'admin', middleware: ['auth'] })
const route = useRoute()
</script>

<template>
  <ConfigsContentFrame title-key="content.nav_audiences" no-select always>
    <template #default>
      <ConfigsContentAudienceForm :id="String(route.params.id)" :key="String(route.params.id)" />
    </template>
  </ConfigsContentFrame>
</template>
