<?php

namespace App\Configs\Content\Mcp\Tools;

use App\Configs\Content\GraphQL\Resolvers\ContentResolver;
use App\Configs\Content\Mcp\Support\Cards;
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

#[Name('content_move_publication')]
#[Description('Быстро меняет статус и/или плановую дату публикации (как перетаскивание на доске и в календаре). Текст не меняет. ready и published может поставить только редактор или согласующий; published — только когда пользователь подтвердил, что текст вышел.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent(true)]
class MovePublication extends ContentWriteTool
{
    protected function ability(): string
    {
        return 'content.move';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('ID публикации.')->required(),
            'status' => $schema->string()->enum(['none', 'idea', 'draft', 'in_work', 'review', 'ready', 'published'])->description('Новый статус.'),
            'planned_date' => $schema->string()->description('Новая дата YYYY-MM-DD; пустая строка — снять дату.'),
        ] + $this->idempotencySchema($schema);
    }

    protected function write(Request $request, Tenant $tenant, User $user): array|Response
    {
        $d = $this->input($request, [
            'id' => ['required', 'integer'], 'status' => ['nullable', 'in:none,idea,draft,in_work,review,ready,published'],
            'planned_date' => ['nullable', 'date_format:Y-m-d'], 'client_request_id' => ['nullable', 'string', 'max:100'],
        ]);
        $args = ['id' => $d['id']];
        if (! empty($d['status'])) {
            $args['status'] = $d['status'];
        }
        if (array_key_exists('planned_date', $d)) {
            $args['planned_date'] = $d['planned_date'];
        }
        if (count($args) === 1) {
            return Response::error('Передайте status и/или planned_date.');
        }
        if (! Publication::query()->whereKey($d['id'])->exists()) {
            return Response::error('Публикация не найдена.');
        }

        $saved = app(ContentResolver::class)->movePublication(null, $args);

        return ['moved' => true, 'publication' => Cards::publication(Publication::query()->findOrFail($saved['id']))];
    }
}
