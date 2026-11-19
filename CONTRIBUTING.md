# Contributing / Участие

Thanks for helping! / Спасибо, что помогаете!

- **Bugs and ideas** — open an issue; for a bug, say what you did, what you expected and what happened.
- **Translations** — `web/locales/{ru,en,sr,tg}.json`. Keys live under `content.*` and must exist in **all four** files. Serbian and Tajik are machine translations: native-speaker fixes are the most valuable contribution. Do not put `<tag>`-like text into strings (vue-i18n swallows it).
- **Code** — this is a Sitebill *configuration*, it runs inside the platform:
  - backend: `api/` (Laravel + Lighthouse). Every table has `tenant_id`, every model uses `BelongsToTenant`, every GraphQL field is under `@guard @can(ability: "content.*", model: "Tenant")`. Rights are checked on the backend, never only in the UI.
  - frontend: `web/` (Nuxt layer, Nuxt UI). Every UI string goes through `t('content.…')`.
  - keep the style of the neighbouring code; comments explain *why*.
- **Tests** — `api/tests/Feature` run inside the platform's Pest suite (`artisan test --testsuite=Content`).

By contributing you agree that your work is released under the [MIT license](LICENSE).
