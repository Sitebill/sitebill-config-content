<?php

namespace App\Configs\Content\Jobs;

use App\Ai\AiException;
use App\Ai\AiGateway;
use App\Configs\Content\Models\Placement;
use App\Configs\Content\Models\Platform;
use App\Configs\Content\Models\Project;
use App\Configs\Content\Models\Publication;
use App\Configs\Content\Services\Ai\AdaptFeature;
use App\Configs\Content\Services\AudiencePains;
use App\Configs\Content\Support\BatchProgress;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Адаптация одной публикации под одну площадку в очереди (пакет «на все площадки»). Результат — связанная версия
 * (`parent_id`) со статусом «черновик»: в план она попадает как черновик на проверку, а не как готовая публикация.
 * Очередь живёт без app('currentTenant'), поэтому тенант биндится явно и возвращается на место — иначе TenantScope
 * ничего не отфильтрует (главное правило 1).
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
        $tenant = Tenant::withoutGlobalScopes()->find($this->tenantId);
        if (! $tenant) {
            return;
        }

        $previous = app()->bound('currentTenant') ? app('currentTenant') : null;
        app()->instance('currentTenant', $tenant);
        try {
            $pub = Publication::query()->find($this->publicationId);
            $platform = Platform::query()->find($this->platformId);
            if (! $pub || ! $platform || (int) $platform->project_id !== (int) $pub->project_id) {
                throw new \RuntimeException('Публикация или площадка не найдены.');
            }

            $user = $this->userId ? User::withoutGlobalScopes()->find($this->userId) : null;
            $text = $gateway->runFeature($tenant, $user, app(AdaptFeature::class), [
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
            $child->tenant_id = $tenant->id;
            $child->save();
            Placement::create(['publication_id' => $child->id, 'platform_id' => $platform->id, 'status' => 'todo']);

            BatchProgress::record($tenant->id, $this->batchId, true, ['publication_id' => $child->id, 'platform_id' => $platform->id]);
        } catch (\Throwable $e) {
            // Тексты ИИ-ошибок безопасны для показа (шлюз не раскрывает ключи); остальное только в лог.
            if (! $e instanceof AiException) {
                report($e);
            }
            BatchProgress::record($tenant->id, $this->batchId, false, [
                'platform_id' => $this->platformId, 'error' => $e instanceof AiException ? $e->getMessage() : 'Не удалось адаптировать.',
            ]);
        } finally {
            $previous ? app()->instance('currentTenant', $previous) : app()->forgetInstance('currentTenant');
        }
    }
}
