<?php

namespace App\Configs\Content\Demo;

use App\Configs\Content\Models\Comment;
use App\Configs\Content\Models\Event;
use App\Configs\Content\Models\Material;
use App\Configs\Content\Models\Member;
use App\Configs\Content\Models\Placement;
use App\Configs\Content\Models\Platform;
use App\Configs\Content\Models\Project;
use App\Configs\Content\Models\Publication;
use App\Configs\Content\Services\DemoData;
use App\Models\Tenant;
use App\Services\Demo\DemoProfile;
use App\Services\Demo\DemoTenantResetter;
use Carbon\CarbonImmutable;
use Closure;

/**
 * Демо «Контент-фабрика»: два проекта с планом вокруг даты сброса (те же данные, что кнопка «Демо-данные»), публичные
 * учётки по ролям команды. Участников команды создаёт linkAccounts — его же вызывает часовое восстановление учёток.
 */
class ContentDemoProfile implements DemoProfile
{
    public function key(): string
    {
        return 'content';
    }

    public function label(): string
    {
        return config('demo.profiles.content.label');
    }

    public function description(): string
    {
        return config('demo.profiles.content.description');
    }

    public function accounts(): array
    {
        return config('demo.profiles.content.accounts');
    }

    public function tenantSettings(): array
    {
        return config('demo.profiles.content.tenant_settings');
    }

    public function configureSite(Tenant $tenant): void
    {
    }

    /** Порядок — от дочерних таблиц к родительским. Комментарии посетителей демо тоже уходят. */
    public function wipe(Tenant $tenant, ?Closure $log): void
    {
        foreach ([Comment::class, Placement::class, Publication::class, Event::class, Material::class, Platform::class, Project::class, Member::class] as $model) {
            $model::withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();
        }
        $log && $log('Контент-фабрика: данные удалены');
    }

    public function seed(Tenant $tenant, array $accountUsers, DemoTenantResetter $resetter, bool $images, ?Closure $log): array
    {
        app()->instance('currentTenant', $tenant);
        (new DemoData())->seed(true, CarbonImmutable::today());
        $this->linkAccounts($tenant, $accountUsers);

        return [
            'projects' => Project::query()->count(), 'publications' => Publication::query()->count(), 'members' => Member::query()->count(),
        ];
    }

    /** Роль участника = поле `member_role` учётки в config/demo.php (admin — владелец сайта, членство ему не нужно). */
    public function linkAccounts(Tenant $tenant, array $accountUsers): void
    {
        foreach ($this->accounts() as $key => $account) {
            $user = $accountUsers[$key] ?? null;
            $role = $account['member_role'] ?? null;
            if (! $user || ! $role) {
                continue;
            }
            $member = Member::withoutGlobalScopes()->firstOrNew(['tenant_id' => $tenant->id, 'user_id' => $user->id]);
            $member->role = $role;
            $member->tenant_id = $tenant->id;
            $member->save();
        }
    }
}
