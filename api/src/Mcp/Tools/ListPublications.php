<?php

namespace App\Configs\Content\Mcp\Tools;

use App\Configs\Content\Mcp\Support\Cards;
use App\Configs\Content\Models\Placement;
use App\Configs\Content\Models\Publication;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('content_list_publications')]
#[Description('Публикации контент-плана без текста (только длина body_length): фильтры по проекту, статусу, периоду плановой даты, аудитории, поиску в заголовке/рубрике, «только версии этой публикации» (parent_id). Сортировка по плановой дате. Текст — через content_get_publication.')]
#[IsReadOnly]
#[IsIdempotent]
class ListPublications extends ContentTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->description('ID проекта (из content_overview).'),
            'status' => $schema->string()->enum(['none', 'idea', 'draft', 'in_work', 'review', 'ready', 'published'])->description('Статус.'),
            'open_only' => $schema->boolean()->description('true — только незавершённые (idea, draft, in_work, review, ready).'),
            'from' => $schema->string()->description('YYYY-MM-DD: плановая дата не раньше.'),
            'to' => $schema->string()->description('YYYY-MM-DD: плановая дата не позже.'),
            'audience_id' => $schema->integer()->description('Только публикации для этой целевой аудитории.'),
            'parent_id' => $schema->integer()->description('Только версии/адаптации этой публикации.'),
            'search' => $schema->string()->description('Часть заголовка или рубрики.'),
            'page' => $schema->integer()->description('Страница, с 1.'),
            'per_page' => $schema->integer()->description('1–50, по умолчанию 20.'),
        ];
    }

    protected function execute(Request $request, Tenant $tenant, ?User $user): array|Response
    {
        $d = $this->input($request, [
            'project_id' => ['nullable', 'integer'], 'status' => ['nullable', 'in:none,idea,draft,in_work,review,ready,published'],
            'open_only' => ['nullable', 'boolean'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d'],
            'audience_id' => ['nullable', 'integer'], 'parent_id' => ['nullable', 'integer'], 'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:500'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $page = (int) ($d['page'] ?? 1);
        $perPage = (int) ($d['per_page'] ?? 20);

        $q = Publication::query()
            ->when(! empty($d['project_id']), fn ($q) => $q->where('project_id', $d['project_id']))
            ->when(! empty($d['status']), fn ($q) => $q->where('status', $d['status']))
            ->when(! empty($d['open_only']), fn ($q) => $q->whereIn('status', ['idea', 'draft', 'in_work', 'review', 'ready']))
            ->when(! empty($d['from']), fn ($q) => $q->where('planned_date', '>=', $d['from']))
            ->when(! empty($d['to']), fn ($q) => $q->where('planned_date', '<=', $d['to']))
            ->when(! empty($d['audience_id']), fn ($q) => $q->where('audience_id', $d['audience_id']))
            ->when(! empty($d['parent_id']), fn ($q) => $q->where('parent_id', $d['parent_id']))
            ->when(! empty($d['search']), fn ($q) => $q->where(fn ($w) => $w->where('title', 'like', '%' . $d['search'] . '%')->orWhere('rubric', 'like', '%' . $d['search'] . '%')));

        $total = (clone $q)->count();
        $pubs = $q->orderBy('planned_date')->orderBy('planned_time')->orderBy('id')->skip(($page - 1) * $perPage)->take($perPage)->get();
        $placements = Placement::query()->whereIn('publication_id', $pubs->pluck('id'))->get()->groupBy('publication_id');

        return [
            'total' => $total, 'page' => $page, 'per_page' => $perPage, 'has_more' => $page * $perPage < $total,
            'publications' => $pubs->map(fn (Publication $p) => Cards::publication($p, $placements->get($p->id, collect())))->all(),
        ];
    }
}
