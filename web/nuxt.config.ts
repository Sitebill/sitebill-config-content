// Nuxt Layer конфигурации `content` (контент-фабрика). Подключается через `extends` в apps/web/nuxt.config.ts.
// Локали перечисляются явно и мёржатся с корневым списком по `code`; langDir у layer'а свой — дублируем
// значение корневого конфига (см. configs/appointments/web/nuxt.config.ts).
export default defineNuxtConfig({
  i18n: {
    langDir: '../locales/',
    locales: [
      { code: 'ru', file: 'ru.json' },
      { code: 'en', file: 'en.json' },
      { code: 'sr', file: 'sr.json' },
      { code: 'tg', file: 'tg.json' },
    ],
  },
})
