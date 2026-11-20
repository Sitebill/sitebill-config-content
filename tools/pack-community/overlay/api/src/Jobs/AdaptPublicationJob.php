<?php

namespace SitebillApps\Rumantic\ContentManager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Sitebill\AppSdk\Exceptions\AiException;
use Sitebill\AppSdk\Host;
use SitebillApps\Rumantic\ContentManager\Models\Placement;
use SitebillApps\Rumantic\ContentManager\Models\Platform;
use SitebillApps\Rumantic\ContentManager\Models\Project;
use SitebillApps\Rumantic\ContentManager\Models\Publication;
use SitebillApps\Rumantic\ContentManager\Services\Ai\AdaptFeature;
use SitebillApps\Rumantic\ContentManager\Services\AudiencePains;
use SitebillApps\Rumantic\ContentManager\Support\AiGateway;
use SitebillApps\Rumantic\ContentManager\Support\BatchProgress;

/**
 * Адаптация одной публикации под одну площадку в очереди (пакет «на все площадки»). Результат — связанная версия
 * (`parent_id`) со статусом «черновик». Очередь живёт без текущего сайта, поэтому работа идёт внутри Host::site()->runAs().
 */
class AdaptPublicationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(
        private readonly int $tenantId,
        private readonly ?int $userId,
        private readonly int $publicationId,
        private readonly int $platformId,
        private readonly string $locale,
        private readonly string $batchId,
    ) {}

    public function handle(AiGateway $gateway): void
    {
        Host::site()->runAs($this->tenantId, fn () => $this->adapt($gateway));
    }

    private function adapt(AiGateway $gateway): void
    {
        try {
            $pub = Publication::query()->find($this->publicationId);
            $platform = Platform::query()->find($this->platformId);
            if (! $pub || ! $platform || (int) $platform->project_id !== (int) $pub->project_id) {
                throw new \RuntimeException('Публикация или площадка не найдены.');
            }

            $text = $gateway->runFeature(null, null, app(AdaptFeature::class), [
                'project' => Project::query()->find($pub->project_id)?->only(['name', 'topic', 'audience', 'tone', 'keywords', 'competitors']) ?? [],
                'platform' => $platform->only(['name', 'kind', 'formats', 'audience', 'max_length']),
                'title' => $pub->title, 'kind' => $pub->kind, 'rubric' => $pub->rubric, 'text' => $pub->body, 'locale' => $this->locale,
                'pains' => AudiencePains::forPrompt($pub->audience_id, (array) $pub->pain_ids),
            ]);

            $child = new Publication([
                'project_id' => $pub->project_id, 'parent_id' => $pub->id, 'title' => $pub->title, 'kind' => $pub->kind, 'rubric' => $pub->rubric,
                'sphere' => $pub->sphere, 'status' => 'draft', 'priority' => $pub->priority, 'planned_date' => $pub->planned_date,
                'planned_time' => $pub->planned_time, 'body' => $text, 'hashtags' => $pub->hashtags,
                'audience_id' => $pub->audience_id, 'pain_ids' => $pub->pain_ids,
            ]);
            $child->save();
            Placement::create(['publication_id' => $child->id, 'platform_id' => $platform->id, 'status' => 'todo']);

            BatchProgress::record($this->tenantId, $this->batchId, true, ['publication_id' => $child->id, 'platform_id' => $platform->id]);
        } catch (\Throwable $e) {
            // Тексты ИИ-ошибок безопасны для показа (шлюз не раскрывает ключи); остальное только в лог.
            if (! $e instanceof AiException) {
                report($e);
            }
            BatchProgress::record($this->tenantId, $this->batchId, false, [
                'platform_id' => $this->platformId, 'error' => $e instanceof AiException ? $e->getMessage() : 'Не удалось адаптировать.',
            ]);
        }
    }
}
