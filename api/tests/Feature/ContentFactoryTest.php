<?php

use App\Configs\Content\Models\Comment;
use App\Configs\Content\Models\Placement;
use App\Configs\Content\Models\Platform;
use App\Configs\Content\Models\Project;
use App\Configs\Content\Models\Publication;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

require_once __DIR__ . '/../Support.php';

// Фаза 3 («фабрика»): ИИ-идеи и план периода, согласование с комментариями, пакетная адаптация в очереди.
// Хелперы contentAs/contentMember — из ContentAccessTest; contentAiAdmin/contentAiProject — из ContentAiTest.

beforeEach(function () {
    config(['outbound.allow_private_hosts' => ['api.openai.com']]);
    RateLimiter::clear('ai:' . app('currentTenant')->id . ':1');
});

function factoryPlatform(Project $project, string $name, ?int $perWeek = null, ?int $max = null): Platform
{
    $p = new Platform(['project_id' => $project->id, 'name' => $name, 'kind' => 'telegram', 'per_week' => $perWeek, 'max_length' => $max]);
    $p->tenant_id = $project->tenant_id;
    $p->save();

    return $p;
}

function factoryPub(Project $project, array $over = []): Publication
{
    $pub = new Publication($over + ['project_id' => $project->id, 'title' => 'Пост', 'status' => 'review', 'body' => 'Исходный текст публикации.']);
    $pub->tenant_id = $project->tenant_id;
    $pub->save();

    return $pub;
}

const FACTORY_IDEAS = 'mutation ($p: Int!) { contentAiIdeas(project_id: $p, count: 2) { title kind rubric body platform_ids planned_date } }';
const FACTORY_PLAN = 'mutation ($p: Int!, $t: String) { contentAiPlan(project_id: $p, days: 7, today: $t) { title planned_date planned_time platform_ids kind } }';

test('ИИ-идеи: JSON из ответа модели превращается в предложения, существующие заголовки идут в промпт', function () {
    $admin = contentAiAdmin($this, "Вот идеи:\n```json\n[{\"title\":\"Как выбрать свет\",\"kind\":\"article\",\"rubric\":\"Советы\",\"hook\":\"О трёх сценариях света.\"},{\"title\":\"\",\"kind\":\"post\"},{\"title\":\"Кейс кухни\",\"kind\":\"странный\",\"hook\":\"\"}]\n```");
    $project = contentAiProject();
    factoryPub($project, ['title' => 'Уже вышедший пост', 'rubric' => 'Кейсы']);

    contentAs($this, $admin, FACTORY_IDEAS, ['p' => $project->id])->assertGraphQLErrorFree()
        ->assertJsonCount(2, 'data.contentAiIdeas')
        ->assertJsonPath('data.contentAiIdeas.0.title', 'Как выбрать свет')
        ->assertJsonPath('data.contentAiIdeas.0.kind', 'article')
        ->assertJsonPath('data.contentAiIdeas.0.body', 'О трёх сценариях света.')
        ->assertJsonPath('data.contentAiIdeas.1.kind', 'post')   // неизвестный формат заменён
        ->assertJsonPath('data.contentAiIdeas.1.planned_date', null);

    Http::assertSent(fn ($r) => str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE), 'Уже вышедший пост'));
    expect(Publication::count())->toBe(1);   // ничего не сохранено
});

test('ответ модели не в формате JSON — понятная ошибка, а не 500', function () {
    $admin = contentAiAdmin($this, 'Извините, не могу.');
    contentAs($this, $admin, FACTORY_IDEAS, ['p' => contentAiProject()->id])
        ->assertGraphQLErrorMessage('Модель вернула ответ в неожиданном формате. Попробуйте ещё раз.');
});

test('план периода: записи вне периода и с чужой площадкой отбрасываются, площадка проверяется', function () {
    $project = contentAiProject();
    $tg = factoryPlatform($project, 'Telegram', 3);
    $reply = json_encode([
        ['date' => '2026-12-02', 'time' => '10:00', 'title' => 'Во вторник', 'kind' => 'post', 'rubric' => 'Советы', 'hook' => 'x', 'platform_id' => $tg->id],
        ['date' => '2026-12-01', 'time' => '25:99', 'title' => 'Первым', 'kind' => 'video', 'platform_id' => 999999],
        ['date' => '2027-01-15', 'time' => '10:00', 'title' => 'За пределами', 'kind' => 'post', 'platform_id' => $tg->id],
        ['date' => 'не дата', 'title' => 'Мусор', 'platform_id' => $tg->id],
    ], JSON_UNESCAPED_UNICODE);
    $admin = contentAiAdmin($this, $reply);

    $res = contentAs($this, $admin, FACTORY_PLAN, ['p' => $project->id, 't' => '2026-12-01'])->assertGraphQLErrorFree();
    $res->assertJsonCount(2, 'data.contentAiPlan')
        ->assertJsonPath('data.contentAiPlan.0.title', 'Первым')              // отсортировано по дате
        ->assertJsonPath('data.contentAiPlan.0.planned_time', null)           // время невалидно
        ->assertJsonPath('data.contentAiPlan.0.platform_ids', [])             // площадки нет в проекте
        ->assertJsonPath('data.contentAiPlan.1.platform_ids', [$tg->id]);

    Http::assertSent(fn ($r) => str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE), 'желаемо 3 в неделю') && str_contains($r->body(), '2026-12-07'));
});

test('согласование: автор отправляет на согласование, согласующий принимает или возвращает с пояснением', function () {
    $admin = createAdminUser();
    $project = contentAiProject();
    $pub = factoryPub($project, ['status' => 'review']);

    $decide = 'mutation ($id: ID!, $d: ContentDecision!, $c: String) { contentDecide(publication_id: $id, decision: $d, comment: $c) { kind body author } }';

    // автор и наблюдатель решать не могут
    contentAs($this, contentMember('author'), $decide, ['id' => $pub->id, 'd' => 'approve'])->assertGraphQLErrorMessage('This action is unauthorized.');
    contentAs($this, contentMember('viewer'), $decide, ['id' => $pub->id, 'd' => 'approve'])->assertGraphQLErrorMessage('This action is unauthorized.');

    // возврат требует пояснения
    $approver = contentMember('approver');
    contentAs($this, $approver, $decide, ['id' => $pub->id, 'd' => 'return'])->assertGraphQLValidationError('comment', 'Напишите, что нужно доработать.');
    contentAs($this, $approver, $decide, ['id' => $pub->id, 'd' => 'return', 'c' => 'Добавьте цифры'])
        ->assertJsonPath('data.contentDecide.kind', 'return')->assertJsonPath('data.contentDecide.body', 'Добавьте цифры');
    expect($pub->fresh()->status)->toBe('in_work');

    // решение принимается только по публикации «на согласовании»
    contentAs($this, $approver, $decide, ['id' => $pub->id, 'd' => 'approve'])->assertGraphQLValidationError('publication_id', 'Решение принимается по публикации со статусом «На согласовании».');

    $pub->fresh()->update(['status' => 'review']);
    contentAs($this, $approver, $decide, ['id' => $pub->id, 'd' => 'approve'])->assertJsonPath('data.contentDecide.kind', 'approve');
    expect($pub->fresh()->status)->toBe('ready');
});

test('лента: автор и согласующий пишут, наблюдатель только читает, чужая публикация недоступна', function () {
    $project = contentAiProject();
    $pub = factoryPub($project);
    $add = 'mutation ($id: ID!, $b: String!) { contentAddComment(publication_id: $id, body: $b) { body author kind } }';
    $list = 'query ($id: ID!) { contentComments(publication_id: $id) { body kind } }';

    contentAs($this, contentMember('author'), $add, ['id' => $pub->id, 'b' => 'Проверьте заголовок'])->assertJsonPath('data.contentAddComment.kind', 'comment');
    contentAs($this, contentMember('viewer'), $add, ['id' => $pub->id, 'b' => 'x'])->assertGraphQLErrorMessage('This action is unauthorized.');
    contentAs($this, contentMember('viewer'), $list, ['id' => $pub->id])->assertJsonCount(1, 'data.contentComments');

    $other = \App\Models\Tenant::create(['name' => 'Чужой', 'slug' => 'content-feed-other', 'status' => 'active']);
    $foreign = new Project(['name' => 'Чужой']);
    $foreign->tenant_id = $other->id;
    $foreign->save();
    $foreignPub = new Publication(['project_id' => $foreign->id, 'title' => 'Чужая', 'status' => 'idea']);
    $foreignPub->tenant_id = $other->id;
    $foreignPub->save();
    contentAs($this, contentMember('author'), $add, ['id' => $foreignPub->id, 'b' => 'x'])->assertGraphQLValidationError('publication_id', 'Публикация не найдена.');
    expect(Comment::withoutGlobalScopes()->where('publication_id', $foreignPub->id)->count())->toBe(0);
});

const FACTORY_BATCH = 'mutation ($id: ID!) { contentAiAdaptAll(publication_id: $id) { batch_id total done skipped finished failed { platform_id error } } }';
const FACTORY_BATCH_STATE = 'query ($b: String!) { contentBatch(batch_id: $b) { total done finished failed { error } } }';

test('пакетная адаптация: версия-черновик на каждую площадку, повтор пропускает уже сделанные', function () {
    $admin = contentAiAdmin($this, 'Адаптированный текст.');
    $project = contentAiProject();
    $tg = factoryPlatform($project, 'Telegram', null, 500);
    $vk = factoryPlatform($project, 'ВК');
    $pub = factoryPub($project);

    $res = contentAs($this, $admin, FACTORY_BATCH, ['id' => $pub->id])->assertGraphQLErrorFree();
    // очередь в тестах синхронная: к моменту ответа всё готово
    $res->assertJsonPath('data.contentAiAdaptAll.total', 2)->assertJsonPath('data.contentAiAdaptAll.done', 2)->assertJsonPath('data.contentAiAdaptAll.finished', true);

    $children = Publication::query()->where('parent_id', $pub->id)->get();
    expect($children)->toHaveCount(2)
        ->and($children->pluck('status')->unique()->all())->toBe(['draft'])
        ->and($children->pluck('body')->unique()->all())->toBe(['Адаптированный текст.'])
        ->and(Placement::query()->whereIn('publication_id', $children->pluck('id'))->pluck('platform_id')->sort()->values()->all())->toBe([$tg->id, $vk->id]);
    expect($children->every(fn ($c) => $c->tenant_id === $pub->tenant_id))->toBeTrue();

    // повтор: все версии уже есть
    contentAs($this, $admin, FACTORY_BATCH, ['id' => $pub->id])
        ->assertJsonPath('data.contentAiAdaptAll.total', 0)->assertJsonPath('data.contentAiAdaptAll.skipped', 2)->assertJsonPath('data.contentAiAdaptAll.finished', true);
    expect(Publication::query()->where('parent_id', $pub->id)->count())->toBe(2);

    // прогресс читается по batch_id
    $id = contentAs($this, $admin, FACTORY_BATCH, ['id' => factoryPub($project)->id])->json('data.contentAiAdaptAll.batch_id');
    contentAs($this, $admin, FACTORY_BATCH_STATE, ['b' => $id])->assertJsonPath('data.contentBatch.finished', true);
});

test('пакет: ошибка модели по площадке попадает в отчёт, остальные проходят; без текста и без ИИ — отказ', function () {
    $project = contentAiProject();
    factoryPlatform($project, 'Telegram');
    $pub = factoryPub($project);
    $admin = contentAiAdmin($this, 'ok');
    fakeHttp(['api.openai.com/*' => Http::response(['error' => ['message' => 'bad key']], 401)]);

    $res = contentAs($this, $admin, FACTORY_BATCH, ['id' => $pub->id])->assertGraphQLErrorFree();
    $res->assertJsonPath('data.contentAiAdaptAll.done', 1)->assertJsonPath('data.contentAiAdaptAll.failed.0.error', 'Провайдер отклонил API-ключ. Проверьте ключ и права доступа.');
    expect(Publication::query()->where('parent_id', $pub->id)->count())->toBe(0);

    contentAs($this, $admin, FACTORY_BATCH, ['id' => factoryPub($project, ['body' => null])->id])
        ->assertGraphQLValidationError('publication_id', 'У публикации нет текста для адаптации.');

    // автор без подключённого ИИ
    \App\Models\TenantAiConnection::query()->delete();
    contentAs($this, contentMember('author'), FACTORY_BATCH, ['id' => $pub->id])->assertGraphQLErrorMessage('ИИ не подключён. Укажите API-ключ провайдера в настройках (раздел «ИИ») или обратитесь к администратору платформы.');
});

test('аудитория из библиотеки: в бренд-память идут название и описание, своё значение остаётся как есть', function () {
    $admin = contentAiAdmin($this, 'Текст.');
    $project = contentAiProject();
    $project->update(['audience' => 'Работа: владельцы квартир; Своя аудитория']);
    $m = new \App\Configs\Content\Models\Material(['kind' => 'audience', 'title' => 'Работа: владельцы квартир', 'body' => "## Портрет\n\n**28–45 лет**, делают ремонт"]);
    $m->tenant_id = $project->tenant_id;
    $m->save();

    contentAs($this, $admin, 'mutation ($i: ContentAiInput!) { contentAiGenerate(input: $i) { text } }', ['i' => ['action' => 'draft', 'project_id' => $project->id, 'title' => 'Т']])->assertGraphQLErrorFree();

    Http::assertSent(function ($r) {
        $body = json_encode($r->data(), JSON_UNESCAPED_UNICODE);

        return str_contains($body, 'Работа: владельцы квартир (') && str_contains($body, '28–45 лет') && str_contains($body, 'Своя аудитория') && ! str_contains($body, '**');
    });
});

test('боли аудитории: сохраняются JSON-ом, у публикации лишние боли отсекаются, в промпт идут отмеченные боли и вопросы', function () {
    $admin = contentAiAdmin($this, 'Текст.');
    $project = contentAiProject();

    $pains = json_encode([
        ['id' => 's1', 'title' => 'Боли частных риелторов', 'pains' => [
            ['id' => 'p1', 'title' => 'Нет потока клиентов', 'description' => 'Зависят от рекомендаций'],
            ['id' => 'p2', 'title' => 'Ручная рутина', 'description' => 'Публикации на 5 площадках'],
            ['title' => '', 'description' => ''],
        ], 'questions' => [['id' => 'q1', 'question' => 'Сколько стоит?', 'answer' => 'От 990 в месяц']]],
        ['id' => 's2', 'title' => 'Зависимость от площадок', 'pains' => [['id' => 'p3', 'title' => 'Блокировки', 'description' => 'Аккаунт могут закрыть']], 'questions' => [['id' => 'q2', 'question' => 'Другой раздел?', 'answer' => 'НЕ ДОЛЖНО ПОПАСТЬ']]],
    ], JSON_UNESCAPED_UNICODE);

    $saved = contentAs($this, $admin, 'mutation ($i: ContentMaterialInput!) { contentSaveMaterial(input: $i) { id pains } }', ['i' => ['kind' => 'audience', 'title' => 'Риелторы', 'pains' => $pains]])
        ->assertGraphQLErrorFree()->json('data.contentSaveMaterial');
    $stored = json_decode($saved['pains'], true);
    expect($stored)->toHaveCount(2)->and($stored[0]['pains'])->toHaveCount(2)->and($stored[0]['pains'][1]['id'])->toBe('p2');

    // чужой вид материала pains не хранит
    $note = contentAs($this, $admin, 'mutation ($i: ContentMaterialInput!) { contentSaveMaterial(input: $i) { pains } }', ['i' => ['kind' => 'note', 'title' => 'Н', 'pains' => $pains]])
        ->assertGraphQLErrorFree()->json('data.contentSaveMaterial.pains');
    expect($note)->toBeNull();

    $pub = contentAs($this, $admin, 'mutation ($i: ContentPublicationInput!) { contentSavePublication(input: $i) { id audience_id pain_ids } }', ['i' => [
        'project_id' => $project->id, 'title' => 'Пост', 'audience_id' => (int) $saved['id'], 'pain_ids' => ['p1', 'p3', 'нет-такой'],
    ]])->assertGraphQLErrorFree()->json('data.contentSavePublication');
    expect($pub['audience_id'])->toBe((int) $saved['id'])->and($pub['pain_ids'])->toBe(['p1', 'p3']);

    contentAs($this, $admin, 'mutation ($i: ContentAiInput!) { contentAiGenerate(input: $i) { text } }', ['i' => [
        'action' => 'draft', 'project_id' => $project->id, 'title' => 'Т', 'audience_id' => (int) $saved['id'], 'pain_ids' => ['p1'],
    ]])->assertGraphQLErrorFree();
    Http::assertSent(function ($r) {
        $b = json_encode($r->data(), JSON_UNESCAPED_UNICODE);

        return str_contains($b, 'БОЛИ АУДИТОРИИ') && str_contains($b, 'Нет потока клиентов') && str_contains($b, 'От 990 в месяц')
            && ! str_contains($b, 'Ручная рутина') && ! str_contains($b, 'НЕ ДОЛЖНО ПОПАСТЬ');
    });

    // смена аудитории на пустую сбрасывает боли
    $cleared = contentAs($this, $admin, 'mutation ($id: ID, $i: ContentPublicationInput!) { contentSavePublication(id: $id, input: $i) { audience_id pain_ids } }', ['id' => $pub['id'], 'i' => [
        'project_id' => $project->id, 'title' => 'Пост', 'audience_id' => null, 'pain_ids' => ['p1'],
    ]])->assertGraphQLErrorFree()->json('data.contentSavePublication');
    expect($cleared['audience_id'])->toBeNull()->and($cleared['pain_ids'])->toBe([]);
});

test('готовый промпт: без вызова модели и без подключённого ИИ, с бренд-памятью и болями', function () {
    fakeHttp(['*' => Http::response([], 500)]);
    $admin = createAdminUser();
    $project = contentAiProject();
    $m = new \App\Configs\Content\Models\Material(['kind' => 'audience', 'title' => 'Риелторы', 'pains' => [['id' => 's1', 'title' => 'Раздел', 'pains' => [['id' => 'p1', 'title' => 'Нет клиентов', 'description' => 'Ждут рекомендаций']], 'questions' => []]]]);
    $m->tenant_id = $project->tenant_id;
    $m->save();

    $p = contentAs($this, $admin, 'mutation ($i: ContentAiInput!) { contentAiPrompt(input: $i) }', ['i' => [
        'action' => 'draft', 'project_id' => $project->id, 'title' => 'Заголовок', 'instruction' => 'Про свет', 'audience_id' => $m->id, 'pain_ids' => ['p1'],
    ]])->assertGraphQLErrorFree()->json('data.contentAiPrompt');

    expect($p)->toContain('О ПРОЕКТЕ')->toContain('Нет клиентов')->toContain('Про свет')->toContain('---');
    Http::assertNothingSent();

    // как и в генерации, переписать без текста нельзя
    contentAs($this, $admin, 'mutation ($i: ContentAiInput!) { contentAiPrompt(input: $i) }', ['i' => ['action' => 'rewrite', 'project_id' => $project->id]])->assertJsonPath('errors.0.message', fn ($m) => $m !== null);
});
