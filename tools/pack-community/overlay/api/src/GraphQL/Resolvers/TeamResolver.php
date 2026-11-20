<?php

namespace SitebillApps\Rumantic\ContentManager\GraphQL\Resolvers;

use Illuminate\Support\Facades\Auth;
use SitebillApps\Rumantic\ContentManager\Support\ContentAccess;

/** В приложении команды нет: админ сайта — владелец. Резолвер оставлен ради запроса contentMyRole, на котором стоит интерфейс. */
final class TeamResolver
{
    public function myRole(): string
    {
        return ContentAccess::roleOf(Auth::user()) ?? 'viewer';
    }
}
