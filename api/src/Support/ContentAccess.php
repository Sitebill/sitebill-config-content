<?php

namespace App\Configs\Content\Support;

use App\Configs\Content\Models\Member;
use App\Models\User;

/**
 * Роли команды и права контент-фабрики. Админ сайта (роль `admin` ядра в своём тенанте) — всегда `owner`;
 * остальным роль выдаёт `content_members`. Проверки вызываются из Gate-способностей провайдера, поэтому права
 * держит бэкенд, а не меню фронта (главное правило 2).
 */
final class ContentAccess
{
    public const OWNER = 'owner';

    /** способность → роли, которым она разрешена */
    private const ABILITIES = [
        'content.read' => ['owner', 'editor', 'author', 'approver', 'viewer'],
        // публикации, материалы, «Важное», размещения
        'content.write' => ['owner', 'editor', 'author'],
        // быстрые правки статуса и даты с доски/календаря (согласующий двигает материал, но не редактирует текст)
        'content.move' => ['owner', 'editor', 'author', 'approver'],
        // перевод в «готово»/«опубликовано» (согласование)
        'content.approve' => ['owner', 'editor', 'approver'],
        // проекты, площадки, удаление, демо-данные
        'content.manage' => ['owner', 'editor'],
        // состав команды
        'content.team' => ['owner'],
    ];

    /** Статусы, которые автор поставить сам не может: их ставит согласующий или редактор. */
    public const APPROVED_STATUSES = ['ready', 'published'];

    public static function roleOf(?User $user): ?string
    {
        if (! $user || ! app()->bound('currentTenant')) {
            return null;
        }
        if ((int) $user->tenant_id !== (int) app('currentTenant')->id) {
            return null; // пользователь другого тенанта и платформенный юзер сюда не попадают
        }
        if ($user->hasRole('admin')) {
            return self::OWNER;
        }

        return Member::query()->where('user_id', $user->id)->value('role');
    }

    public static function can(?User $user, string $ability): bool
    {
        $role = self::roleOf($user);

        return $role !== null && in_array($role, self::ABILITIES[$ability] ?? [], true);
    }

    /** @return list<string> */
    public static function abilities(): array
    {
        return array_keys(self::ABILITIES);
    }
}
