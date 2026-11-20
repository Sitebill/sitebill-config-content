import ContentHome from './components/configs/content/Home.vue'
import ContentDashboard from './components/configs/content/Dashboard.vue'
import type { ConfigModuleDefinition } from '~/core/configs/types'

/**
 * Точка входа frontend-части конфигурации `content` — контент-фабрика (docs/roadmap/CONTENT_FACTORY.md).
 * Регистрируется в apps/web/app/core/configs/registry.ts. Разделы админки — страницы admin/content/*.
 */
export const contentConfigModule: ConfigModuleDefinition = {
  id: 'content',
  label: 'Контент-фабрика',
  description: 'Рабочее место контент-команды: проекты, площадки, контент-план, календарь, библиотека и ИИ-генераторы.',
  homeComponent: ContentHome,
  publicLayoutMode: 'default',
  navItems: ({ t, localePath }) => [
    { label: t('content.nav_home'), icon: 'i-lucide-sparkles', to: localePath('/') },
  ],
  menuRoutes: () => [],
  userMenuItems: () => [],
  // Непустой список заменяет пункты недвижимости в админке (см. useAdminBaseNav.ts).
  // «Команда» видна всем: страница сама сообщает, что настройка — дело владельца.
  adminMenuItems: ({ t }) => [
    { label: t('content.nav_dashboard'), icon: 'i-lucide-layout-dashboard', to: '/admin' },
    { label: t('content.nav_plan'), icon: 'i-lucide-list-checks', to: '/admin/content/plan' },
    { label: t('content.nav_calendar'), icon: 'i-lucide-calendar-days', to: '/admin/content/calendar' },
    { label: t('content.nav_library'), icon: 'i-lucide-library', to: '/admin/content/library' },
    { label: t('content.nav_audiences'), icon: 'i-lucide-target', to: '/admin/content/audiences' },
    { label: t('content.nav_projects'), icon: 'i-lucide-folder-kanban', to: '/admin/content/projects' },
    { label: t('content.nav_assistant'), icon: 'i-lucide-wand-sparkles', to: '/admin/content/assistant' },
    { label: t('content.nav_team'), icon: 'i-lucide-users', to: '/admin/content/team' },
  ],
  adminDashboardComponent: ContentDashboard,
  // Участники команды без роли admin: без «Команды» (её настраивает владелец), ассистент — кроме наблюдателя.
  memberMenuItems: ({ t, role }) => [
    { label: t('content.nav_dashboard'), icon: 'i-lucide-layout-dashboard', to: '/admin' },
    { label: t('content.nav_plan'), icon: 'i-lucide-list-checks', to: '/admin/content/plan' },
    { label: t('content.nav_calendar'), icon: 'i-lucide-calendar-days', to: '/admin/content/calendar' },
    { label: t('content.nav_library'), icon: 'i-lucide-library', to: '/admin/content/library' },
    { label: t('content.nav_audiences'), icon: 'i-lucide-target', to: '/admin/content/audiences' },
    { label: t('content.nav_projects'), icon: 'i-lucide-folder-kanban', to: '/admin/content/projects' },
    ...(role === 'viewer' ? [] : [{ label: t('content.nav_assistant'), icon: 'i-lucide-wand-sparkles', to: '/admin/content/assistant' }]),
  ],
}
