<?php

namespace App\Configs\Content\Mcp\Tools;

use App\Configs\Content\Mcp\Support\Cards;
use App\Configs\Content\Models\Material;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('content_list_materials')]
#[Description('Библиотека: книги, дневник, заметки, целевые аудитории, идеи, хештеги, кейсы (без текста — только body_length). Фильтры: вид, проект, поиск по заголовку/тегам. Открыть запись целиком — content_get_material.')]
#[IsReadOnly]
#[IsIdempotent]
class ListMaterials extends ContentTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()->enum(['book', 'diary', 'note', 'audience', 'idea', 'hashtags', 'case'])->description('Вид материала.'),
            'project_id' => $schema->integer()->description('Материалы проекта и общие (без проекта).'),
            'search' => $schema->string()->description('Часть заголовка или тегов.'),
            'page' => $schema->integer()->description('Страница, с 1.'),
            'per_page' => $schema->integer()->description('1–50, по умолчанию 20.'),
        ];
    }

    protected function execute(Request $request, Tenant $tenant, ?User $user): array|Response
    {
        $d = $this->input($request, [
            'kind' => ['nullable', 'in:book,diary,note,audience,idea,hashtags,case'], 'project_id' => ['nullable', 'integer'], 'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:500'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $page = (int) ($d['page'] ?? 1);
        $perPage = (int) ($d['per_page'] ?? 20);

        $q = Material::query()
            ->when(! empty($d['kind']), fn ($q) => $q->where('kind', $d['kind']))
            ->when(! empty($d['project_id']), fn ($q) => $q->where(fn ($w) => $w->where('project_id', $d['project_id'])->orWhereNull('project_id')))
            ->when(! empty($d['search']), fn ($q) => $q->where(fn ($w) => $w->where('title', 'like', '%' . $d['search'] . '%')->orWhere('tags', 'like', '%' . $d['search'] . '%')));

        $total = (clone $q)->count();
        $rows = $q->orderByDesc('entry_date')->orderByDesc('id')->skip(($page - 1) * $perPage)->take($perPage)->get();

        return ['total' => $total, 'page' => $page, 'per_page' => $perPage, 'has_more' => $page * $perPage < $total, 'materials' => $rows->map(fn (Material $m) => Cards::material($m))->all()];
    }
}
