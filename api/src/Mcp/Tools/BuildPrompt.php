<?php

namespace App\Configs\Content\Mcp\Tools;

use App\Configs\Content\GraphQL\Resolvers\AiResolver;
use App\Configs\Content\Models\Publication;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('content_build_prompt')]
#[Description('Собирает готовый промпт встроенного ИИ-помощника платформы (бренд-память проекта, формат и лимит площадки, отмеченные боли аудитории, пожелания) для действия draft | rewrite | adapt | titles | hashtags. Модель платформы НЕ вызывается и ничего не тратится: вы получаете бриф и выполняете его сами. Можно передать publication_id — тогда название, формат, рубрика, текст, аудитория и боли берутся из публикации.')]
#[IsReadOnly]
#[IsIdempotent]
class BuildPrompt extends ContentTool
{
    protected function ability(): string
    {
        return 'content.write';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->enum(['draft', 'rewrite', 'adapt', 'titles', 'hashtags'])->description('Что нужно сделать.')->required(),
            'publication_id' => $schema->integer()->description('Взять данные из этой публикации (остальные поля перекрывают их).'),
            'project_id' => $schema->integer()->description('Проект (обязателен, если нет publication_id).'),
            'platform_id' => $schema->integer()->description('Площадка: формат и лимит длины (для adapt — целевая).'),
            'title' => $schema->string()->description('Тема/заголовок.'),
            'kind' => $schema->string()->description('Формат: post, article, news, case, video, announcement.'),
            'rubric' => $schema->string()->description('Рубрика.'),
            'text' => $schema->string()->description('Исходный текст для rewrite/adapt/titles/hashtags.'),
            'instruction' => $schema->string()->description('Пожелания и тезисы (до 1000 знаков).'),
            'audience_id' => $schema->integer()->description('Целевая аудитория из библиотеки.'),
            'pain_ids' => $schema->array()->items($schema->string())->description('id болей аудитории, которые решает текст.'),
            'locale' => $schema->string()->enum(['ru', 'en', 'sr', 'tg'])->description('Язык текста.'),
        ];
    }

    protected function execute(Request $request, Tenant $tenant, ?User $user): array|Response
    {
        $d = $this->input($request, [
            'action' => ['required', 'in:draft,rewrite,adapt,titles,hashtags'], 'publication_id' => ['nullable', 'integer'], 'project_id' => ['nullable', 'integer'],
            'platform_id' => ['nullable', 'integer'], 'title' => ['nullable', 'string', 'max:200'], 'kind' => ['nullable', 'string', 'max:40'], 'rubric' => ['nullable', 'string', 'max:80'],
            'text' => ['nullable', 'string', 'max:20000'], 'instruction' => ['nullable', 'string', 'max:1000'], 'audience_id' => ['nullable', 'integer'],
            'pain_ids' => ['nullable', 'array', 'max:50'], 'pain_ids.*' => ['string', 'max:24'], 'locale' => ['nullable', 'in:ru,en,sr,tg'],
        ]);

        if (! empty($d['publication_id'])) {
            $pub = Publication::query()->find($d['publication_id']);
            if (! $pub) {
                return Response::error('Публикация не найдена.');
            }
            $d += ['project_id' => $pub->project_id, 'title' => $pub->title, 'kind' => $pub->kind, 'rubric' => $pub->rubric, 'text' => $pub->body,
                'audience_id' => $pub->audience_id, 'pain_ids' => $pub->pain_ids ?? []];
        }
        unset($d['publication_id']);
        if (empty($d['project_id'])) {
            return Response::error('Укажите project_id или publication_id.');
        }

        return ['prompt' => app(AiResolver::class)->prompt(null, ['input' => array_filter($d, fn ($v) => $v !== null)])];
    }
}
