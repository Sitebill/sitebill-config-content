<?php

namespace App\Configs\Content\Mcp\Tools;

use App\Configs\Content\GraphQL\Resolvers\ContentResolver;
use App\Configs\Content\Mcp\Support\Cards;
use App\Configs\Content\Models\Project;
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

#[Name('content_save_project')]
#[Description('Создаёт проект (бренд/клиент) или меняет его «память бренда»: тематика, аудитория, тон (tone of voice), ключевые слова, конкуренты — именно по ним ИИ и вы пишете тексты. Без id — новый (нужен name; к нему автоматически добавятся типовые площадки; действует лимит тарифа), с id — меняются только переданные поля. Только редактор и владелец. Не придумывайте бренд-память: спросите пользователя или берите из его материалов.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent(true)]
class SaveProject extends ContentWriteTool
{
    protected function ability(): string
    {
        return 'content.manage';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('ID проекта — для правки.'),
            'name' => $schema->string()->description('Название до 120 знаков (обязательно для нового).'),
            'description' => $schema->string()->description('Описание до 2000 знаков.'),
            'topic' => $schema->string()->description('Тематика до 200 знаков.'),
            'audience' => $schema->string()->description('Целевая аудитория: названия из библиотеки через «; » (или свой текст), до 1000 знаков.'),
            'tone' => $schema->string()->description('Тон (tone of voice) до 200 знаков.'),
            'keywords' => $schema->string()->description('Ключевые слова через запятую, до 300 знаков.'),
            'competitors' => $schema->string()->description('Конкуренты для отстройки, до 300 знаков.'),
            'manager' => $schema->string()->description('Ответственный менеджер.'),
        ] + $this->idempotencySchema($schema);
    }

    protected function write(Request $request, Tenant $tenant, User $user): array|Response
    {
        $d = $this->input($request, [
            'id' => ['nullable', 'integer'], 'name' => ['nullable', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:2000'], 'topic' => ['nullable', 'string', 'max:200'],
            'audience' => ['nullable', 'string', 'max:1000'], 'tone' => ['nullable', 'string', 'max:200'], 'keywords' => ['nullable', 'string', 'max:300'],
            'competitors' => ['nullable', 'string', 'max:300'], 'manager' => ['nullable', 'string', 'max:120'], 'client_request_id' => ['nullable', 'string', 'max:100'],
        ]);
        $id = $d['id'] ?? null;
        unset($d['id'], $d['client_request_id']);

        if ($id) {
            if (! Project::query()->whereKey($id)->exists()) {
                return Response::error('Проект не найден.');
            }
            if (array_key_exists('name', $d) && $d['name'] === null) {
                unset($d['name']);
            }
        } elseif (empty($d['name'])) {
            return Response::error('Для нового проекта нужен name.');
        }

        $row = app(ContentResolver::class)->saveProject(null, ['id' => $id, 'input' => $d]);

        return ['saved' => true, 'project' => Cards::project(Project::query()->findOrFail($row['id']))];
    }
}
