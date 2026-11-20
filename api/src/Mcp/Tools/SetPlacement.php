<?php

namespace App\Configs\Content\Mcp\Tools;

use App\Configs\Content\GraphQL\Resolvers\ContentResolver;
use App\Configs\Content\Mcp\Support\Cards;
use App\Configs\Content\Models\Platform;
use App\Configs\Content\Models\Publication;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('content_set_placement')]
#[Description('Отмечает состояние публикации на одной площадке: todo, in_work, scheduled, published, skipped; для published можно передать url опубликованного материала. Общий статус публикации пересчитывается сам (опубликована везде → published). Ставьте published только когда пользователь подтвердил, что материал реально вышел на этой площадке.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent(true)]
class SetPlacement extends ContentWriteTool
{
    protected function ability(): string
    {
        return 'content.write';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'publication_id' => $schema->integer()->description('ID публикации.')->required(),
            'platform_id' => $schema->integer()->description('ID площадки того же проекта (content_overview.platforms).')->required(),
            'status' => $schema->string()->enum(['todo', 'in_work', 'scheduled', 'published', 'skipped'])->description('Состояние на площадке.')->required(),
            'url' => $schema->string()->description('Ссылка на опубликованный материал.'),
            'comment' => $schema->string()->description('Заметка до 300 знаков.'),
        ] + $this->idempotencySchema($schema);
    }

    protected function write(Request $request, Tenant $tenant, User $user): array|Response
    {
        $d = $this->input($request, [
            'publication_id' => ['required', 'integer'], 'platform_id' => ['required', 'integer'], 'status' => ['required', 'in:todo,in_work,scheduled,published,skipped'],
            'url' => ['nullable', 'url', 'max:500'], 'comment' => ['nullable', 'string', 'max:300'], 'client_request_id' => ['nullable', 'string', 'max:100'],
        ]);
        $pub = Publication::query()->find($d['publication_id']);
        $platform = Platform::query()->find($d['platform_id']);
        if (! $pub || ! $platform) {
            return Response::error('Публикация или площадка не найдены.');
        }
        if ((int) $platform->project_id !== (int) $pub->project_id) {
            return Response::error('Площадка принадлежит другому проекту.');
        }
        unset($d['client_request_id']);

        app(ContentResolver::class)->setPlacement(null, $d);

        return ['saved' => true, 'publication' => Cards::publication($pub->fresh())];
    }
}
