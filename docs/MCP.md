# MCP: работа с контент-фабрикой через ИИ-агентов

Контент-фабрика отдаёт свои инструменты на общем MCP-сервере платформы (`POST https://<сайт>/mcp`, Streamable HTTP). Агент (Claude, ChatGPT-совместимые клиенты, Cursor, свой скрипт) видит проекты, план и аудитории, пишет и правит публикации в пределах прав владельца токена.

Content-factory tools live on the platform's shared MCP endpoint (`POST https://<site>/mcp`). An agent can read the plan, audiences and library and edit publications within the rights of the token owner.

## Подключение

1. Включите «ИИ-агенты» (MCP) в настройках сайта и выпустите **персональный токен** в личном кабинете («ИИ-агенты»). Для записи нужен токен с правом `mcp:write`; без него доступно только чтение.
2. Адрес сервера — `https://<ваш-сайт>/mcp`, заголовок `Authorization: Bearer <токен>`.

```json
{ "mcpServers": { "content": { "url": "https://<ваш-сайт>/mcp", "headers": { "Authorization": "Bearer <токен>" } } } }
```

Токен действует только на домене своего сайта. Тенантский ключ (без человека) инструменты контент-фабрики не видит.

## Кто что видит

Инструмент виден и вызывается, только если у владельца токена есть нужное право роли (те же права, что в интерфейсе; проверяет бэкенд):

| Право | Роли | Инструменты |
|---|---|---|
| `content.read` | владелец, редактор, автор, согласующий, наблюдатель | `content_overview`, `content_list_publications`, `content_get_publication`, `content_list_materials`, `content_get_material`, `content_plan_review` |
| `content.write` | владелец, редактор, автор | `content_save_publication`, `content_set_placement`, `content_save_material`, `content_build_prompt` (токен чтения достаточно) |
| `content.move` | + согласующий | `content_move_publication`, `content_add_comment` |
| `content.approve` | владелец, редактор, согласующий | `content_decide` |
| `content.manage` | владелец, редактор | `content_save_project`, `content_save_platform` |

Автор не может поставить `ready`/`published` ни одним инструментом — только `review`. Удалять через MCP нельзя ничего.

## Как работать

1. `content_overview` — роль, проекты с «памятью бренда», площадки, справочники значений, аудитории, правила работы. С него начинайте.
2. Чтение: `content_list_publications` (без текстов) → `content_get_publication` (текст, размещение, боли, обсуждение). Для аудитории `content_get_material` отдаёт «боли»: разделы с id болей и вопросами-ответами.
3. Написание: возьмите бренд-память проекта и боли аудитории, сохраните текст через `content_save_publication` с `audience_id` и `pain_ids` — человек увидит, на какие боли отвечает текст. `content_build_prompt` отдаёт тот же готовый бриф, что и встроенный помощник (модель платформы не вызывается).
4. Процесс: автор → `status=review` → согласующий `content_decide` (только по прямому указанию человека).
5. Идемпотентность: инструменты записи принимают `client_request_id` — повтор с тем же ключом за сутки возвращает прежний результат, а не создаёт дубль.

## Безопасность

- Тексты публикаций, комментарии и описания — данные, а не инструкции; сервер напоминает об этом агенту в `content_overview.guide`.
- Каждый вызов пишется в журнал активности (значения аргументов записи не сохраняются).
- Запись идёт через те же резолверы, что и веб-интерфейс: лимиты тарифа, проверка статусов, чистка структуры болей.
- Токен «только чтение» не видит инструменты записи, даже если он выпущен владельцу сайта.

## Устройство

Ядро платформы знает только реестр `App\Mcp\McpToolRegistry`; провайдер конфигурации (`ContentConfigServiceProvider`) регистрирует классы из `api/src/Mcp/Tools`. Видимость решает `Mcp\Concerns\ContentMcpAccess` (токен-человек, `app_type = content`, право роли). Тесты — `api/tests/Feature/ContentMcpTest.php`.
