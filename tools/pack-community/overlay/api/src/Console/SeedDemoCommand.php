<?php

namespace SitebillApps\Rumantic\ContentManager\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Sitebill\AppSdk\Host;
use SitebillApps\Rumantic\ContentManager\Models\Project;
use SitebillApps\Rumantic\ContentManager\Models\Publication;
use SitebillApps\Rumantic\ContentManager\Services\DemoData;

/**
 * Пример данных из консоли — то же, что кнопка «Загрузить пример данных» (мутация cmSeedDemo), для установщика
 * (`install.sh --demo`) и ИИ-агентов: после установки они сразу показывают пользователю наполненный сайт.
 * В консоли сайт не привязан, поэтому берём единственный сайт Community (или --tenant=ID) и работаем через Host::site()->runAs.
 */
final class SeedDemoCommand extends Command
{
    protected $signature = 'cm:seed-demo {--tenant= : id сайта (по умолчанию — первый)} {--locale= : ru|en|sr|tg, по умолчанию язык сайта} {--json : один JSON в ответ}';

    protected $description = 'Контент-менеджер: загрузить пример данных (два проекта с планом вокруг сегодняшнего дня)';

    public function handle(): int
    {
        $tenantId = (int) ($this->option('tenant') ?: DB::table('tenants')->orderBy('id')->value('id'));
        if ($tenantId === 0) {
            return $this->finish(['status' => 'error', 'message' => 'Сайт не найден'], self::FAILURE);
        }

        $result = Host::site()->runAs($tenantId, function () {
            if (Project::query()->exists() || Publication::query()->exists()) {
                return ['status' => 'skipped', 'message' => 'В приложении уже есть данные, пример не загружен'];
            }
            $locale = (string) ($this->option('locale') ?: Host::site()->current()?->locale ?: 'en');
            (new DemoData())->seed(str_starts_with($locale, 'ru'), CarbonImmutable::today());

            return ['status' => 'ok', 'projects' => Project::query()->count(), 'publications' => Publication::query()->count()];
        });

        return $this->finish($result, self::SUCCESS);
    }

    private function finish(array $data, int $code): int
    {
        $this->line($this->option('json') ? json_encode($data, JSON_UNESCAPED_UNICODE) : ($data['message'] ?? 'Пример данных загружен: проектов '.($data['projects'] ?? 0).', публикаций '.($data['publications'] ?? 0)));

        return $code;
    }
}
