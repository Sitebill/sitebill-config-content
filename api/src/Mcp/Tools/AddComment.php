<?php

namespace App\Configs\Content\Mcp\Tools;

use App\Configs\Content\GraphQL\Resolvers\ReviewResolver;
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

#[Name('content_add_comment')]
#[Description('Пишет комментарий в ленту обсуждения публикации (виден команде). Автором комментария будет владелец токена. Не вставляйте в комментарий персональные данные и секреты.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent(false)]
class AddComment extends ContentWriteTool
{
    protected function ability(): string
    {
        return 'content.move';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'publication_id' => $schema->integer()->description('ID публикации.')->required(),
            'body' => $schema->string()->description('Текст комментария, до 2000 знаков.')->required(),
        ] + $this->idempotencySchema($schema);
    }

    protected function write(Request $request, Tenant $tenant, User $user): array|Response
    {
        $d = $this->input($request, ['publication_id' => ['required', 'integer'], 'body' => ['required', 'string', 'max:2000'], 'client_request_id' => ['nullable', 'string', 'max:100']]);

        return ['saved' => true, 'comment' => app(ReviewResolver::class)->addComment(null, ['publication_id' => $d['publication_id'], 'body' => $d['body']])];
    }
}
