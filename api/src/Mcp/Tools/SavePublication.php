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

#[Name('content_save_publication')]
#[Description('Создаёт публикацию (без id: нужны project_id и title) или меняет существующую (с id: меняются только переданные поля; пустая строка очищает необязательное поле). Текст body — markdown. Новая публикация по умолчанию получает статус idea. Автор не может поставить ready/published — это делают редактор и согласующий; чтобы отдать текст на проверку, ставьте status=review. platform_ids заменяет список площадок размещения (уже опубликованные не удаляются). audience_id и pain_ids — для какой аудитории текст и какие её боли он решает (id из content_get_material); боли чужой аудитории отбрасываются. Не выдумывайте факты и ссылки; публикуйте (published) только по прямому указанию пользователя.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent(true)]
class SavePublication extends ContentWriteTool
{
    protected function ability(): string
    {
        return 'content.write';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('ID публикации — для правки; без него создаётся новая.'),
            'project_id' => $schema->integer()->description('Проект (обязателен для новой).'),
            'title' => $schema->string()->description('Заголовок до 200 знаков (обязателен для новой).'),
            'kind' => $schema->string()->enum(['post', 'article', 'news', 'case', 'video', 'announcement'])->description('Формат.'),
            'rubric' => $schema->string()->description('Рубрика до 80 знаков.'),
            'sphere' => $schema->string()->enum(['work', 'art', 'other'])->description('Сфера.'),
            'status' => $schema->string()->enum(['none', 'idea', 'draft', 'in_work', 'review', 'ready', 'published'])->description('Статус.'),
            'priority' => $schema->string()->enum(['low', 'normal', 'high'])->description('Приоритет.'),
            'planned_date' => $schema->string()->description('YYYY-MM-DD.'),
            'planned_time' => $schema->string()->description('HH:MM.'),
            'body' => $schema->string()->description('Текст публикации, markdown.'),
            'hashtags' => $schema->string()->description('Хештеги через пробел, до 300 знаков.'),
            'link' => $schema->string()->description('Ссылка/UTM.'),
            'assignee' => $schema->string()->description('Ответственный (имя).'),
            'comment' => $schema->string()->description('Внутренний комментарий.'),
            'parent_id' => $schema->integer()->description('Публикация-источник, если это версия/адаптация.'),
            'platform_ids' => $schema->array()->items($schema->integer())->description('Площадки размещения (id из content_overview).'),
            'audience_id' => $schema->integer()->description('Целевая аудитория из библиотеки.'),
            'pain_ids' => $schema->array()->items($schema->string())->description('id болей этой аудитории, которые решает текст.'),
        ] + $this->idempotencySchema($schema);
    }

    protected function write(Request $request, Tenant $tenant, User $user): array|Response
    {
        $d = $this->input($request, [
            'id' => ['nullable', 'integer'], 'project_id' => ['nullable', 'integer'], 'title' => ['nullable', 'string', 'max:200'],
            'kind' => ['nullable', 'in:post,article,news,case,video,announcement'], 'rubric' => ['nullable', 'string', 'max:80'], 'sphere' => ['nullable', 'in:work,art,other'],
            'status' => ['nullable', 'in:none,idea,draft,in_work,review,ready,published'], 'priority' => ['nullable', 'in:low,normal,high'],
            'planned_date' => ['nullable', 'date_format:Y-m-d'], 'planned_time' => ['nullable', 'date_format:H:i'], 'body' => ['nullable', 'string', 'max:100000'],
            'hashtags' => ['nullable', 'string', 'max:300'], 'link' => ['nullable', 'string', 'max:500'], 'assignee' => ['nullable', 'string', 'max:120'],
            'comment' => ['nullable', 'string', 'max:2000'], 'parent_id' => ['nullable', 'integer'],
            'platform_ids' => ['nullable', 'array', 'max:30'], 'platform_ids.*' => ['integer'],
            'audience_id' => ['nullable', 'integer'], 'pain_ids' => ['nullable', 'array', 'max:50'], 'pain_ids.*' => ['string', 'max:24'],
            'client_request_id' => ['nullable', 'string', 'max:100'],
        ]);
        $id = $d['id'] ?? null;
        unset($d['id'], $d['client_request_id']);

        // Правка: обязательные поля берём из текущей записи, чтобы агенту хватало передать только изменения.
        if ($id) {
            $pub = Publication::query()->find($id);
            if (! $pub) {
                return Response::error('Публикация не найдена.');
            }
            $d += ['project_id' => $pub->project_id, 'title' => $pub->title];
            // Аудитория и боли идут парой: только боли — к текущей аудитории; та же аудитория без болей — прежние боли.
            if (array_key_exists('pain_ids', $d) && ! array_key_exists('audience_id', $d)) {
                $d['audience_id'] = $pub->audience_id;
            } elseif (array_key_exists('audience_id', $d) && ! array_key_exists('pain_ids', $d) && (int) $d['audience_id'] === (int) $pub->audience_id) {
                $d['pain_ids'] = $pub->pain_ids ?? [];
            }
        } elseif (empty($d['project_id']) || empty($d['title'])) {
            return Response::error('Для новой публикации нужны project_id и title.');
        }
        // Только переданные поля: null у «обязательных» не должен затирать значения.
        $d = array_filter($d, fn ($v, $k) => $v !== null || ! in_array($k, ['project_id', 'title', 'kind', 'sphere', 'status', 'priority'], true), ARRAY_FILTER_USE_BOTH);

        $saved = app(ContentResolver::class)->savePublication(null, ['id' => $id, 'input' => $d]);

        return ['saved' => true, 'publication' => Cards::publication(Publication::query()->findOrFail($saved['id']), null, true)];
    }
}
