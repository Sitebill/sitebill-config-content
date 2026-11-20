<?php

namespace SitebillApps\Rumantic\ContentManager\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Sitebill\AppSdk\Host;

/**
 * Права приложения: всё делает администратор сайта (он же владелец). Команды с ролями в приложении нет —
 * она живёт в конфигурации `content` платформы; способности Gate свои (`cm.*`), чтобы не пересекаться с конфигом.
 */
final class ContentAccess
{
    public const OWNER = 'owner';

    private const ABILITIES = ['cm.read', 'cm.write', 'cm.move', 'cm.approve', 'cm.manage', 'cm.team'];

    public const APPROVED_STATUSES = ['ready', 'published'];

    public static function roleOf(?Authenticatable $user): ?string
    {
        return Host::users()->isAdmin($user) ? self::OWNER : null;
    }

    public static function can(?Authenticatable $user, string $ability): bool
    {
        return in_array($ability, self::ABILITIES, true) && self::roleOf($user) !== null;
    }

    /** @return list<string> */
    public static function abilities(): array
    {
        return self::ABILITIES;
    }
}
