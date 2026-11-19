<?php

use App\Configs\Content\Models\Member;
use App\Configs\Content\Models\Project;
use App\Configs\Content\Models\Publication;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Demo\DemoTenantResetter;

// Демо-профиль «Контент-фабрика»: полный сброс сеет проекты и план, участников команды по ролям; повтор не копит данные.

test('сброс демо создаёт план и команду по ролям и повторяем', function () {
    $tenant = app('currentTenant');
    $tenant->forceFill(['demo_profile' => 'content', 'is_default' => false])->save();
    $tenant = Tenant::find($tenant->id);

    $stats = (new DemoTenantResetter)->resetFull($tenant, false);
    expect($stats['projects'])->toBe(2)->and($stats['publications'])->toBeGreaterThan(10)->and($stats['members'])->toBe(3);

    expect($tenant->fresh()->app_type)->toBe('content');
    $roles = Member::query()->with('user:id,email')->get()->mapWithKeys(fn ($m) => [explode('@', $m->user->email)[0] => $m->role])->all();
    expect($roles)->toBe(['editor' => 'editor', 'author' => 'author', 'approver' => 'approver']);
    // Владелец (admin) в команде не числится: он всегда владелец сайта.
    expect(User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('email', 'like', 'admin@%')->exists())->toBeTrue();

    (new DemoTenantResetter)->resetFull($tenant, false);
    expect(Project::count())->toBe(2)->and(Member::count())->toBe(3)->and(Publication::count())->toBe($stats['publications']);
}, 60);

test('часовое восстановление учёток возвращает роли участников', function () {
    $tenant = app('currentTenant');
    $tenant->forceFill(['demo_profile' => 'content', 'is_default' => false])->save();
    $tenant = Tenant::find($tenant->id);
    (new DemoTenantResetter)->resetFull($tenant, false);

    Member::query()->delete();
    (new DemoTenantResetter)->restoreAccounts($tenant);
    expect(Member::count())->toBe(3);
}, 60);
