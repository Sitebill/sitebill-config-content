<?php

use App\Configs\Content\Models\Member;
use App\Configs\Content\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Http;

// Общие помощники тестов контент-фабрики: подключаются через require_once, чтобы тесты шли в любом порядке и по --filter.

/** GraphQL под пользователем; graphQL() у тестового класса protected, поэтому вызываем в его контексте. */
function contentAs(object $test, ?User $user, string $query, array $vars = [])
{
    return (fn () => ($user ? $this->actingAs($user, 'web') : $this)->graphQL($query, $vars))->call($test);
}

/** Пользователь текущего тенанта с ролью в команде. */
function contentMember(string $role): User
{
    $user = User::factory()->create();
    Member::create(['user_id' => $user->id, 'role' => $role]);

    return $user;
}

const CONTENT_AI_SAVE = 'mutation ($input: AiConnectionInput!) { saveMyAiConnection(input: $input) { status { available } } }';

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
