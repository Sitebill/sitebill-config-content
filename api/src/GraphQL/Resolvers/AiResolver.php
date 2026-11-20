<?php

namespace App\Configs\Content\GraphQL\Resolvers;

use App\Ai\AiGateway;
use App\Configs\Content\Models\Event;
use App\Configs\Content\Models\Platform;
use App\Configs\Content\Models\Project;
use App\Configs\Content\Models\Publication;
use App\Configs\Content\Services\AudiencePains;
use App\Configs\Content\Services\Ai\AdaptFeature;
use App\Configs\Content\Services\Ai\ContentFeature;
use App\Configs\Content\Services\Ai\DraftFeature;
use App\Configs\Content\Services\Ai\HashtagsFeature;
use App\Configs\Content\Services\Ai\IdeasFeature;
use App\Configs\Content\Services\Ai\PeriodPlanFeature;
use App\Configs\Content\Services\Ai\RewriteFeature;
use App\Configs\Content\Services\Ai\TitlesFeature;
use Carbon\CarbonImmutable;
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
            'pains' => AudiencePains::forPrompt($in['audience_id'] ?? null, $in['pain_ids'] ?? []),
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

    /** Идеи публикаций для проекта: предложения без дат и площадок. */
    public function ideas(mixed $root, array $args): array
    {
        $project = $this->project((int) $args['project_id']);
        $pubs = Publication::query()->where('project_id', $project->id)->orderByDesc('id')->limit(60)->get(['title', 'rubric']);

        $raw = $this->gateway->runFeature(app('currentTenant'), Auth::user(), app(IdeasFeature::class), [
            'project' => $project->only(['name', 'topic', 'audience', 'tone', 'keywords', 'competitors']),
            'count' => $args['count'] ?? 6,
            'existing_titles' => $pubs->pluck('title')->all(),
            'rubrics' => $pubs->pluck('rubric')->filter()->unique()->values()->all(),
            'locale' => $args['locale'] ?? app('currentTenant')->default_locale ?? 'ru',
        ]);

        return array_map(fn (array $r) => $this->proposal($r), json_decode($raw, true) ?: []);
    }

    /** План на период: предложения с датами и площадками (учитывает частоту по площадкам, занятые дни и важные даты). */
    public function plan(mixed $root, array $args): array
    {
        $project = $this->project((int) $args['project_id']);
        $from = CarbonImmutable::parse($args['today'] ?? 'today')->startOfDay();
        $to = $from->addDays(max(3, min(60, (int) ($args['days'] ?? 14))) - 1);

        $platforms = Platform::query()->where('project_id', $project->id)->get(['id', 'name', 'kind', 'per_week'])->map->toArray()->all();
        $busy = Publication::query()->where('project_id', $project->id)->whereBetween('planned_date', [$from->toDateString(), $to->toDateString()])
            ->get(['title', 'planned_date'])->map(fn ($p) => ['date' => substr((string) $p->planned_date, 0, 10), 'title' => $p->title])->all();
        $events = Event::query()->whereBetween('event_date', [$from->toDateString(), $to->toDateString()])
            ->get(['title', 'event_date'])->map(fn ($e) => ['date' => substr((string) $e->event_date, 0, 10), 'title' => $e->title])->all();

        $raw = $this->gateway->runFeature(app('currentTenant'), Auth::user(), app(PeriodPlanFeature::class), [
            'project' => $project->only(['name', 'topic', 'audience', 'tone', 'keywords', 'competitors']),
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'platforms' => $platforms, 'busy' => $busy, 'events' => $events,
            'locale' => $args['locale'] ?? app('currentTenant')->default_locale ?? 'ru',
        ]);

        return array_map(fn (array $r) => $this->proposal($r), json_decode($raw, true) ?: []);
    }

    private function project(int $id): Project
    {
        return Project::query()->find($id)
            ?? throw ValidationException::withMessages(['project_id' => 'Проект не найден.']);
    }

    /** Предложение в формате ContentProposal (как у ассистента на правилах): принимается тем же кодом интерфейса. */
    private function proposal(array $r): array
    {
        return [
            'title' => $r['title'], 'kind' => $r['kind'], 'rubric' => $r['rubric'] ?? null, 'body' => $r['hook'] ?: null, 'hashtags' => null,
            'planned_date' => $r['date'] ?? null, 'planned_time' => $r['time'] ?? null,
            'platform_ids' => ! empty($r['platform_id']) ? [(int) $r['platform_id']] : [],
        ];
    }
}
