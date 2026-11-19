<?php

namespace App\Configs\Content\Services;

use App\Configs\Content\Models\Placement;
use App\Configs\Content\Models\Publication;

/**
 * The overall state of a publication is calculated from its placements (one per channel):
 * every channel handled -> published, some of them -> partial, none -> still waiting.
 */
final class PublicationState
{
    /** none (no channels chosen) | pending | partial | done. Skipped channels do not count. */
    public static function placementState(iterable $placements): string
    {
        $active = [];
        foreach ($placements as $p) {
            if ($p->status !== 'skipped') {
                $active[] = $p;
            }
        }
        if (! $active) {
            return 'none';
        }
        $published = count(array_filter($active, fn ($p) => $p->status === 'published'));
        if ($published === 0) {
            return 'pending';
        }

        return $published === count($active) ? 'done' : 'partial';
    }

    /** Brings Publication::status in line with the placements. A publication without a status stays as it is. */
    public static function sync(Publication $publication): Publication
    {
        if ($publication->status === 'none') {
            return $publication;
        }
        $placements = Placement::query()->where('publication_id', $publication->id)->get();
        $state = self::placementState($placements);
        // Все площадки закрыты -> «опубликован»; иначе опубликованное не выше «готов к публикации».
        $next = match ($state) {
            'done' => 'published',
            default => $publication->status === 'published' ? 'ready' : $publication->status,
        };
        if ($next !== $publication->status) {
            $publication->status = $next;
            $publication->save();
        }

        return $publication;
    }
}
