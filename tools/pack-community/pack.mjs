// Сборка пакета .sbapp приложения rumantic.content-manager из конфигурации `content` (docs/roadmap/CONTENT_FACTORY.md §8).
// Шаги: web (SFC → ESM), css (utilities под SFC), api (бэкенд конфига + оверлеи), локали (плоские ключи), manifest, zip.
// Скриншотов в пакете нет: портал принимает запрос до ~1 МБ.
//
// Запуск: node tools/pack-community/pack.mjs [--version 1.2.0] [--out <каталог>]
import { execFileSync } from 'node:child_process'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const here = path.dirname(fileURLToPath(import.meta.url))
const configRoot = path.resolve(here, '../..')
const arg = (name, def) => { const i = process.argv.indexOf('--' + name); return i > -1 ? process.argv[i + 1] : def }
const version = arg('version', '1.2.0')
const outRoot = path.resolve(arg('out', path.join(here, '.build')))
const APP_ID = 'rumantic.content-manager'
const pkgDir = path.join(outRoot, 'app')
const run = script => execFileSync(process.execPath, [path.join(here, script), '--out', path.join(outRoot, script === 'build-css.mjs' ? 'app.css' : script === 'build-web.mjs' ? 'web' : 'api')], { stdio: 'inherit' })

run('build-api.mjs') // первым: даёт gql-names.json для web
run('build-web.mjs')
run('build-css.mjs')

fs.rmSync(pkgDir, { recursive: true, force: true })
const copyDir = (from, to) => fs.cpSync(from, to, { recursive: true })

// ---- api
copyDir(path.join(outRoot, 'api'), path.join(pkgDir, 'api'))

// ---- web: бандл + стили, вставляемые при загрузке модуля (один раз на страницу)
const css = fs.readFileSync(path.join(outRoot, 'app.css'), 'utf8')
let js = fs.readFileSync(path.join(outRoot, 'web/app.mjs'), 'utf8')
js += `\n;(() => {\n  if (typeof document === 'undefined' || document.getElementById('app-${APP_ID}-css')) return\n  const s = document.createElement('style')\n  s.id = 'app-${APP_ID}-css'\n  s.textContent = ${JSON.stringify(css)}\n  document.head.appendChild(s)\n})()\n`
fs.mkdirSync(path.join(pkgDir, 'web'), { recursive: true })
fs.writeFileSync(path.join(pkgDir, 'web/app.mjs'), js)

// ---- локали: в конфиге вложенные `content.*`, приложение ищет по полному ключу
const flat = (o, p = '') => Object.entries(o).flatMap(([k, v]) => v && typeof v === 'object' ? flat(v, p + k + '.') :[[p + k, v]])
fs.mkdirSync(path.join(pkgDir, 'locales'), { recursive: true })
for (const loc of ['ru', 'en', 'sr', 'tg']) {
  const src = JSON.parse(fs.readFileSync(path.join(configRoot, `web/locales/${loc}.json`), 'utf8'))
  fs.writeFileSync(path.join(pkgDir, `locales/${loc}.json`), JSON.stringify(Object.fromEntries(flat(src)), null, 2) + '\n')
}

// ---- manifest
const L = (ru, en, sr, tg) => ({ ru, en, sr, tg })
const menu = [
  [L('Обзор', 'Dashboard', 'Pregled', 'Шарҳ'), 'i-lucide-layout-dashboard', ''],
  [L('Контент-план', 'Content plan', 'Plan sadržaja', 'Нақшаи мундариҷа'), 'i-lucide-list-checks', 'plan'],
  [L('Календарь', 'Calendar', 'Kalendar', 'Тақвим'), 'i-lucide-calendar-days', 'calendar'],
  [L('Библиотека', 'Library', 'Biblioteka', 'Китобхона'), 'i-lucide-library', 'library'],
  [L('Аудитории и боли', 'Audiences & pains', 'Publika i bolovi', 'Аудиторияҳо ва дардҳо'), 'i-lucide-users', 'audiences'],
  [L('Проекты и площадки', 'Projects & channels', 'Projekti i kanali', 'Лоиҳаҳо ва майдончаҳо'), 'i-lucide-share-2', 'projects'],
  [L('ИИ-ассистент', 'AI assistant', 'AI asistent', 'Ёрдамчии AI'), 'i-lucide-sparkles', 'assistant'],
]
const manifest = {
  id: APP_ID,
  name: L('Контент-менеджер', 'Content Manager', 'Menadžer sadržaja', 'Идораи мундариҷа'),
  version,
  vendor: { name: 'Lumen', url: 'https://sitebillcms.com/' },
  license: 'MIT',
  repository: 'https://github.com/Sitebill/sitebill-config-content',
  description: {
    ru: 'Контент-фабрика: проекты и площадки, контент-план, календарь, библиотека, аудитории с болями, согласование, пакетная адаптация под площадки и ИИ-ассистент (свой ключ или модель платформы). Приложение целиком заменяет сайт.',
    en: 'Content factory: projects and channels, content plan, calendar, library, audiences with pains, approval, batch adaptation per channel and an AI assistant (your own key or the platform model). The app takes over the whole site.',
  },
  sdk: { php: '1', web: '1' },
  requires: { host: '>=1.2' },
  provider: 'SitebillApps\\Rumantic\\ContentManager\\ContentManagerServiceProvider',
  permissions: [],
  profiles: ['blank'],
  shell: {
    mode: 'owner',
    home: 'ContentHome',
    adminDashboard: 'ContentDashboard',
    adminMenu: 'replace',
    accountMenu: 'replace',
    publicNav: [{ label: L('Обзор', 'Overview', 'Pregled', 'Шарҳ'), to: '/' }],
  },
  admin: {
    menu: menu.map(([label, icon, p]) => ({ label, icon, path: p })),
    pages: menu.map(m => m[2]),
    widgets: { 'admin.dashboard': 'ContentDashboard' },
  },
  web: { admin: 'web/app.mjs', site: 'web/app.mjs' },
}
fs.writeFileSync(path.join(pkgDir, 'manifest.json'), JSON.stringify(manifest, null, 2) + '\n')

// ---- прочее
fs.writeFileSync(path.join(pkgDir, 'composer.json'), JSON.stringify({
  name: 'rumantic/sitebill-app-content-manager',
  description: 'Content factory: an installable Sitebill app (projects, channels, plan, calendar, library, audiences, AI).',
  type: 'project', license: 'MIT', require: { php: '>=8.4' },
  autoload: { 'psr-4': { 'SitebillApps\\Rumantic\\ContentManager\\': 'api/src/' } },
}, null, 4) + '\n')
const license = path.join(configRoot, 'LICENSE')
fs.writeFileSync(path.join(pkgDir, 'LICENSE'), fs.existsSync(license) ? fs.readFileSync(license) : 'MIT License\n')
fs.writeFileSync(path.join(pkgDir, 'README.md'), `# Content Manager (rumantic.content-manager ${version})\n\nСобрано из конфигурации \`content\` (Sitebill/sitebill-config-content) инструментом tools/pack-community.\nТребует Host API >= 1.2 (Host::ai(), Host::users(), host.navigate). Права: всё делает администратор сайта.\n`)

console.log(`пакет собран: ${pkgDir}`)
