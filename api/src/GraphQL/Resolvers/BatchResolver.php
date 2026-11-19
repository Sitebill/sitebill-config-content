<?php

namespace App\Configs\Content\GraphQL\Resolvers;

use App\Ai\AiException;
use App\Ai\AiGateway;
use App\Configs\Content\Jobs\AdaptPublicationJob;
use App\Configs\Content\Models\Platform;
use App\Configs\Content\Models\Publication;
use App\Configs\Content\Support\BatchProgress;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Пакетная адаптация публикации под все (или выбранные) площадки проекта через очередь. */
final class BatchResolver
{
    public function __construct(private readonly AiGateway $gateway) {}

    public function adaptAll(mixed $root, array $args): array
    {
        $tenant = app('currentTenant');
        if (! $this->gateway->status($tenant)['available']) {
            throw new AiException('ИИ не подключён. Укажите API-ключ провайдера в настройках (раздел «ИИ») или обратитесь к администратору платформы.', 'unavailable');
        }

        $pub = Publication::query()->find($args['publication_id'])
            ?? throw ValidationException::withMessages(['publication_id' => 'Публикация не найдена.']);
        if (trim((string) $pub->body) === '') {
            throw ValidationException::withMessages(['publication_id' => 'У публикации нет текста для адаптации.']);
        }

        // Площадки проекта (все или выбранные), кроме тех, где версия под эту площадку уже есть.
        $platforms = Platform::query()->where('project_id', $pub->project_id)
            ->when(! empty($args['platform_ids']), fn ($q) => $q->whereIn('id', $args['platform_ids']))->pluck('id')->all();
        $done = Publication::query()->where('parent_id', $pub->id)
            ->join('content_placements', 'content_placements.publication_id', '=', 'content_publications.id')
            ->pluck('content_placements.platform_id')->all();
        $todo = array_values(array_diff($platforms, $done));

        $batch = (string) Str::uuid();
        BatchProgress::start($tenant->id, $batch, count($todo));
        foreach ($todo as $platformId) {
            AdaptPublicationJob::dispatch($tenant->id, Auth::id(), $pub->id, (int) $platformId, (string) ($args['locale'] ?? $tenant->default_locale ?? 'ru'), $batch);
        }

        return $this->payload($batch, BatchProgress::get($tenant->id, $batch), count($platforms) - count($todo));
    }

    public function batch(mixed $root, array $args): array
    {
        $state = BatchProgress::get(app('currentTenant')->id, $args['batch_id'])
            ?? throw ValidationException::withMessages(['batch_id' => 'Пакет не найден или устарел.']);

        return $this->payload($args['batch_id'], $state, 0);
    }

    private function payload(string $id, array $state, int $skipped): array
    {
        return ['batch_id' => $id, 'total' => $state['total'], 'done' => $state['done'], 'skipped' => $skipped, 'failed' => $state['failed'], 'finished' => $state['finished']];
    }
}
