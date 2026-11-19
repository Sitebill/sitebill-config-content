# Architecture

## Place in the platform

Sitebill is a multi-tenant SaaS. A tenant's `app_type` selects a *configuration*; `content` selects this one. The core registers it in two places and otherwise does not know about it:

- backend: `config/configurations.php` (`provider` = `ContentConfigServiceProvider`), `composer.json` (PSR-4 `App\Configs\Content\` → `api/src`), `graphql/schema.graphql` (`#import`);
- frontend: Nuxt `extends` and the module registry (`registry.ts`), which receives `contentConfigModule` (menu, dashboard, member menu).

## Data model

All tables carry `tenant_id` (FK) and are filled through `BelongsToTenant`, which scopes queries by the current tenant.

| Table | Essentials |
|---|---|
| `content_projects` | name, topic, audience, tone, keywords, competitors, manager, colour |
| `content_platforms` | project, name, kind, url, formats, length limit, posts per week, API status |
| `content_publications` | project, optional `parent_id` (a version / adaptation), title, kind, rubric, sphere, status, priority, planned date/time, body, hashtags, link, assignee |
| `content_placements` | publication × platform: status, published at, url, comment |
| `content_materials` | library item: kind (book, diary, note, audience, idea, hashtags, case), title, body, state, sphere |
| `content_events` | «important» dates for the calendar |
| `content_members` | team: `user_id` → role |

### Statuses

`none · idea · draft · in_work · review · ready · published`. A publication's overall status is derived from its placements by `PublicationState` (single source of truth): all placements done → `published`, some → partly published, none → the manually set status.

## Roles and rights

The site admin is always `owner`; everyone else gets a role from `content_members`. `ContentAccess::ABILITIES` maps abilities to roles and is the only place the rights are defined.

| Ability | owner | editor | author | approver | viewer |
|---|:-:|:-:|:-:|:-:|:-:|
| `content.read` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `content.write` (publications, materials) | ✅ | ✅ | ✅ | | |
| `content.move` (status / date from board and calendar) | ✅ | ✅ | ✅ | ✅ | |
| `content.approve` (`ready`, `published`) | ✅ | ✅ | | ✅ | |
| `content.manage` (projects, channels, delete, demo) | ✅ | ✅ | | | |
| `content.team` | ✅ | | | | |

`Gate::define` is registered per ability by the provider; GraphQL fields use `@guard @can(ability: "content.x", model: "Tenant")`. An author who tries to set `ready` / `published` is rejected by `guardStatus` in the resolver.

### Team members without the site-admin role

The platform exposes a generic hook: a configuration registers a resolver in `App\Support\ConfigMemberRoles`, the user payload gets `config_role`, and the Nuxt module may define `memberMenuItems`. The core only sees a string and a menu; rights stay on the backend.

## Assistant

`PlanAgent` is a set of transparent rules (no model call): `review` returns codes plus a short meta string, the UI turns codes into localized text; `ideas`, `planProposal`, `adaptation` produce proposals that a human accepts — the assistant never publishes or edits on its own. AI features will plug into the same endpoints through the platform AI gateway.

## Frontend

State lives in `useContentWorkspace()` (`useState`): the whole workspace (hundreds of publications) is reloaded after each mutation, rules are computed on the server. Pages are wrapped in `Frame.vue` (project filter, loading / denied / empty-demo states, the publication card host).
