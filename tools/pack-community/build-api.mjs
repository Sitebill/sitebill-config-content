// Сборка api-части приложения Community из бэкенда конфига `content` (docs/roadmap/CONTENT_FACTORY.md §8).
// Исходник конфига не правится: здесь он копируется в пространство имён приложения, а то, что завязано на ядро
// (ИИ-шлюз, команда с ролями, MCP, лимиты тарифа, демо-профиль), заменяется оверлеями из overlay/api/.
//
// Запуск: node tools/pack-community/build-api.mjs [--out <каталог>]
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const here = path.dirname(fileURLToPath(import.meta.url))
const configRoot = path.resolve(here, '../..')
const arg = (name, def) => { const i = process.argv.indexOf('--' + name); return i > -1 ? process.argv[i + 1] : def }
const outDir = path.resolve(arg('out', path.join(here, '.build/api')))

const NS = 'SitebillApps\\Rumantic\\ContentManager'
const NS_GQL = NS.replaceAll('\\', '\\\\') // в SDL обратные слэши удвоены
const PREFIX = 'app_rumantic_content_manager_'
const TABLES = 'projects|platforms|publications|placements|materials|events|comments'

const SKIP = [/^Mcp\//, /^Demo\//, /^Models\/Member\.php$/, /^ContentConfigServiceProvider\.php$/]

const walk = (dir, base = dir) => fs.readdirSync(dir, { withFileTypes: true }).flatMap(e =>
  e.isDirectory() ? walk(path.join(dir, e.name), base) : [path.relative(base, path.join(dir, e.name)).replaceAll('\\', '/')])

// Правило должно сработать хотя бы раз во всём пакете, иначе исходник конфига изменился и правило устарело.
const hits = new Map()
function rule(name, re, to) {
  return { name, re, to }
}
function apply(text, rules) {
  for (const r of rules) {
    const next = text.replace(r.re, r.to)
    if (next !== text) hits.set(r.name, (hits.get(r.name) ?? 0) + 1)
    text = next
  }
  return text
}

const phpRules = [
  rule('ns', /App\\Configs\\Content/g, () => NS),
  rule('entitlements', /\n[ \t]*if \(\$isNew\) \{\n[ \t]*app\(\\App\\Entitlements\\TenantEntitlements::class\)[^\n]*\n[ \t]*\}/g, ''),
  rule('tenant-trait-use', /^use App\\Models\\Concerns\\BelongsToTenant;\n/m, ''),
  rule('tenant-trait', /^[ \t]*use BelongsToTenant;\n\n?/m, ''),
  rule('model-import', /^use Illuminate\\Database\\Eloquent\\Model;/m, 'use Sitebill\\AppSdk\\AppModel;'),
  rule('model-extends', /\bextends Model\b/, 'extends AppModel'),
  rule('tenant-import', /^use App\\Models\\Tenant;\n/m, ''),
  rule('tenant-type', /\bTenant \$/g, 'mixed $'),
  rule('user', /^use App\\Models\\User;/m, `use ${NS}\\Models\\User;`),
  rule('ai-exception', /^use App\\Ai\\AiException;/m, 'use Sitebill\\AppSdk\\Exceptions\\AiException;'),
  rule('ai-gateway', /^use App\\Ai\\AiGateway;/m, `use ${NS}\\Support\\AiGateway;`),
  rule('ai-feature', /^use App\\Ai\\Features\\AiFeature;/m, `use ${NS}\\Support\\AiFeature;`),
  // Способности Gate приложения свои (`cm.*`): конфиг `content` платформы определяет `content.*`, и они не должны пересекаться.
  rule('abilities', /'content\.(read|write|move|approve|manage|team)'/g, "'cm.$1'"),
  rule('tables', new RegExp(`'content_(${TABLES})\\b`, 'g'), `'${PREFIX}$1`),
]

const gqlRules = [
  rule('gql-ns', /App\\\\Configs\\\\Content\\\\/g, () => NS_GQL + '\\\\'),
  rule('gql-member-types', /(?:"[^\n]*"\n)?(?:type ContentMember|input ContentMemberInput) \{[\s\S]*?\n\}\n\n/g, ''),
  rule('gql-member-fields', /(?:    "[^\n]*"\n)?    (?:contentMembers|contentSaveMember|contentRemoveMember)\b[^\n]*\n(?:        [^\n]*\n)+\n?/g, ''),
]

fs.rmSync(outDir, { recursive: true, force: true })
const srcRoot = path.join(configRoot, 'api/src')
for (const rel of walk(srcRoot)) {
  if (SKIP.some(re => re.test(rel))) continue
  const dest = path.join(outDir, 'src', rel)
  fs.mkdirSync(path.dirname(dest), { recursive: true })
  fs.writeFileSync(dest, apply(fs.readFileSync(path.join(srcRoot, rel), 'utf8').replaceAll('\r\n', '\n'), phpRules))
}

// Оверлеи ложатся поверх: заменяют исходные файлы и добавляют новые.
const overlay = path.join(here, 'overlay/api')
for (const rel of walk(overlay)) {
  const dest = path.join(outDir, rel)
  fs.mkdirSync(path.dirname(dest), { recursive: true })
  fs.copyFileSync(path.join(overlay, rel), dest)
}

// GraphQL-схема.
const gqlDest = path.join(outDir, 'graphql/schema.graphql')
fs.mkdirSync(path.dirname(gqlDest), { recursive: true })
let schema = apply(fs.readFileSync(path.join(configRoot, 'api/graphql/content.graphql'), 'utf8').replaceAll('\r\n', '\n'), gqlRules)
schema = schema.replace(/"content\.(read|write|move|approve|manage|team)"/g, '"cm.$1"')
// Конфиг `content` всегда загружен в той же схеме (платформа и образ Community), поэтому имена приложения свои:
// Content* → Cm*, content* → cm*. Строки с классами резолверов (после обратного слэша) не трогаем.
const names = {}
schema = schema.replace(/(?<![\\\w])(Content|content)([A-Z]\w*)\b/g, (m, head, tail) => (names[m] = (head === 'Content' ? 'Cm' : 'cm') + tail))
fs.writeFileSync(gqlDest, schema)
// build-web переименовывает те же идентификаторы в запросах интерфейса.
fs.writeFileSync(path.join(path.dirname(outDir), 'gql-names.json'), JSON.stringify(names, null, 2))

// Проверки: каждое правило сработало, ядро в коде не осталось.
const expected = [...phpRules, ...gqlRules].map(r => r.name)
const missed = expected.filter(n => !hits.has(n))
const leftovers = []
for (const rel of walk(outDir)) {
  const text = fs.readFileSync(path.join(outDir, rel), 'utf8')
  if (/\bApp\\(?!Configs)|currentTenant.*app_type|\bTenantEntitlements\b|content_members|ConfigMemberRoles|McpToolRegistry|DemoProfile/.test(text.replace(/\/\/[^\n]*|\/\*[\s\S]*?\*\//g, ''))) leftovers.push(rel)
  if (/App\\\\Configs|ContentMember\b/.test(text)) leftovers.push(rel)
}
if (missed.length || leftovers.length) {
  console.error('build-api: проблемы\n  правила не сработали: ' + (missed.join(', ') || '—') + '\n  остатки ядра: ' + ([...new Set(leftovers)].join(', ') || '—'))
  process.exit(1)
}
console.log(`api собран: ${outDir}\n  файлов: ${walk(outDir).length}`)
