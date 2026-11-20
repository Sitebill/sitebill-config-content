<?php

use App\Configs\Content\Models\Material;
use App\Configs\Content\Models\Publication;
use App\Mcp\McpTokens;
use App\Models\Tenant;
use App\Models\User;

require_once __DIR__ . '/../Support.php';

// MCP-tools контент-фабрики на общем /mcp: видимость по роли и токену, запись через те же резолверы, что и GraphQL.

function cmcpCall(string $method, array $params = [], ?string $token = null)
{
    $request = test()->withHeaders(['Accept' => 'application/json, text/event-stream']);
    if ($token) {
        $request = $request->withToken($token);
    }

    return $request->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params]);
}

function cmcpTool(string $name, array $arguments, string $token): array
{
    $result = cmcpCall('tools/call', ['name' => $name, 'arguments' => (object) $arguments], $token)->assertOk()->json('result');

    return ['error' => (bool) ($result['isError'] ?? false), 'payload' => json_decode($result['content'][0]['text'] ?? 'null', true) ?? ($result['content'][0]['text'] ?? null)];
}

/** Вызов закрыт: скрытый tool фреймворк отвечает JSON-RPC-ошибкой, а недопустимое действие — isError. */
function cmcpBlocked(string $name, array $arguments, string $token): bool
{
    $r = cmcpCall('tools/call', ['name' => $name, 'arguments' => (object) $arguments], $token)->json();

    return isset($r['error']) || (bool) ($r['result']['isError'] ?? false);
}

function cmcpNames(string $token): array
{
    return collect(cmcpCall('tools/list', [], $token)->assertOk()->json('result.tools'))->pluck('name')->filter(fn ($n) => str_starts_with($n, 'content_'))->values()->all();
}

function cmcpToken(User|Tenant $owner, array $abilities = McpTokens::ALL): string
{
    return $owner->createToken(McpTokens::NAME_PREFIX . ' test', $abilities)->plainTextToken;
}

beforeEach(function () {
    $this->tenant = Tenant::where('is_default', true)->firstOrFail();
    $this->tenant->update(['mcp_enabled' => true, 'app_type' => 'content']);
    app()->instance('currentTenant', $this->tenant->fresh());
    $this->admin = createAdminUser(['tenant_id' => $this->tenant->id]);
    $this->adminToken = cmcpToken($this->admin);
});

const CMCP_READS = ['content_overview', 'content_list_publications', 'content_get_publication', 'content_list_materials', 'content_get_material', 'content_plan_review'];

test('tools/list: владелец видит всё, роли — по своим правам, токен чтения и тенантский ключ — без записи', function () {
    $all = cmcpNames($this->adminToken);
    expect($all)->toContain(...[...CMCP_READS, 'content_build_prompt', 'content_save_publication', 'content_decide', 'content_save_project', 'content_save_platform', 'content_save_material']);

    $author = contentMember('author');
    $names = cmcpNames(cmcpToken($author));
    expect($names)->toContain('content_save_publication', 'content_add_comment', 'content_save_material')
        ->not->toContain('content_decide', 'content_save_project', 'content_save_platform');

    $approver = cmcpNames(cmcpToken(contentMember('approver')));
    expect($approver)->toContain('content_decide', 'content_move_publication', 'content_add_comment')->not->toContain('content_save_publication', 'content_build_prompt');

    expect(cmcpNames(cmcpToken(contentMember('viewer'))))->toBe(array_values(array_intersect($all, CMCP_READS)));
    expect(cmcpNames(cmcpToken($this->admin, [McpTokens::READ])))->toBe(array_values(array_intersect($all, [...CMCP_READS, 'content_build_prompt'])));
    expect(cmcpNames(cmcpToken($this->tenant)))->toBeEmpty();
});

test('простой пользователь сайта и чужой тип тенанта: tools скрыты и не вызываются', function () {
    $visitor = cmcpToken(User::factory()->create(['tenant_id' => $this->tenant->id]));
    expect(cmcpNames($visitor))->toBeEmpty();
    $r = cmcpCall('tools/call', ['name' => 'content_overview', 'arguments' => (object) []], $visitor)->json();
    expect(isset($r['error']) || ($r['result']['isError'] ?? false))->toBeTrue();

    $this->tenant->update(['app_type' => 'real_estate']);
    expect(cmcpNames($this->adminToken))->toBeEmpty();
});

test('публикация через MCP: создание, правка части полей, аудитория с болями, перенос, размещение, чтение', function () {
    $project = contentAiProject();
    $aud = cmcpTool('content_save_material', [
        'kind' => 'audience', 'title' => 'Риелторы', 'body' => 'Частные риелторы',
        'pains' => [['title' => 'Боли', 'pains' => [['title' => 'Нет клиентов', 'description' => 'Ждут рекомендаций']], 'questions' => [['question' => 'Цена?', 'answer' => 'От 990']]]],
    ], $this->adminToken);
    expect($aud['error'])->toBeFalse();
    $audId = $aud['payload']['material']['id'];
    $painId = $aud['payload']['material']['pains'][0]['pains'][0]['id'];

    $new = cmcpTool('content_save_publication', ['project_id' => $project->id, 'title' => 'Пост про клиентов', 'body' => "## Привет\n\nТекст", 'audience_id' => $audId, 'pain_ids' => [$painId, 'левая'], 'planned_date' => '2026-12-01'], $this->adminToken);
    expect($new['error'])->toBeFalse()->and($new['payload']['publication']['status'])->toBe('idea')->and($new['payload']['publication']['pain_ids'])->toBe([$painId]);
    $id = $new['payload']['publication']['id'];

    // правка только заголовка: текст, аудитория и боли остаются; пустая строка очищает рубрику
    Publication::query()->whereKey($id)->update(['rubric' => 'Старая']);
    $upd = cmcpTool('content_save_publication', ['id' => $id, 'title' => 'Новый заголовок', 'rubric' => ''], $this->adminToken)['payload']['publication'];
    expect($upd['title'])->toBe('Новый заголовок')->and($upd['body'])->toContain('Привет')->and($upd['rubric'])->toBeNull()->and($upd['pain_ids'])->toBe([$painId]);

    $moved = cmcpTool('content_move_publication', ['id' => $id, 'status' => 'review', 'planned_date' => '2026-12-05'], $this->adminToken)['payload']['publication'];
    expect($moved['status'])->toBe('review')->and($moved['planned_date'])->toBe('2026-12-05');

    $platformId = \App\Configs\Content\Models\Platform::query()->where('project_id', $project->id)->value('id')
        ?? cmcpTool('content_save_platform', ['project_id' => $project->id, 'name' => 'ТГ', 'kind' => 'telegram'], $this->adminToken)['payload']['platform']['id'];
    $pl = cmcpTool('content_set_placement', ['publication_id' => $id, 'platform_id' => $platformId, 'status' => 'published', 'url' => 'https://t.me/x/1'], $this->adminToken);
    expect($pl['error'])->toBeFalse()->and($pl['payload']['publication']['placements'][0]['url'])->toBe('https://t.me/x/1');

    $get = cmcpTool('content_get_publication', ['id' => $id], $this->adminToken)['payload'];
    expect($get['selected_pains'][0]['title'])->toBe('Нет клиентов')->and($get['audience']['title'])->toBe('Риелторы');

    $list = cmcpTool('content_list_publications', ['audience_id' => $audId], $this->adminToken)['payload'];
    expect($list['total'])->toBe(1)->and($list['publications'][0])->not->toHaveKey('body');

    $prompt = cmcpTool('content_build_prompt', ['action' => 'draft', 'publication_id' => $id], $this->adminToken)['payload']['prompt'];
    expect($prompt)->toContain('БОЛИ АУДИТОРИИ')->toContain('Нет клиентов');
});

test('права ролей и процесс согласования через MCP', function () {
    $project = contentAiProject();
    $authorToken = cmcpToken(contentMember('author'));
    $approverToken = cmcpToken(contentMember('approver'));

    $id = cmcpTool('content_save_publication', ['project_id' => $project->id, 'title' => 'Текст автора', 'status' => 'review'], $authorToken)['payload']['publication']['id'];

    // автор не ставит ready ни при сохранении, ни при переносе
    expect(cmcpTool('content_save_publication', ['id' => $id, 'status' => 'ready'], $authorToken)['error'])->toBeTrue();
    expect(cmcpTool('content_move_publication', ['id' => $id, 'status' => 'published'], $authorToken)['error'])->toBeTrue();
    expect(Publication::query()->find($id)->status)->toBe('review');

    // решение: возврат требует пояснения, автор решать не может (tool скрыт)
    expect(cmcpTool('content_decide', ['publication_id' => $id, 'decision' => 'return'], $approverToken)['error'])->toBeTrue();
    $r = cmcpCall('tools/call', ['name' => 'content_decide', 'arguments' => (object) ['publication_id' => $id, 'decision' => 'approve']], $authorToken)->json();
    expect(isset($r['error']) || ($r['result']['isError'] ?? false))->toBeTrue()->and(Publication::query()->find($id)->status)->toBe('review');

    expect(cmcpTool('content_decide', ['publication_id' => $id, 'decision' => 'return', 'comment' => 'Добавьте цифры'], $approverToken)['error'])->toBeFalse();
    expect(Publication::query()->find($id)->status)->toBe('in_work');
    cmcpTool('content_add_comment', ['publication_id' => $id, 'body' => 'Поправил'], $authorToken);
    $discussion = cmcpTool('content_get_publication', ['id' => $id], $authorToken)['payload']['discussion'];
    expect(collect($discussion)->pluck('kind')->all())->toBe(['return', 'comment']);

    // токен «только чтение» не пишет, даже у владельца
    $ro = cmcpToken($this->admin, [McpTokens::READ]);
    expect(cmcpBlocked('content_save_publication', ['project_id' => $project->id, 'title' => 'x'], $ro))->toBeTrue()
        ->and(Publication::query()->where('title', 'x')->exists())->toBeFalse();
});

test('аудитория чужого вида: pains только у audience; материалы и проект сохраняются, лимиты тарифа действуют', function () {
    expect(cmcpTool('content_save_material', ['kind' => 'note', 'title' => 'Н', 'pains' => [['title' => 'x']]], $this->adminToken)['error'])->toBeTrue();

    $p = cmcpTool('content_save_project', ['name' => 'Бренд', 'tone' => 'Спокойный'], $this->adminToken);
    expect($p['error'])->toBeFalse()->and($p['payload']['project']['tone'])->toBe('Спокойный');
    $upd = cmcpTool('content_save_project', ['id' => $p['payload']['project']['id'], 'topic' => 'Интерьер'], $this->adminToken)['payload']['project'];
    expect($upd['name'])->toBe('Бренд')->and($upd['topic'])->toBe('Интерьер');

    $ov = cmcpTool('content_overview', [], $this->adminToken)['payload'];
    expect($ov['role'])->toBe('owner')->and($ov['projects'])->toHaveCount(1)->and($ov['guide'])->toContain('Статусы');
    expect(Material::query()->count())->toBe(0);
});
