<?php

namespace App\Configs\Content\Mcp\Tools;

use App\Configs\Content\GraphQL\Resolvers\ContentResolver;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('content_plan_review')]
#[Description('Разбор контент-плана правилами платформы (без ИИ): просрочено, не подготовлено к дате, ждёт согласования, не размещено везде, залежавшаяся рубрика, пустые дни, однообразие, слабая площадка. Каждая находка: code, severity (info/warn/bad), publication_id и короткая деталь meta. Подходит как отправная точка «что делать сегодня».')]
#[IsReadOnly]
#[IsIdempotent]
class PlanReview extends ContentTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->description('Только этот проект (по умолчанию все).'),
            'today' => $schema->string()->description('YYYY-MM-DD: «сегодня» для расчёта (по умолчанию текущая дата).'),
        ];
    }

    protected function execute(Request $request, Tenant $tenant, ?User $user): array|Response
    {
        $d = $this->input($request, ['project_id' => ['nullable', 'integer'], 'today' => ['nullable', 'date_format:Y-m-d']]);

        return ['findings' => app(ContentResolver::class)->review(null, array_filter($d, fn ($v) => $v !== null))];
    }
}
