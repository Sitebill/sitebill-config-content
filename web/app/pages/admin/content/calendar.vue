<script setup lang="ts">
import type { CmEvent, CmPublication } from '../../../utils/content'
import { CM_MOVE_PUBLICATION } from '../../../graphql/content/documents'

// Календарь месяца: публикации и «Важное»; публикацию можно перетащить на другой день.
definePageMeta({ layout: 'admin', middleware: ['auth'] })

const { t, locale } = useI18n()
const ws = useContentWorkspace()

const ym = useState('cm-cal-ym', () => cmToday().slice(0, 7))
const today = cmToday()

const cells = computed(() => {
  const [y, m] = ym.value.split('-').map(Number)
  const lead = (new Date(y!, m! - 1, 1).getDay() + 6) % 7 // неделя с понедельника
  const start = cmAddDays(`${ym.value}-01`, -lead)
  const days = Math.ceil((lead + new Date(y!, m!, 0).getDate()) / 7) * 7
  return [...Array(days)].map((_, i) => cmAddDays(start, i))
})

const byDay = computed(() => {
  const map = new Map<string, CmPublication[]>()
  ws.scoped.value.filter(p => p.planned_date).forEach(p => map.set(p.planned_date!, [...(map.get(p.planned_date!) || []), p]))
  map.forEach(list => list.sort((a, b) => (a.planned_time || '').localeCompare(b.planned_time || '')))
  return map
})

/** Дни рождения и праздники повторяются каждый год, остальное — один раз. */
const eventsOn = (day: string) => ws.events.value
  .filter(ev => ev.event_date === day || (['birthday', 'holiday'].includes(ev.kind) && ev.event_date.slice(5) === day.slice(5) && day > ev.event_date))
  .sort((a, b) => (a.event_time || '').localeCompare(b.event_time || ''))
const evLabel = (ev: CmEvent) => `${CM_EVENT_ICON[ev.kind]} ${ev.event_time ? ev.event_time + ' ' : ''}${ev.title}`

// ---- перетаскивание публикации на день
const dragId = ref<string | null>(null)
const over = ref('')
async function dropOn(day: string) {
  over.value = ''
  const p = ws.pubs.value.find(x => String(x.id) === String(dragId.value))
  dragId.value = null
  if (p && p.planned_date !== day) await ws.run(CM_MOVE_PUBLICATION, { id: p.id, planned_date: day }, 'content.moved')
}

// ---- всплывающая сводка дня (по наведению или по клику)
const hover = ref('')
const pinned = ref('')
const evModal = ref<Partial<CmEvent> | null>(null)
const showPop = (day: string) => hover.value === day || pinned.value === day
const closePop = () => { hover.value = ''; pinned.value = '' }
const undated = computed(() => ws.scoped.value.filter(p => !p.planned_date))
</script>

<template>
  <ConfigsContentFrame title-key="content.nav_calendar" always>
    <template #actions="{ openPub }">
      <div class="inline-flex gap-0.5 rounded-xl bg-elevated p-0.5">
        <button type="button" class="rounded-lg px-3 py-1 text-sm font-semibold" @click="ym = cmShiftYm(ym, -1)">‹</button>
        <button type="button" class="rounded-lg px-3 py-1 text-sm font-semibold" @click="ym = today.slice(0, 7)">{{ t('content.today') }}</button>
        <button type="button" class="rounded-lg px-3 py-1 text-sm font-semibold" @click="ym = cmShiftYm(ym, 1)">›</button>
      </div>
      <UButton v-if="ws.canWrite.value" color="error" variant="outline" icon="i-lucide-plus" :label="t('content.important')" @click="evModal = { event_date: today }" />
      <UButton v-if="ws.canWrite.value" icon="i-lucide-plus" :label="t('content.pub_new')" @click="openPub(null, { planned_date: today })" />
    </template>

    <template #default="{ openPub }">
      <h3 class="mb-3 text-lg font-semibold text-highlighted">{{ cmUpFirst(cmMonthTitle(locale, ym)) }}</h3>
      <div class="grid grid-cols-7 gap-px overflow-hidden rounded-2xl border border-default bg-[var(--ui-border)]">
        <div v-for="(w, i) in cmWeekdays(locale)" :key="'w' + i" class="bg-default p-2 text-center text-xs font-bold uppercase text-muted">{{ w }}</div>

        <div
          v-for="(day, idx) in cells" :key="day"
          class="relative flex min-h-24 cursor-pointer flex-col gap-0.5 bg-default p-1.5 hover:z-10 hover:ring-2 hover:ring-primary hover:ring-inset max-sm:min-h-16"
          :class="[day.slice(0, 7) !== ym ? 'opacity-45' : '', over === day ? 'outline-2 -outline-offset-2 outline-dashed outline-primary' : '']"
          @mouseenter="hover = day" @mouseleave="hover === day && (hover = '')" @click="pinned = pinned === day ? '' : day"
          @dragover.prevent="over = day" @dragleave="over === day && (over = '')" @drop.prevent="dropOn(day)"
        >
          <div class="flex justify-between text-xs font-semibold text-muted">
            <b :class="[day === today ? 'rounded-full bg-primary px-1.5 text-inverted' : '', eventsOn(day).length ? 'text-error' : '']">{{ Number(day.slice(8)) }}</b>
            <span v-if="eventsOn(day).length + (byDay.get(day)?.length || 0) > 3">+{{ eventsOn(day).length + (byDay.get(day)?.length || 0) - 3 }}</span>
          </div>
          <div v-for="ev in eventsOn(day).slice(0, 3)" :key="'e' + ev.id" class="cursor-pointer truncate rounded-md border-l-[3px] border-error bg-error/15 px-1.5 py-0.5 text-[11.5px] font-semibold text-error" :title="ev.title" @click.stop="evModal = ev">{{ evLabel(ev) }}</div>
          <div
            v-for="p in (byDay.get(day) || []).slice(0, Math.max(0, 3 - eventsOn(day).length))" :key="p.id" draggable="true"
            class="cursor-grab truncate rounded-md border-l-[3px] px-1.5 py-0.5 text-[11.5px]" :title="p.title"
            :style="{ borderColor: CM_STATUS_COLOR[p.status], background: `color-mix(in srgb, ${CM_STATUS_COLOR[p.status]} 17%, transparent)` }"
            @dragstart="dragId = p.id" @click.stop="openPub(p)"
          >{{ (p.planned_time ? p.planned_time + ' ' : '') + p.title }}</div>

          <div v-if="showPop(day)" class="absolute left-1/2 z-30 flex w-64 max-w-[80vw] -translate-x-1/2 cursor-default flex-col gap-1 rounded-2xl border border-default bg-default p-2.5 shadow-xl" :class="idx >= cells.length - 14 ? 'bottom-full' : 'top-full'" @click.stop>
            <h4 class="mb-1 text-xs font-bold text-muted">{{ cmUpFirst(cmDayTitle(locale, day)) }}</h4>
            <div v-for="ev in eventsOn(day)" :key="'pe' + ev.id" class="cursor-pointer rounded-lg border-l-[3px] border-error bg-error/10 px-2 py-1.5 text-[13px] font-semibold text-error" @click="closePop(); evModal = ev">{{ evLabel(ev) }}</div>
            <div v-for="p in (byDay.get(day) || [])" :key="p.id" class="flex cursor-pointer items-center gap-2 rounded-lg border-l-[3px] px-2 py-1.5 text-[13px]" :style="{ borderColor: CM_STATUS_COLOR[p.status], background: `color-mix(in srgb, ${CM_STATUS_COLOR[p.status]} 8%, transparent)` }" @click="closePop(); openPub(p)">
              <span class="min-w-0 flex-1 truncate">{{ (p.planned_time ? p.planned_time + ' ' : '') + p.title }}</span>
              <small class="text-muted">{{ t('content.status_' + p.status) }}</small>
            </div>
            <p v-if="!eventsOn(day).length && !(byDay.get(day) || []).length" class="text-sm text-muted">{{ t('content.day_empty') }}</p>
            <div v-if="ws.canWrite.value" class="mt-1 flex flex-wrap gap-1.5">
              <UButton size="xs" color="neutral" variant="outline" :label="'+ ' + t('content.pub_short')" @click="closePop(); openPub(null, { planned_date: day })" />
              <UButton size="xs" color="error" variant="outline" :label="'+ ' + t('content.important')" @click="closePop(); evModal = { event_date: day }" />
            </div>
          </div>
        </div>
      </div>

      <UCard v-if="undated.length" class="mt-4">
        <h3 class="mb-2 flex items-center justify-between font-semibold text-highlighted">{{ t('content.no_date_title') }}<small class="font-medium text-muted">{{ undated.length }}</small></h3>
        <ConfigsContentPubRow v-for="p in undated" :key="p.id" :pub="p" />
      </UCard>

      <ConfigsContentEventModal v-if="evModal" :key="evModal.id || 'new' + evModal.event_date" :item="evModal" @close="evModal = null" />
    </template>
  </ConfigsContentFrame>
</template>
