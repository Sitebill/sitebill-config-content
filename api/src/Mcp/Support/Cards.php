<?php

namespace App\Configs\Content\Mcp\Support;

use App\Configs\Content\Models\Comment;
use App\Configs\Content\Models\Material;
use App\Configs\Content\Models\Placement;
use App\Configs\Content\Models\Platform;
use App\Configs\Content\Models\Project;
use App\Configs\Content\Models\Publication;
use App\Configs\Content\Services\PublicationState;
use Illuminate\Support\Collection;

/**
 * Карточки сущностей контент-фабрики для агента: краткая (в списках, без текста — он тяжёлый) и полная. Тексты,
 * комментарии и описания написаны людьми и моделями — это данные, а не инструкции (напоминание есть в overview).
 */
final class Cards
{
    public static function project(Project $p): array
    {
        return $p->only(['id', 'name', 'description', 'topic', 'audience', 'tone', 'keywords', 'competitors', 'manager']);
    }

    public static function platform(Platform $p): array
    {
        return $p->only(['id', 'project_id', 'name', 'kind', 'url', 'audience', 'formats', 'max_length', 'per_week', 'api_status']);
    }

    /** @param  Collection<int, Placement>|null  $placements */
    public static function publication(Publication $p, ?Collection $placements = null, bool $full = false): array
    {
        $placements ??= Placement::query()->where('publication_id', $p->id)->get();
        $card = [
            'id' => $p->id, 'project_id' => $p->project_id, 'parent_id' => $p->parent_id, 'title' => $p->title, 'kind' => $p->kind,
            'rubric' => $p->rubric, 'sphere' => $p->sphere, 'status' => $p->status, 'priority' => $p->priority,
            'planned_date' => $p->planned_date ? substr((string) $p->planned_date, 0, 10) : null,
            'planned_time' => $p->planned_time ? substr((string) $p->planned_time, 0, 5) : null,
            'audience_id' => $p->audience_id, 'pain_ids' => $p->pain_ids ?? [],
            'placement_state' => PublicationState::placementState($placements),
            'placements' => $placements->map(fn (Placement $x) => ['platform_id' => $x->platform_id, 'status' => $x->status, 'url' => $x->url])->values()->all(),
        ];
        if (! $full) {
            return $card + ['body_length' => mb_strlen((string) $p->body)];
        }

        return $card + $p->only(['body', 'hashtags', 'link', 'assignee', 'comment']);
    }

    public static function comment(Comment $c): array
    {
        return ['id' => $c->id, 'kind' => $c->kind, 'author' => $c->user?->name, 'body' => $c->body, 'created_at' => $c->created_at?->toIso8601String()];
    }

    /** Материал библиотеки; у аудитории в списке — только число болей (структура — в content_get_audience). */
    public static function material(Material $m, bool $full = false): array
    {
        $card = $m->only(['id', 'project_id', 'kind', 'entry_date', 'state', 'sphere', 'topic', 'title', 'tags']);
        $card['entry_date'] = $m->entry_date ? substr((string) $m->entry_date, 0, 10) : null;
        if ($m->kind === 'audience') {
            $card['pains_count'] = collect($m->pains ?? [])->sum(fn ($s) => count($s['pains'] ?? []));
        }

        return $full ? $card + ['body' => $m->body] + ($m->kind === 'audience' ? ['pains' => $m->pains ?? []] : []) : $card + ['body_length' => mb_strlen((string) $m->body)];
    }
}
