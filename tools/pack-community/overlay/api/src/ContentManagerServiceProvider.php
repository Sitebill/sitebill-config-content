<?php

namespace SitebillApps\Rumantic\ContentManager;

use Illuminate\Support\Facades\Gate;
use Sitebill\AppSdk\AppServiceProvider;
use SitebillApps\Rumantic\ContentManager\Support\ContentAccess;

/**
 * Права для @can(ability: "content.*") в схеме: Lighthouse без model вызывает Gate::check(ability).
 * В приложении все способности — у администратора сайта (см. Support\ContentAccess).
 */
final class ContentManagerServiceProvider extends AppServiceProvider
{
    protected function bootApp(): void
    {
        foreach (ContentAccess::abilities() as $ability) {
            Gate::define($ability, fn ($user) => ContentAccess::can($user, $ability));
        }
    }
}
