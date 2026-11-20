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

#[Name('content_decide')]
#[Description('Решение согласующего по публикации со статусом review: approve → ready (готова к публикации), return → in_work (на доработку; comment обязателен — что именно править). Это решение человека: вызывайте ТОЛЬКО по прямому указанию пользователя, не одобряйте и не возвращайте тексты по своему усмотрению. Доступно согласующему, редактору и владельцу.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent(false)]
class DecidePublication extends ContentWriteTool
{
    protected function ability(): string
    {
        return 'content.approve';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'publication_id' => $schema->integer()->description('ID публикации со статусом review.')->required(),
            'decision' => $schema->string()->enum(['approve', 'return'])->description('approve — согласовать, return — вернуть на доработку.')->required(),
            'comment' => $schema->string()->description('Пояснение; для return обязательно.'),
        ] + $this->idempotencySchema($schema);
    }

    protected function write(Request $request, Tenant $tenant, User $user): array|Response
    {
        $d = $this->input($request, [
            'publication_id' => ['required', 'integer'], 'decision' => ['required', 'in:approve,return'], 'comment' => ['nullable', 'string', 'max:2000'],
            'client_request_id' => ['nullable', 'string', 'max:100'],
        ]);

        return ['decided' => true, 'entry' => app(ReviewResolver::class)->decide(null, ['publication_id' => $d['publication_id'], 'decision' => $d['decision'], 'comment' => $d['comment'] ?? null])];
    }
}
