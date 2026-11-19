import type { DocumentNode } from '@apollo/client/core'
import type { CmProject, CmPlatform, CmPublication, CmMaterial, CmEvent } from '../utils/content'
import { CM_STATUSES, CM_APPROVED_STATUSES } from '../utils/content'
import {
  CM_PROJECTS, CM_PLATFORMS, CM_PUBLICATIONS, CM_MATERIALS, CM_EVENTS, CM_MY_ROLE, CM_SEED_DEMO,
} from '../graphql/content/documents'

/**
 * Рабочее пространство контент-фабрики: проекты, площадки, публикации, материалы, «Важное» и роль пользователя.
 * Состояние общее для всех страниц (useState), перезагружается целиком после каждой мутации — данных мало
 * (сотни публикаций), а правила размещения и статусов считает бэкенд.
 * Права здесь — только подсказки для интерфейса; настоящую проверку делает бэкенд (ContentAccess).
 */
export function useContentWorkspace() {
  const apollo = useGqlClient()
  const toast = useToast()
  const { t, locale } = useI18n()

  const projects = useState<CmProject[]>('cm-projects', () => [])
  const platforms = useState<CmPlatform[]>('cm-platforms', () => [])
  const pubs = useState<CmPublication[]>('cm-pubs', () => [])
  const materials = useState<CmMaterial[]>('cm-materials', () => [])
  const events = useState<CmEvent[]>('cm-events', () => [])
  const role = useState<string>('cm-role', () => '')
  const loading = useState('cm-loading', () => true)
  const denied = useState('cm-denied', () => false)
  const filter = useState<string>('cm-project-filter', () => '')

  const projectById = computed(() => new Map(projects.value.map(p => [Number(p.id), p])))
  const platformById = computed(() => new Map(platforms.value.map(p => [Number(p.id), p])))
  const scoped = computed(() => filter.value ? pubs.value.filter(p => String(p.project_id) === filter.value) : pubs.value)
  const scopedPlatforms = computed(() => filter.value ? platforms.value.filter(p => String(p.project_id) === filter.value) : platforms.value)
  const isEmpty = computed(() => !projects.value.length && !pubs.value.length)

  // Подсказки интерфейса; зеркало ContentAccess::ABILITIES.
  const canWrite = computed(() => ['owner', 'editor', 'author'].includes(role.value))
  const canMove = computed(() => ['owner', 'editor', 'author', 'approver'].includes(role.value))
  const canApprove = computed(() => ['owner', 'editor', 'approver'].includes(role.value))
  const canManage = computed(() => ['owner', 'editor'].includes(role.value))
  const canTeam = computed(() => role.value === 'owner')
  /** Статусы, которые пользователь может выбрать в карточке. */
  const allowedStatuses = computed(() => CM_STATUSES.filter(s => canApprove.value || !CM_APPROVED_STATUSES.includes(s)))

  /** Текст ошибки GraphQL для пользователя: сначала ошибки валидации полей, затем общее сообщение. */
  function errorText(e: any): string {
    const validation = e?.graphQLErrors?.[0]?.extensions?.validation as Record<string, string[]> | undefined
    const first = validation ? Object.values(validation)[0]?.[0] : null
    return first || e?.graphQLErrors?.[0]?.message || e?.message || t('content.error')
  }

  async function query<T = any>(query: DocumentNode, variables: Record<string, unknown> = {}): Promise<T> {
    const res = await apollo.query({ query, variables, fetchPolicy: 'network-only' })
    return res.data as T
  }

  /** Мутация без тоста и перезагрузки: для ИИ-предложений, которые ничего не сохраняют. */
  async function mutate<T = any>(mutation: DocumentNode, variables: Record<string, unknown> = {}): Promise<T> {
    const res = await apollo.mutate({ mutation, variables })
    return res.data as T
  }

  async function reload() {
    try {
      const [a, b, c, d, e, r] = await Promise.all([
        query(CM_PROJECTS), query(CM_PLATFORMS), query(CM_PUBLICATIONS), query(CM_MATERIALS), query(CM_EVENTS), query(CM_MY_ROLE),
      ])
      projects.value = a.contentProjects
      platforms.value = b.contentPlatforms
      pubs.value = c.contentPublications
      materials.value = d.contentMaterials
      events.value = e.contentEvents
      role.value = r.contentMyRole
      denied.value = false
      if (filter.value && !projects.value.some(p => String(p.id) === filter.value)) filter.value = ''
    } catch {
      denied.value = true
    } finally {
      loading.value = false
    }
  }

  /**
   * Мутация с тостом и перезагрузкой данных. Возвращает данные ответа или null при ошибке
   * (ошибка уже показана пользователю).
   */
  async function run<T = any>(mutation: DocumentNode, variables: Record<string, unknown>, okKey: string | null = 'content.saved'): Promise<T | null> {
    try {
      const res = await apollo.mutate({ mutation, variables })
      if (okKey) toast.add({ title: t(okKey), color: 'success' })
      await reload()
      return res.data as T
    } catch (e) {
      toast.add({ title: errorText(e), color: 'error' })
      return null
    }
  }

  const seed = () => run(CM_SEED_DEMO, { locale: String(locale.value).slice(0, 2) }, 'content.demo_loaded')

  // Выбранный проект запоминаем в браузере (необязательное удобство).
  if (import.meta.client) {
    onMounted(() => {
      try { if (!filter.value) filter.value = localStorage.getItem('cm.project') || '' } catch { /* хранилище недоступно */ }
    })
    watch(filter, (v) => { try { localStorage.setItem('cm.project', v) } catch { /* ignore */ } })
  }

  return {
    projects, platforms, pubs, materials, events, role, loading, denied, filter,
    projectById, platformById, scoped, scopedPlatforms, isEmpty,
    canWrite, canMove, canApprove, canManage, canTeam, allowedStatuses,
    reload, run, query, mutate, errorText, seed,
  }
}
