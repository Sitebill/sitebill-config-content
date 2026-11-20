// Прототип сборки web-части приложения Community из SFC конфига `content` (docs/roadmap/CONTENT_FACTORY.md §8).
// Рантайм приложений грузит готовый ESM с общим Vue хоста и не умеет собирать .vue, поэтому компилируем их здесь:
// Vite (lib-режим) + plugin-vue + авто-импорты Nuxt, замещённые слоем shim/ поверх `host`.
//
// Запуск: node tools/pack-community/build-web.mjs [--app rumantic.content-manager] [--out <каталог>]
// Зависимости (vite, plugin-vue, unplugin-*) берутся из apps/web/node_modules монорепозитория.
import { createRequire } from 'node:module'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'

const here = path.dirname(fileURLToPath(import.meta.url))
const configRoot = path.resolve(here, '../..')
const webApp = path.join(configRoot, 'web/app')
const arg = (name, def) => { const i = process.argv.indexOf('--' + name); return i > -1 ? process.argv[i + 1] : def }
const appId = arg('app', 'rumantic.content-manager')
const outDir = path.resolve(arg('out', path.join(here, '.build/web')))
const nm = path.resolve(configRoot, '../../apps/web/node_modules')
const req = createRequire(path.join(nm, 'x.js'))
const load = async name => import(pathToFileURL(req.resolve(name)).href)

const { build } = await load('vite')
const vue = (await load('@vitejs/plugin-vue')).default
const AutoImport = (await load('unplugin-auto-import/vite')).default
const Components = (await load('unplugin-vue-components/vite')).default

const gqlNamesFile = path.join(path.dirname(outDir), 'gql-names.json')
if (!fs.existsSync(gqlNamesFile)) throw new Error('build-web: нет gql-names.json — сначала build-api.mjs')
const gqlNames = JSON.parse(fs.readFileSync(gqlNamesFile, 'utf8'))

const slash = p => p.replace(/\\/g, '/')
const work = path.join(here, '.build/work')
fs.mkdirSync(work, { recursive: true })

// ---- shim vue: всё, что экспортирует Vue, берём у хоста
const vueNames = Object.keys(req('vue')).filter(k => /^[A-Za-z_$][\w$]*$/.test(k) && k !== 'default')
fs.writeFileSync(path.join(work, 'vue.mjs'), `const V = globalThis.__SB_HOST__.vue\nexport default V\n${vueNames.map(k => `export const ${k} = V.${k}`).join('\n')}\n`)

const shimNuxt = slash(path.join(here, 'shim/nuxt.mjs'))
const shimComponents = slash(path.join(here, 'shim/components.mjs'))
const uiNames = ['UButton', 'UInput', 'UTextarea', 'UBadge', 'UIcon', 'UModal', 'UCard', 'USwitch', 'USelect', 'UFormField', 'UEditor', 'UEditorToolbar', 'NuxtLink', 'ClientOnly']

// ---- точка входа: страницы и виджеты приложения
const P = slash(path.join(webApp, 'pages/admin/content'))
const C = slash(path.join(webApp, 'components/configs/content'))
fs.writeFileSync(path.join(work, 'entry.mjs'), `
import { defineComponent, h } from 'vue'
import { setHost, syncLocation, useRoute } from '${shimNuxt}'
import Dashboard from '${C}/Dashboard.vue'
import Home from '${C}/Home.vue'
import Plan from '${P}/plan.vue'
import Calendar from '${P}/calendar.vue'
import Library from '${P}/library.vue'
import Projects from '${P}/projects.vue'
import Assistant from '${P}/assistant.vue'
import AudiencesIndex from '${P}/audiences/index.vue'
import AudienceEdit from '${P}/audiences/[id].vue'

// Компонент получает host пропсом (контракт рантайма приложений); композаблы shim читают его из модуля.
const wrap = (name, render) => defineComponent({
  name,
  props: { host: { type: Object, required: true } },
  setup(props) {
    setHost(props.host, ${JSON.stringify(appId)})
    syncLocation()
    const route = useRoute()
    return () => h(render(route))
  },
})

export const pages = {
  '': wrap('CmDashboardPage', () => Dashboard),
  plan: wrap('CmPlanPage', () => Plan),
  calendar: wrap('CmCalendarPage', () => Calendar),
  library: wrap('CmLibraryPage', () => Library),
  projects: wrap('CmProjectsPage', () => Projects),
  assistant: wrap('CmAssistantPage', () => Assistant),
  // страница аудитории: в рантайме приложений путь сопоставляется точно, поэтому id идёт в ?id=
  audiences: wrap('CmAudiencesPage', route => (route.params.id ? AudienceEdit : AudiencesIndex)),
}
export const widgets = { ContentDashboard: wrap('CmDashboardWidget', () => Dashboard), ContentHome: wrap('CmHome', () => Home) }
`)

await build({
  root: work,
  configFile: false,
  logLevel: 'info',
  resolve: {
    alias: [
      { find: /^vue$/, replacement: slash(path.join(work, 'vue.mjs')) },
      { find: '@apollo/client/core', replacement: slash(path.join(here, 'shim/apollo.mjs')) },
    ],
  },
  plugins: [
    // Имена GraphQL приложения свои (Cm*/cm*, см. build-api.mjs): правим запросы интерфейса до компиляции SFC.
    {
      name: 'app-gql-names',
      enforce: 'pre',
      transform(code, id) {
        if (!slash(id).toLowerCase().startsWith(slash(webApp).toLowerCase())) return null
        return code.replace(/\b(?:Content|content)[A-Z]\w*\b/g, m => gqlNames[m] ?? m)
      },
    },
    vue(),
    AutoImport({
      imports: ['vue', { [shimNuxt]: ['useI18n', 'useState', 'useToast', 'useConfirmDialog', 'definePageMeta', 'useSeoMeta', 'useHead', 'useGqlClient', 'useRoute', 'useRouter', 'useLocalePath', 'useAuthStore'] }],
      dirs: [slash(path.join(webApp, 'utils')), slash(path.join(webApp, 'composables'))],
      vueTemplate: true,
      dts: false,
    }),
    Components({
      dirs: [slash(path.join(webApp, 'components'))],
      directoryAsNamespace: true,
      resolvers: [name => (uiNames.includes(name) ? { name, from: shimComponents } : undefined)],
      dts: false,
    }),
  ],
  build: {
    outDir,
    emptyOutDir: true,
    minify: true,
    lib: { entry: slash(path.join(work, 'entry.mjs')), formats: ['es'], fileName: () => 'app.mjs' },
    rollupOptions: { output: { codeSplitting: false } },
  },
})
console.log('OK →', outDir)
