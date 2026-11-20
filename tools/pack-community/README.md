# pack-community — сборка приложения Community из конфига

Выпускает `rumantic.content-manager` ≥ 1.2.0 из этого же конфига, а не ведёт второй код (docs/roadmap/CONTENT_FACTORY.md §8).

```bash
node tools/pack-community/pack.mjs [--version 1.2.0]   # → .build/app/ (готовый каталог пакета)
# затем официальной проверкой и упаковкой из SDK:
cd tools/pack-community/.build/app && node <repo>/packages/app-sdk/js/bin/sitebill-app.mjs pack --out ../dist --php <php8.4>
```

Зависимости (vite, plugin-vue, unplugin-*, tailwind, jsdom) берутся из `apps/web/node_modules` монорепозитория.
Пакет без скриншотов ≈ 130 КБ — влезает в лимит портала (~1 МБ на запрос).

## Из чего собирается

| Шаг | Скрипт | Что делает |
|---|---|---|
| api | `build-api.mjs` | копирует `api/src` конфига в пространство имён `SitebillApps\Rumantic\ContentManager`, правилами заменяет связи с ядром (модели → `AppModel`, таблицы → `app_rumantic_content_manager_*`, ИИ-исключение, лимиты тарифа убраны), поверх кладёт `overlay/api/` |
| схема | там же | схема без команды; имена GraphQL приложения свои (`Content*`→`Cm*`, `content*`→`cm*`), способности Gate — `cm.*`: конфиг `content` всегда загружен в той же схеме, пересекаться нельзя. Карта имён — `.build/gql-names.json`, её применяет `build-web` |
| web | `build-web.mjs` | SFC → один ESM (`web/app.mjs`), `vue` — у хоста, авто-импорты Nuxt заменены слоем `shim/` поверх `host` |
| css | `build-css.mjs` | Tailwind (как у `apps/web`, тема Nuxt UI) просматривает SFC и отдаёт только слой utilities (≈26 КБ); `pack.mjs` вставляет его в `app.mjs` как `<style>` |
| локали | `pack.mjs` | `web/locales/*.json` (`content.*`) → плоские `locales/<loc>.json` |
| manifest | `pack.mjs` | id, `requires.host >=1.2`, shell (home `ContentHome`, дашборд `ContentDashboard`), меню и страницы |

`build-api.mjs` падает, если правило не сработало (исходник конфига изменился) или в коде остались `App\…`, MCP, лимиты, роли команды.

## Оверлеи (`overlay/api/`)

- `Support/AiGateway.php`, `AiFeature.php` — тот же `runFeature`, но поверх `Host::ai()` (ключ владельца/модель платформы, лимиты и журнал — у хоста; фича пишется как `app.rumantic.content-manager.<key>`);
- `Support/ContentAccess.php` — права = администратор сайта (`Host::users()->isAdmin`), способности `cm.*`;
- `GraphQL/Resolvers/TeamResolver.php` — только `cmMyRole`, команды в приложении нет;
- `Jobs/AdaptPublicationJob.php` — очередь внутри `Host::site()->runAs()`;
- `Models/User.php` — имя автора комментария; `ContentManagerServiceProvider.php` — Gate;
- `database/migrations/` — схема с нуля, без внешних ключей на таблицы хоста.

## Проверка

- `smoke.mjs` — jsdom: страницы и виджеты монтируются без ошибок;
- стенд Community без Docker: `python tools/devpanel/stand.py reset --profile blank`, затем `artisan apps install <пакет>` с окружением стенда (`EDITION=community`, `DB_DATABASE=community_wizard`, `APPS_DIR=.local-community-apps`);
  проверено 2026-11-20: главная, план, аудитории, демо-данные, `cmAiPrompt`, ошибка ИИ без ключа, фоновая адаптация (`AdaptPublicationJob`).

## Что осталось

- Требует Host API 1.2 (`Host::ai()`, `Host::users()`, `host.navigate`): Community должен выйти с ними раньше, чем приложение попадёт в каталог.
- Богатый редактор заменён обычным полем; команда, MCP и демо-профиль в приложение не входят.
- Предыдущие версии приложения (1.0.0/1.1.0) с таблицами `app_rumantic_content_manager_*` другой схемы несовместимы: миграция «с нуля», данных 1.1.0 в Community пока нет.
