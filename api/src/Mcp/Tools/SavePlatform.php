<?php

namespace App\Configs\Content\Mcp\Tools;

use App\Configs\Content\GraphQL\Resolvers\ContentResolver;
use App\Configs\Content\Mcp\Support\Cards;
use App\Configs\Content\Models\Platform;
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

#[Name('content_save_platform')]
#[Description('Создаёт площадку проекта (канал публикации) или меняет её: тип, ссылка, формат, максимальная длина текста и желаемая частота в неделю — их учитывают план и ИИ-адаптация. Без id — новая (нужны project_id, name, kind), с id — меняются только переданные поля. Только редактор и владелец. API-подключения площадок через MCP не настраиваются (api_status остаётся manual).')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent(true)]
class SavePlatform extends ContentWriteTool
{
    protected function ability(): string
    {
        return 'content.manage';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('ID площадки — для правки.'),
            'project_id' => $schema->integer()->description('Проект (обязателен для новой).'),
            'name' => $schema->string()->description('Название до 120 знаков.'),
            'kind' => $schema->string()->enum(['telegram', 'vk', 'dzen', 'ok', 'tiktok', 'youtube', 'rutube', 'habr', 'vcru', 'site', 'blog', 'email', 'avtor_today', 'max', 'workspace', 'tenchat', 'looky', 'other'])->description('Тип площадки.'),
            'url' => $schema->string()->description('Ссылка на канал/страницу.'),
            'audience' => $schema->string()->description('Аудитория площадки, до 300 знаков.'),
            'formats' => $schema->string()->description('Подходящие форматы, до 200 знаков.'),
            'max_length' => $schema->integer()->description('Максимальная длина текста в знаках.'),
            'per_week' => $schema->integer()->description('Желаемая частота: публикаций в неделю, 0–100.'),
        ] + $this->idempotencySchema($schema);
    }

    protected function write(Request $request, Tenant $tenant, User $user): array|Response
    {
        $d = $this->input($request, [
            'id' => ['nullable', 'integer'], 'project_id' => ['nullable', 'integer'], 'name' => ['nullable', 'string', 'max:120'],
            'kind' => ['nullable', 'in:telegram,vk,dzen,ok,tiktok,youtube,rutube,habr,vcru,site,blog,email,avtor_today,max,workspace,tenchat,looky,other'],
            'url' => ['nullable', 'url', 'max:300'], 'audience' => ['nullable', 'string', 'max:300'], 'formats' => ['nullable', 'string', 'max:200'],
            'max_length' => ['nullable', 'integer', 'min:1', 'max:1000000'], 'per_week' => ['nullable', 'integer', 'min:0', 'max:100'], 'client_request_id' => ['nullable', 'string', 'max:100'],
        ]);
        $id = $d['id'] ?? null;
        unset($d['id'], $d['client_request_id']);

        if ($id) {
            $p = Platform::query()->find($id);
            if (! $p) {
                return Response::error('Площадка не найдена.');
            }
            $d = array_filter($d, fn ($v, $k) => $v !== null || ! in_array($k, ['project_id', 'name', 'kind'], true), ARRAY_FILTER_USE_BOTH);
            $d += ['project_id' => $p->project_id, 'name' => $p->name, 'kind' => $p->kind];
        } elseif (empty($d['project_id']) || empty($d['name']) || empty($d['kind'])) {
            return Response::error('Для новой площадки нужны project_id, name и kind.');
        }
        unset($d['api_status']);

        $row = app(ContentResolver::class)->savePlatform(null, ['id' => $id, 'input' => $d]);

        return ['saved' => true, 'platform' => Cards::platform(Platform::query()->findOrFail($row['id']))];
    }
}
