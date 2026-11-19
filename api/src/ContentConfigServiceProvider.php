<?php

namespace App\Configs\Content;

use App\Configs\Content\Support\ContentAccess;
use App\Configs\Content\Models\Member;
use App\Configs\Content\Models\Project;
use App\Entitlements\EntitlementCatalog;
use App\Models\Tenant;
use App\Support\ConfigMemberRoles;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Точка входа backend-части конфигурации `content` — контент-фабрика (docs/roadmap/CONTENT_FACTORY.md).
 * Регистрируется ядром через запись 'provider' в apps/api/config/configurations.php.
 */
class ContentConfigServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerEntitlements();
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Участник команды без роли admin: фронт покажет ему меню конфигурации (config_role в ответе /user).
        ConfigMemberRoles::register(fn ($user) => app()->bound('currentTenant') && app('currentTenant')->app_type === 'content'
            ? ContentAccess::roleOf($user)
            : null);

        // Способности для @can(ability: "content.*") в схеме: без model Lighthouse вызывает Gate::check(ability).
        foreach (ContentAccess::abilities() as $ability) {
            Gate::define($ability, fn ($user) => ContentAccess::can($user, $ability));
        }
    }

    /** Лимиты тарифа конфигурации: проекты и участники команды (проверяются в резолверах через assertCanCreate). */
    private function registerEntitlements(): void
    {
        foreach ([
            ['content_projects_max', 'Контент-фабрика: проекты', 'content_projects', Project::class],
            ['content_members_max', 'Контент-фабрика: участники команды', 'content_members', Member::class],
        ] as [$key, $label, $table, $model]) {
            EntitlementCatalog::register([
                'key' => $key,
                'type' => EntitlementCatalog::TYPE_LIMIT,
                'group' => 'Контент-фабрика',
                'module' => 'content',
                'label' => $label,
                'unit' => 'шт.',
                'usage' => fn (Tenant $t): int => Schema::hasTable($table) ? (int) $model::withoutGlobalScopes()->where('tenant_id', $t->id)->count() : 0,
            ]);
        }
    }
}
