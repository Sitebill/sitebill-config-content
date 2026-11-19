<script setup lang="ts">
import { CM_MEMBERS, CM_SAVE_MEMBER, CM_REMOVE_MEMBER, CM_USERS_SEARCH } from '../../../graphql/content/documents'

// Команда: кто и в какой роли работает с контентом. Только владелец (админ тенанта); проверку делает бэкенд.
definePageMeta({ layout: 'admin', middleware: ['auth'] })

interface Member { id: string; user_id: string; name?: string | null; email?: string | null; role: string }

const { t } = useI18n()
const ws = useContentWorkspace()
const toast = useToast()
const { confirmDialog } = useConfirmDialog()

const members = ref<Member[]>([])
const search = ref('')
const found = ref<{ id: string; name: string; email: string }[]>([])
const newRole = ref<string>('author')
let timer: ReturnType<typeof setTimeout> | null = null

async function load() {
  try { members.value = (await ws.query(CM_MEMBERS)).contentMembers } catch (e) { toast.add({ title: ws.errorText(e), color: 'error' }) }
}
onMounted(load)

watch(search, (v) => {
  if (timer) clearTimeout(timer)
  if (v.trim().length < 2) { found.value = []; return }
  timer = setTimeout(async () => {
    try { found.value = (await ws.query(CM_USERS_SEARCH, { search: v.trim() })).adminUsers.data } catch { found.value = [] }
  }, 300)
})

async function save(userId: string, role: string) {
  try {
    await useGqlClient().mutate({ mutation: CM_SAVE_MEMBER, variables: { input: { user_id: userId, role } } })
    toast.add({ title: t('content.saved'), color: 'success' })
    search.value = ''
    found.value = []
    await load()
  } catch (e) { toast.add({ title: ws.errorText(e), color: 'error' }); await load() }
}
async function remove(m: Member) {
  if (!await confirmDialog({ title: t('content.team_confirm_remove'), color: 'error' })) return
  try {
    await useGqlClient().mutate({ mutation: CM_REMOVE_MEMBER, variables: { user_id: m.user_id } })
    toast.add({ title: t('content.deleted'), color: 'success' })
    await load()
  } catch (e) { toast.add({ title: ws.errorText(e), color: 'error' }) }
}
const free = computed(() => found.value.filter(u => !members.value.some(m => String(m.user_id) === String(u.id))))
</script>

<template>
  <ConfigsContentFrame title-key="content.nav_team" no-select no-all always>
    <UCard v-if="!ws.canTeam.value"><p class="py-6 text-center text-muted">{{ t('content.team_owner_only') }}</p></UCard>
    <div v-else class="grid items-start gap-4 lg:grid-cols-2">
      <UCard>
        <h3 class="mb-3 font-semibold text-highlighted">{{ t('content.team_members') }}</h3>
        <p v-if="!members.length" class="text-sm text-muted">{{ t('content.team_empty') }}</p>
        <div v-for="m in members" :key="m.id" class="flex items-center gap-2 border-b border-default py-2 last:border-0">
          <div class="min-w-0 flex-1"><b class="block truncate font-semibold text-highlighted">{{ m.name || m.email }}</b><span class="text-xs text-muted">{{ m.email }}</span></div>
          <select :value="m.role" :class="CM_FIELD" class="!w-36" @change="save(m.user_id, ($event.target as HTMLSelectElement).value)">
            <option v-for="r in CM_ROLES" :key="r" :value="r">{{ t('content.role_' + r) }}</option>
          </select>
          <UButton size="xs" color="error" variant="ghost" icon="i-lucide-x" :aria-label="t('content.delete')" @click="remove(m)" />
        </div>
      </UCard>

      <UCard>
        <h3 class="mb-3 font-semibold text-highlighted">{{ t('content.team_add') }}</h3>
        <div class="mb-3 grid gap-3 sm:grid-cols-[minmax(0,1fr)_150px]">
          <UFormField :label="t('content.team_search')"><UInput v-model="search" class="w-full" :placeholder="t('content.team_search_ph')" /></UFormField>
          <UFormField :label="t('content.team_role')">
            <select v-model="newRole" :class="CM_FIELD"><option v-for="r in CM_ROLES" :key="r" :value="r">{{ t('content.role_' + r) }}</option></select>
          </UFormField>
        </div>
        <div v-for="u in free" :key="u.id" class="flex items-center gap-2 border-b border-default py-2 last:border-0">
          <div class="min-w-0 flex-1"><b class="block truncate font-semibold text-highlighted">{{ u.name || u.email }}</b><span class="text-xs text-muted">{{ u.email }}</span></div>
          <UButton size="xs" icon="i-lucide-plus" :label="t('content.team_add_btn')" @click="save(u.id, newRole)" />
        </div>
        <ul class="mt-4 space-y-1 text-xs text-muted">
          <li v-for="r in CM_ROLES" :key="r"><b>{{ t('content.role_' + r) }}</b> — {{ t('content.role_hint_' + r) }}</li>
        </ul>
      </UCard>
    </div>
  </ConfigsContentFrame>
</template>
