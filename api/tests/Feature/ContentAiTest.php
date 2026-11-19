<?php

use App\Configs\Content\Models\Platform;
use App\Configs\Content\Models\Project;
use App\Configs\Content\Services\Ai\PlatformHints;
use App\Models\AiUsageLog;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

// ИИ-фичи контент-фабрики: бренд-память в промпте, границы данных, права, лимиты площадки (docs/roadmap/CONTENT_FACTORY.md §6).

const CONTENT_AI_SAVE = 'mutation ($input: AiConnectionInput!) { saveMyAiConnection(input: $input) { status { available } } }';
const CONTENT_AI = 'mutation ($i: ContentAiInput!) { contentAiGenerate(input: $i) { text items } }';

beforeEach(function () {
    config(['outbound.allow_private_hosts' => ['api.openai.com']]);
    RateLimiter::clear('ai:' . app('currentTenant')->id . ':1');
});

/** Админ с подключённым ключом OpenAI; ответ модели подменён. */
function contentAiAdmin(object $test, string $reply): \App\Models\User
{
    fakeHttp(['api.openai.com/*' => Http::response([
        'choices' => [['message' => ['content' => $reply]]],
        'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 30],
        'model' => 'gpt-4o-mini',
    ])]);
    $admin = createAdminUser();
    contentAs($test, $admin, CONTENT_AI_SAVE, ['input' => ['provider' => 'openai', 'api_key' => 'sk-test-1234567890abcd', 'model' => 'gpt-4o-mini', 'consent' => true]])
        ->assertGraphQLErrorFree();

    return $admin;
}

function contentAiProject(): Project
{
    $p = new Project(['name' => 'Студия «Лён»', 'topic' => 'интерьер квартир', 'audience' => 'владельцы квартир', 'tone' => 'спокойный', 'keywords' => 'ремонт, свет', 'competitors' => 'КонкурентХ']);
    $p->tenant_id = app('currentTenant')->id;
    $p->save();

    return $p;
}

test('draft: бренд-память проекта и площадка попадают в промпт, результат чистится и ничего не сохраняется', function () {
    $admin = contentAiAdmin($this, "```\n**Свет** для кухни\n\nТекст поста.\n```");
    $project = contentAiProject();
    $tg = new Platform(['project_id' => $project->id, 'name' => 'Telegram', 'kind' => 'telegram', 'max_length' => 1500]);
    $tg->tenant_id = $project->tenant_id;
    $tg->save();

    contentAs($this, $admin, CONTENT_AI, ['i' => ['action' => 'draft', 'project_id' => $project->id, 'platform_id' => $tg->id, 'rubric' => 'Советы', 'instruction' => 'про свет на кухне']])
        ->assertGraphQLErrorFree()
        ->assertJsonPath('data.contentAiGenerate.text', "Свет для кухни\n\nТекст поста.");

    Http::assertSent(function ($request) {
        $body = json_encode($request->data(), JSON_UNESCAPED_UNICODE);

        return str_contains($body, 'интерьер квартир') && str_contains($body, 'спокойный')
            && str_contains($body, 'Предел длины: 1500') && str_contains($body, 'про свет на кухне');
    });
    $log = AiUsageLog::where('feature', 'content.draft')->firstOrFail();
    expect($log->status)->toBe('ok')->and($log->source)->toBe('own_key')->and($log->tokens_in)->toBe(100);
    expect(\App\Configs\Content\Models\Publication::count())->toBe(0);
});

test('titles и hashtags разбираются в список', function () {
    $project = contentAiProject();

    $admin = contentAiAdmin($this, "1. Как выбрать свет\n- Свет для кухни\n\"Как выбрать свет\"\n");
    contentAs($this, $admin, CONTENT_AI, ['i' => ['action' => 'titles', 'project_id' => $project->id, 'text' => 'Текст']])
        ->assertJsonPath('data.contentAiGenerate.items', ['Как выбрать свет', 'Свет для кухни']);

    contentAiAdmin($this, 'Вот теги: #свет #кухня, #интерьер_квартир #свет');
    contentAs($this, $admin, CONTENT_AI, ['i' => ['action' => 'hashtags', 'project_id' => $project->id, 'text' => 'Текст']])
        ->assertJsonPath('data.contentAiGenerate.items', ['#свет', '#кухня', '#интерьер_квартир']);
});

test('adapt режет ответ до предела площадки по границе слова', function () {
    $admin = contentAiAdmin($this, str_repeat('слово ', 100));
    $project = contentAiProject();
    $tw = new Platform(['project_id' => $project->id, 'name' => 'X', 'kind' => 'other', 'max_length' => 50]);
    $tw->tenant_id = $project->tenant_id;
    $tw->save();

    $text = contentAs($this, $admin, CONTENT_AI, ['i' => ['action' => 'adapt', 'project_id' => $project->id, 'platform_id' => $tw->id, 'text' => 'Исходник']])
        ->json('data.contentAiGenerate.text');
    expect(mb_strlen($text))->toBeLessThanOrEqual(50)->and($text)->toEndWith('слово');

    expect(PlatformHints::fit('коротко', ['max_length' => 50]))->toBe('коротко');
});

test('чужой проект недоступен, rewrite и adapt требуют текст', function () {
    $admin = contentAiAdmin($this, 'ok');
    $project = contentAiProject();
    // проект другого тенанта
    $other = Tenant::create(['name' => 'Чужой', 'slug' => 'content-ai-other', 'status' => 'active']);
    $foreign = new Project(['name' => 'ЧужойПроект']);
    $foreign->tenant_id = $other->id;
    $foreign->save();

    contentAs($this, $admin, CONTENT_AI, ['i' => ['action' => 'rewrite', 'project_id' => $project->id]])
        ->assertGraphQLValidationError('input.text', 'Нужен исходный текст.');
    contentAs($this, $admin, CONTENT_AI, ['i' => ['action' => 'draft', 'project_id' => $foreign->id]])
        ->assertGraphQLValidationError('input.project_id', 'Проект не найден.');
});

test('права: автор может, наблюдатель и посетитель нет; без ключа понятная ошибка', function () {
    $project = contentAiProject();
    $author = contentMember('author');

    contentAs($this, $author, CONTENT_AI, ['i' => ['action' => 'draft', 'project_id' => $project->id]])
        ->assertGraphQLErrorMessage('ИИ не подключён. Укажите API-ключ провайдера в настройках (раздел «ИИ») или обратитесь к администратору платформы.');

    contentAs($this, contentMember('viewer'), CONTENT_AI, ['i' => ['action' => 'draft', 'project_id' => $project->id]])
        ->assertGraphQLErrorMessage('This action is unauthorized.');
    contentAs($this, \App\Models\User::factory()->create(), CONTENT_AI, ['i' => ['action' => 'draft', 'project_id' => $project->id]])
        ->assertGraphQLErrorMessage('This action is unauthorized.');
});
