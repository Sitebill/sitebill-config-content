<?php

namespace App\Configs\Content\GraphQL\Resolvers;

use App\Ai\AiGateway;
use App\Configs\Content\Models\Platform;
use App\Configs\Content\Models\Project;
use App\Configs\Content\Services\Ai\AdaptFeature;
use App\Configs\Content\Services\Ai\ContentFeature;
use App\Configs\Content\Services\Ai\DraftFeature;
use App\Configs\Content\Services\Ai\HashtagsFeature;
use App\Configs\Content\Services\Ai\RewriteFeature;
use App\Configs\Content\Services\Ai\TitlesFeature;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * ИИ-фичи контент-фабрики (docs/roadmap/CONTENT_FACTORY.md §6). Ничего не сохраняет: результат — предложение,
 * которое пользователь принимает в карточке сам. Источник модели, лимиты и журнал — в общем шлюзе ядра
 * (свой ключ тенанта → платформенная модель), второго шлюза здесь нет.
 */
final class AiResolver
{
    /** действие → класс фичи */
    private const FEATURES = [
        'draft' => DraftFeature::class,
        'rewrite' => RewriteFeature::class,
        'adapt' => AdaptFeature::class,
        'titles' => TitlesFeature::class,
        'hashtags' => HashtagsFeature::class,
    ];

    public function __construct(private readonly AiGateway $gateway) {}

    public function generate(mixed $root, array $args): array
    {
        $in = $args['input'];
        $action = (string) $in['action'];

        // Текстовые действия без текста бессмысленны: понятная ошибка вместо пустого запроса к модели.
        if (in_array($action, ['rewrite', 'adapt'], true) && trim((string) ($in['text'] ?? '')) === '') {
            throw ValidationException::withMessages(['input.text' => 'Нужен исходный текст.']);
        }

        $project = Project::query()->find($in['project_id']);
        if (! $project) {
            throw ValidationException::withMessages(['input.project_id' => 'Проект не найден.']);
        }
        $platform = ! empty($in['platform_id']) ? Platform::query()->where('project_id', $project->id)->find($in['platform_id']) : null;
        if (! empty($in['platform_id']) && ! $platform) {
            throw ValidationException::withMessages(['input.platform_id' => 'Площадка не найдена в этом проекте.']);
        }

        /** @var ContentFeature $feature */
        $feature = app(self::FEATURES[$action]);
        $input = [
            'project' => $project->only(['name', 'topic', 'audience', 'tone', 'keywords', 'competitors']),
            'platform' => $platform?->only(['name', 'kind', 'formats', 'audience', 'max_length']),
            'title' => $in['title'] ?? null, 'kind' => $in['kind'] ?? null, 'rubric' => $in['rubric'] ?? null,
            'text' => $in['text'] ?? null, 'instruction' => $in['instruction'] ?? null,
            'locale' => $in['locale'] ?? app('currentTenant')->default_locale ?? 'ru',
        ];

        $text = $this->gateway->runFeature(app('currentTenant'), Auth::user(), $feature, $input);

        return [
            'text' => $text,
            'items' => match ($action) {
                'titles' => TitlesFeature::lines($text, 8),
                'hashtags' => array_values(array_filter(explode(' ', $text))),
                default => [],
            },
        ];
    }
}
