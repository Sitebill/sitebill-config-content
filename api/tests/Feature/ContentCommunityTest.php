<?php

use App\Community\SiteProfile;
use App\Configs\Content\Models\Member;
use App\Configs\Content\Models\Project;
use App\Entitlements\EntitlementCatalog;
use App\Models\User;

require_once __DIR__ . '/../Support.php';

// Фаза 4: лимиты тарифа конфигурации и установочный профиль Community `content`.

test('лимит тарифа блокирует третий проект, правка существующего не считается', function () {
    $admin = createAdminUser();
    $tenant = app('currentTenant');
    $tenant->update(['entitlements' => ['limits' => ['content_projects_max' => 1]]]);

    $id = contentAs($this, $admin, 'mutation { contentSaveProject(input: {name: "Первый"}) { id } }')->assertGraphQLErrorFree()->json('data.contentSaveProject.id');
    contentAs($this, $admin, 'mutation { contentSaveProject(input: {name: "Второй"}) { id } }')
        ->assertGraphQLErrorMessage('Достигнут лимит тарифа: 1 (Контент-фабрика: проекты). Перейдите на платный тариф, чтобы добавить больше.');
    contentAs($this, $admin, 'mutation ($id: ID) { contentSaveProject(id: $id, input: {name: "Переименован"}) { name } }', ['id' => $id])
        ->assertJsonPath('data.contentSaveProject.name', 'Переименован');
    expect(Project::count())->toBe(1);
    $tenant->update(['entitlements' => null]);
});

test('лимит участников: новый не добавляется, смена роли существующего проходит', function () {
    $admin = createAdminUser();
    $tenant = app('currentTenant');
    $first = User::factory()->create();
    $second = User::factory()->create();
    $save = 'mutation ($u: ID!, $r: String!) { contentSaveMember(input: {user_id: $u, role: $r}) { role } }';
    $tenant->update(['entitlements' => ['limits' => ['content_members_max' => 1]]]);

    contentAs($this, $admin, $save, ['u' => $first->id, 'r' => 'author'])->assertGraphQLErrorFree();
    contentAs($this, $admin, $save, ['u' => $second->id, 'r' => 'author'])->assertGraphQLErrorMessage('Достигнут лимит тарифа: 1 (Контент-фабрика: участники команды). Перейдите на платный тариф, чтобы добавить больше.');
    contentAs($this, $admin, $save, ['u' => $first->id, 'r' => 'editor'])->assertJsonPath('data.contentSaveMember.role', 'editor');
    expect(Member::count())->toBe(1);
    $tenant->update(['entitlements' => null]);
});

test('ключи конфигурации есть в каталоге тарифов и считают использование', function () {
    expect(EntitlementCatalog::isLimit('content_projects_max'))->toBeTrue()->and(EntitlementCatalog::isLimit('content_members_max'))->toBeTrue();
    $tenant = app('currentTenant');
    $p = new Project(['name' => 'X']);
    $p->tenant_id = $tenant->id;
    $p->save();
    expect(EntitlementCatalog::get('content_projects_max')['usage']($tenant))->toBe(1);
});

test('профиль content доступен только при установке и закрепляет app_type', function () {
    $tenant = app('currentTenant');
    $tenant->forceFill(['app_type' => 'real_estate'])->save();

    expect(SiteProfile::modules())->toContain('content')
        ->and(SiteProfile::all())->not->toContain('content')       // не предлагается в «Настройках», мастере и манифестах
        ->and(SiteProfile::isValid('content'))->toBeFalse()
        ->and(SiteProfile::isInstallable('content'))->toBeTrue()
        ->and(SiteProfile::isInstallable('example'))->toBeFalse();  // без community_profile в реестре

    expect(SiteProfile::apply($tenant, 'content'))->toBeTrue()->and($tenant->fresh()->app_type)->toBe('content');
    // Сайт уже на другой конфигурации — профиль им не управляет, смена на realestate/blank невозможна.
    expect(SiteProfile::apply($tenant->fresh(), 'blank'))->toBeFalse();
    expect(fn () => SiteProfile::apply($tenant->fresh(), 'nonsense'))->toThrow(InvalidArgumentException::class);
});
