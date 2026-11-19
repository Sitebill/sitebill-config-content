<?php

namespace App\Configs\Content;

use App\Configs\Content\Support\ContentAccess;
use App\Support\ConfigMemberRoles;
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
}
