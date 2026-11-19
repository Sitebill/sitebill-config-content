<?php

namespace App\Configs\Content\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Ход пакетной операции (адаптация на все площадки): общий счётчик между задачами очереди и интерфейсом.
 * Живёт в кэше, а не в таблице: это временное состояние на минуты, история остаётся в журнале ИИ и в созданных версиях.
 * Ключ включает тенанта, чужой тенант чужой пакет не увидит.
 */
final class BatchProgress
{
    private const TTL = 3600;

    public static function start(int $tenantId, string $id, int $total): void
    {
        Cache::put(self::key($tenantId, $id), ['total' => $total, 'done' => 0, 'failed' => [], 'created' => []], self::TTL);
    }

    /** @param  array{publication_id?: int, platform_id: int, error?: string}  $item */
    public static function record(int $tenantId, string $id, bool $ok, array $item): void
    {
        Cache::lock(self::key($tenantId, $id) . ':lock', 5)->block(3, function () use ($tenantId, $id, $ok, $item) {
            $state = Cache::get(self::key($tenantId, $id));
            if (! $state) {
                return;
            }
            $state['done']++;
            if ($ok) {
                $state['created'][] = $item['publication_id'];
            } else {
                $state['failed'][] = ['platform_id' => $item['platform_id'], 'error' => $item['error'] ?? ''];
            }
            Cache::put(self::key($tenantId, $id), $state, self::TTL);
        });
    }

    /** @return array{total: int, done: int, failed: list<array>, created: list<int>, finished: bool}|null */
    public static function get(int $tenantId, string $id): ?array
    {
        $state = Cache::get(self::key($tenantId, $id));

        return $state ? $state + ['finished' => $state['done'] >= $state['total']] : null;
    }

    private static function key(int $tenantId, string $id): string
    {
        return "content.batch.{$tenantId}.{$id}";
    }
}
