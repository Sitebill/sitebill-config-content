<script setup lang="ts">
// Главная конфигурации `content`: гость видит лендинг, вошедший — дашборд команды
// (так участники без доступа в админку тоже попадают в рабочее место; см. docs/roadmap/CONTENT_FACTORY.md §10).
const { t } = useI18n()
const localePath = useLocalePath()
const auth = useAuthStore()
const features = [
  { icon: 'i-lucide-calendar-days', title: 'content.f1_title', text: 'content.f1_text' },
  { icon: 'i-lucide-share-2', title: 'content.f2_title', text: 'content.f2_text' },
  { icon: 'i-lucide-sparkles', title: 'content.f3_title', text: 'content.f3_text' },
]
</script>

<template>
  <div v-if="auth.logged" class="px-4 py-6 max-w-6xl mx-auto"><ConfigsContentDashboard /></div>
  <div v-else class="px-4 py-16 max-w-4xl mx-auto">
    <div class="text-center mb-12">
      <h1 class="text-3xl font-bold mb-3">{{ t('content.home_title') }}</h1>
      <p class="text-muted mb-6">{{ t('content.home_description') }}</p>
      <UButton :to="localePath('/auth/login')" size="lg">{{ t('content.home_cta') }}</UButton>
    </div>
    <div class="grid gap-4 sm:grid-cols-3">
      <UCard v-for="f in features" :key="f.title">
        <UIcon :name="f.icon" class="size-8 text-primary mb-3" />
        <h3 class="font-semibold mb-1">{{ t(f.title) }}</h3>
        <p class="text-sm text-muted">{{ t(f.text) }}</p>
      </UCard>
    </div>
  </div>
</template>
