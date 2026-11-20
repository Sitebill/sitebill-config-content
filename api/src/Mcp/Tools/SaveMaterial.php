<?php

namespace App\Configs\Content\Mcp\Tools;

use App\Configs\Content\GraphQL\Resolvers\ContentResolver;
use App\Configs\Content\Mcp\Support\Cards;
use App\Configs\Content\Models\Material;
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

#[Name('content_save_material')]
#[Description('Создаёт или меняет запись библиотеки (книга, дневник, заметка, целевая аудитория, идея, хештеги, кейс). Без id — новая (нужны kind и title), с id — меняются только переданные поля. body — markdown. Для kind=audience можно передать pains — «боли аудитории» целиком (заменяют прежние, поэтому сначала прочитайте текущие через content_get_material и верните всё, что должно остаться): массив разделов {id?, title, pains:[{id?, title, description}], questions:[{id?, question, answer}]}. У существующих строк сохраняйте их id — на них ссылаются публикации (pain_ids); у новых id не нужен. Это данные пользователя: не выдумывайте боли и ответы — берите только то, что сообщил пользователь или что есть в материалах.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent(true)]
class SaveMaterial extends ContentWriteTool
{
    protected function ability(): string
    {
        return 'content.write';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('ID записи — для правки.'),
            'kind' => $schema->string()->enum(['book', 'diary', 'note', 'audience', 'idea', 'hashtags', 'case'])->description('Вид (обязателен для новой).'),
            'title' => $schema->string()->description('Заголовок до 200 знаков (обязателен для новой).'),
            'body' => $schema->string()->description('Текст/описание, markdown.'),
            'project_id' => $schema->integer()->description('Проект; пусто — общая запись.'),
            'entry_date' => $schema->string()->description('YYYY-MM-DD.'),
            'state' => $schema->string()->enum(['read', 'planned', 'best', 'todo', 'done'])->description('Книга: read/planned/best; заметка: todo/done.'),
            'sphere' => $schema->string()->enum(['work', 'art'])->description('Аудитория и хештеги: рабочее/художественное.'),
            'topic' => $schema->string()->description('Книга: автор; хештеги: тема.'),
            'tags' => $schema->string()->description('Теги до 200 знаков.'),
            'pains' => $schema->array()->items($schema->object([
                'id' => $schema->string()->description('id раздела (у существующего сохраните).'),
                'title' => $schema->string()->description('Название раздела, например «Боли частных риелторов».'),
                'pains' => $schema->array()->items($schema->object([
                    'id' => $schema->string(), 'title' => $schema->string()->description('Вид боли.'), 'description' => $schema->string()->description('Описание.'),
                ])),
                'questions' => $schema->array()->items($schema->object([
                    'id' => $schema->string(), 'question' => $schema->string(), 'answer' => $schema->string(),
                ])),
            ]))->description('Только для kind=audience: боли аудитории целиком.'),
        ] + $this->idempotencySchema($schema);
    }

    protected function write(Request $request, Tenant $tenant, User $user): array|Response
    {
        $d = $this->input($request, [
            'id' => ['nullable', 'integer'], 'kind' => ['nullable', 'in:book,diary,note,audience,idea,hashtags,case'], 'title' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:50000'], 'project_id' => ['nullable', 'integer'], 'entry_date' => ['nullable', 'date_format:Y-m-d'],
            'state' => ['nullable', 'in:read,planned,best,todo,done'], 'sphere' => ['nullable', 'in:work,art'], 'topic' => ['nullable', 'string', 'max:160'],
            'tags' => ['nullable', 'string', 'max:200'], 'pains' => ['nullable', 'array', 'max:30'], 'client_request_id' => ['nullable', 'string', 'max:100'],
        ]);
        $id = $d['id'] ?? null;
        unset($d['id'], $d['client_request_id']);

        if ($id) {
            $m = Material::query()->find($id);
            if (! $m) {
                return Response::error('Запись не найдена.');
            }
            $d += ['kind' => $m->kind, 'title' => $m->title];
            $d['kind'] = $d['kind'] ?? $m->kind;
            $d['title'] = $d['title'] ?? $m->title;
            if ($d['kind'] !== $m->kind && $m->kind === 'audience') {
                return Response::error('Вид записи-аудитории менять нельзя: на неё ссылаются публикации.');
            }
        } elseif (empty($d['kind']) || empty($d['title'])) {
            return Response::error('Для новой записи нужны kind и title.');
        }
        if (isset($d['pains']) && $d['kind'] !== 'audience') {
            return Response::error('pains можно передавать только для kind=audience.');
        }
        if (array_key_exists('pains', $d)) {
            // Резолвер принимает боли JSON-строкой (как GraphQL) и сам чистит/ограничивает её.
            $d['pains'] = $d['pains'] === null ? null : json_encode($d['pains'], JSON_UNESCAPED_UNICODE);
        }
        $d = array_filter($d, fn ($v, $k) => $v !== null || in_array($k, ['body', 'project_id', 'topic', 'tags', 'state', 'sphere', 'pains'], true), ARRAY_FILTER_USE_BOTH);

        $row = app(ContentResolver::class)->saveMaterial(null, ['id' => $id, 'input' => $d]);

        return ['saved' => true, 'material' => Cards::material(Material::query()->findOrFail($row['id']), true)];
    }
}
