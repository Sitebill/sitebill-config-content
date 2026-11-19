<?php

namespace App\Configs\Content\Services;

use App\Configs\Content\Models\Platform;
use App\Configs\Content\Models\Project;

/** Набор площадок, который каждый новый проект получает сразу. */
final class DefaultPlatforms
{
    public const LIST = [['dzen', 'Дзен'], ['vk', 'ВКонтакте'], ['avtor_today', 'Автор.тудей'], ['max', 'Макс'], ['ok', 'Одноклассники'], ['vcru', 'vc.ru'], ['workspace', 'workspace.ru'], ['tenchat', 'TenChat'], ['looky', 'Looky']];

    public static function ensure(Project $project): void
    {
        $have = Platform::query()->where('project_id', $project->id)->pluck('kind')->all();
        foreach (self::LIST as [$kind, $name]) {
            if (! in_array($kind, $have, true)) {
                Platform::create(['project_id' => $project->id, 'name' => $name, 'kind' => $kind, 'api_status' => 'manual']);
            }
        }
    }
}
