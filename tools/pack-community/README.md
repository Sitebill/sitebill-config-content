# pack-community — прототип сборки приложения Community из конфига

Цель (docs/roadmap/CONTENT_FACTORY.md §8): выпускать `rumantic.content-manager` ≥ 1.2.0 из этого же конфига, а не вести второй код.

## Что сделано (web)

`build-web.mjs` компилирует SFC конфига в один ESM-бандл (`app.mjs`, ≈160 КБ, ≈36 КБ gzip), который рантайм приложений
грузит без сборки (общий Vue хоста, контракт `host`):

- Vite + `@vitejs/plugin-vue`, `vue` подменяется на `globalThis.__SB_HOST__.vue`;
- авто-импорты Nuxt (`useI18n`, `useState`, `useToast`, `useGqlClient`, `useRoute`…) заменены слоем `shim/nuxt.mjs` поверх `host`;
- `gql` из Apollo — строки (`shim/apollo.mjs`), `host.gql` разбирает их сам;
- компоненты Nuxt UI: берутся у хоста (`host.components`), недостающие (`UFormField`, `NuxtLink`, `UEditor` → textarea) — `shim/components.mjs`;
- страницы с динамическим путём: `audiences/[id]` → `audiences?id=` (рантайм сопоставляет страницы по точному пути), ссылки `/admin/content/…` переводятся в `/admin/x/<id>/…`;
- `smoke.mjs` монтирует все страницы и виджеты в jsdom с поддельным `host` и проверяет конструктор болей.

```bash
node tools/pack-community/build-web.mjs     # → tools/pack-community/.build/web/app.mjs
node tools/pack-community/smoke.mjs         # jsdom: страницы и виджеты монтируются без ошибок
```

Зависимости берутся из `apps/web/node_modules` монорепозитория (vite 8, plugin-vue, unplugin-auto-import, unplugin-vue-components, jsdom).

## Чего ещё нет (до выпуска пакета)

1. **Backend.** `api/src` завязан на ядро: `AiGateway` (ИИ), `Tenant`, роли команды (`ContentAccess`, `ConfigMemberRoles`), очереди, MCP-реестр, лимиты тарифа. Host API приложений этого не даёт. Нужно решение: какие части входят в приложение (без ИИ и команды?) или расширение Host API (AI-шлюз).
2. **Схема GraphQL и `@can`**: способности `content.*` определяет провайдер конфига; в приложении нужны свои.
3. **Миграции и данные 1.1.0 → 1.2.0**: таблицы приложения `app_rumantic_content_manager_*` отличаются от `content_*`; нужна эволюционная миграция, а не `create`.
4. **CSS.** Утилиты Tailwind из SFC попадут в стили сайта только если они уже есть в сборке хоста; нужен собственный CSS приложения (компиляция Tailwind при упаковке) — не проверено в браузере.
5. **Локали**: `content.*` → плоские файлы `locales/<loc>.json` пакета (не сделано).
6. **Проверка в реальном хосте** (`artisan apps install`, браузер); богатый редактор заменён обычным полем.
7. **Core**: в `useAppHost` добавлен `navigate` (переход через роутер) — нужен для ссылок между страницами приложения; релиз Community должен идти с ним (`requires.host`).
