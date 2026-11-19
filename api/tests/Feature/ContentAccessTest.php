<?php

use App\Configs\Content\Models\Member;
use App\Configs\Content\Models\Project;
use App\Configs\Content\Models\Publication;
use App\Models\Tenant;
use App\Models\User;

require_once __DIR__ . '/../Support.php';

// Контент-фабрика: роли команды, права на бэкенде и изоляция тенантов (docs/roadmap/CONTENT_FACTORY.md §5).

const CONTENT_SAVE_PUB = 'mutation ($i: ContentPublicationInput!) { contentSavePublication(input: $i) { id status } }';

test('guests are rejected and a plain site visitor has no access', function () {
    contentAs($this, null, '{ contentProjects { id } }')->assertGraphQLErrorMessage('Unauthenticated.');
    contentAs($this, User::factory()->create(), '{ contentProjects { id } }')->assertGraphQLErrorMessage('This action is unauthorized.');
});

test('the site admin is the owner and manages everything', function () {
    $admin = createAdminUser();
    contentAs($this, $admin, '{ contentMyRole }')->assertJsonPath('data.contentMyRole', 'owner');

    $id = contentAs($this, $admin, 'mutation { contentSaveProject(input: {name: "Бренд"}) { id } }')->json('data.contentSaveProject.id');
    expect(Project::query()->find($id)->tenant_id)->toBe(app('currentTenant')->id);
    contentAs($this, $admin, '{ contentMembers { id } }')->assertJsonPath('data.contentMembers', []);
});

test('roles get exactly their rights', function () {
    $admin = createAdminUser();
    $projectId = contentAs($this, $admin, 'mutation { contentSaveProject(input: {name: "Бренд"}) { id } }')->json('data.contentSaveProject.id');

    $viewer = contentMember('viewer');
    contentAs($this, $viewer, '{ contentProjects { id } }')->assertJsonCount(1, 'data.contentProjects');
    contentAs($this, $viewer, CONTENT_SAVE_PUB, ['i' => ['project_id' => (int) $projectId, 'title' => 'x']])
        ->assertGraphQLErrorMessage('This action is unauthorized.');

    $author = contentMember('author');
    contentAs($this, $author, CONTENT_SAVE_PUB, ['i' => ['project_id' => (int) $projectId, 'title' => 'Пост']])
        ->assertJsonPath('data.contentSavePublication.status', 'idea');
    contentAs($this, $author, 'mutation { contentSaveProject(input: {name: "Чужой"}) { id } }')
        ->assertGraphQLErrorMessage('This action is unauthorized.');
    contentAs($this, $author, '{ contentMembers { id } }')->assertGraphQLErrorMessage('This action is unauthorized.');

    $editor = contentMember('editor');
    contentAs($this, $editor, 'mutation { contentSaveProject(input: {name: "Второй"}) { name } }')->assertJsonPath('data.contentSaveProject.name', 'Второй');
});

test('only an approver or an editor can set the ready and published statuses', function () {
    $admin = createAdminUser();
    $projectId = (int) contentAs($this, $admin, 'mutation { contentSaveProject(input: {name: "Бренд"}) { id } }')->json('data.contentSaveProject.id');
    $pub = Publication::create(['project_id' => $projectId, 'title' => 'Пост', 'status' => 'review']);

    $move = 'mutation ($id: ID!, $s: String) { contentMovePublication(id: $id, status: $s) { status } }';
    $author = contentMember('author');
    contentAs($this, $author, $move, ['id' => $pub->id, 's' => 'ready'])->assertGraphQLValidationError('status', 'This status can only be set by an editor or an approver.');
    contentAs($this, $author, CONTENT_SAVE_PUB, ['i' => ['project_id' => $projectId, 'title' => 'Пост', 'status' => 'published']])
        ->assertGraphQLValidationError('input.status', 'This status can only be set by an editor or an approver.');
    contentAs($this, $author, $move, ['id' => $pub->id, 's' => 'in_work'])->assertJsonPath('data.contentMovePublication.status', 'in_work');

    contentAs($this, contentMember('approver'), $move, ['id' => $pub->id, 's' => 'ready'])->assertJsonPath('data.contentMovePublication.status', 'ready');
});

test('another tenant sees none of the data and cannot join the team', function () {
    $admin = createAdminUser();
    contentAs($this, $admin, 'mutation { contentSaveProject(input: {name: "Мой"}) { id } }');

    $other = Tenant::create(['name' => 'Другой', 'slug' => 'other-'.uniqid()]);
    // tenant_id не в $fillable (как у всех моделей модуля), поэтому задаём явно, иначе его подставит текущий тенант
    $foreignProject = new Project(['name' => 'Чужой']);
    $foreignProject->tenant_id = $other->id;
    $foreignProject->save();
    $foreignUser = User::factory()->create(['tenant_id' => $other->id]);

    contentAs($this, $admin, '{ contentProjects { name } }')->assertJsonCount(1, 'data.contentProjects')->assertJsonPath('data.contentProjects.0.name', 'Мой');
    // чужой проект для резолвера «не существует» (ModelNotFound → ошибка), и он остаётся на месте
    contentAs($this, $admin, 'mutation ($id: ID!) { contentDeleteProject(id: $id) }', ['id' => $foreignProject->id])->assertJsonPath('data', null);
    expect(Project::withoutGlobalScopes()->whereKey($foreignProject->id)->exists())->toBeTrue();
    contentAs($this, $admin, 'mutation ($u: ID!) { contentSaveMember(input: {user_id: $u, role: "author"}) { id } }', ['u' => $foreignUser->id])
        ->assertGraphQLValidationError('input.user_id', 'User not found on this site.');

    // админ чужого тенанта не получает доступа к нашему сайту
    $foreignAdmin = createAdminUser(['tenant_id' => $other->id]);
    contentAs($this, $foreignAdmin, '{ contentProjects { id } }')->assertGraphQLErrorMessage('This action is unauthorized.');
    expect(Project::withoutGlobalScopes()->where('tenant_id', $other->id)->count())->toBe(1);
});

test('the owner manages the team', function () {
    $admin = createAdminUser();
    $user = User::factory()->create();
    $save = 'mutation ($u: ID!, $r: String!) { contentSaveMember(input: {user_id: $u, role: $r}) { role user_id } }';

    contentAs($this, $admin, $save, ['u' => $user->id, 'r' => 'author'])->assertJsonPath('data.contentSaveMember.role', 'author');
    contentAs($this, $admin, $save, ['u' => $user->id, 'r' => 'editor'])->assertJsonPath('data.contentSaveMember.role', 'editor');
    expect(Member::query()->count())->toBe(1);
    contentAs($this, $admin, $save, ['u' => $admin->id, 'r' => 'author'])->assertGraphQLValidationError('input.user_id', 'The site admin is always the owner.');

    contentAs($this, $admin, 'mutation ($u: ID!) { contentRemoveMember(user_id: $u) }', ['u' => $user->id])->assertJsonPath('data.contentRemoveMember', true);
    expect(Member::query()->count())->toBe(0);
});

test('demo data fills an empty workspace once', function () {
    $admin = createAdminUser();
    contentAs($this, $admin, 'mutation { contentSeedDemo(locale: "ru") }')->assertJsonPath('data.contentSeedDemo', true);
    expect(Project::query()->count())->toBeGreaterThan(0);
    contentAs($this, $admin, 'mutation { contentSeedDemo }')->assertJsonPath('data.contentSeedDemo', false);
});

test('a team member gets config_role in /user so the frontend can show the content menu', function () {
    // запрос заново определяет тенанта из БД, поэтому тип сохраняем, а не меняем в памяти
    app('currentTenant')->forceFill(['app_type' => 'content'])->save();

    $this->actingAs(contentMember('author'), 'web')->getJson('/api/v1/user')->assertOk()->assertJsonPath('user.config_role', 'author');
    $this->actingAs(User::factory()->create(), 'web')->getJson('/api/v1/user')->assertOk()->assertJsonPath('user.config_role', null);

    // на тенанте другого типа конфигурация не участвует вообще
    app('currentTenant')->forceFill(['app_type' => 'real_estate'])->save();
    $this->actingAs(contentMember('author'), 'web')->getJson('/api/v1/user')->assertOk()->assertJsonPath('user.config_role', null);
});
