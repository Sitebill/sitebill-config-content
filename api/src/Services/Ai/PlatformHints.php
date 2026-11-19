<?php

namespace App\Configs\Content\Services\Ai;

/** Описание площадки для промпта: тип, формат, аудитория, предел знаков. */
final class PlatformHints
{
    public static function block(array $platform): string
    {
        $lines = [];
        foreach (['name' => 'Площадка', 'kind' => 'Тип', 'formats' => 'Форматы', 'audience' => 'Аудитория площадки'] as $key => $label) {
            $v = trim((string) ($platform[$key] ?? ''));
            if ($v !== '') {
                $lines[] = "{$label}: " . mb_substr($v, 0, 200);
            }
        }
        if (! empty($platform['max_length'])) {
            $lines[] = 'Предел длины: ' . (int) $platform['max_length'] . ' знаков (не превышать)';
        }

        return $lines ? "ПЛОЩАДКА:\n" . implode("\n", $lines) : '';
    }

    /** Обрезка до предела площадки по границе слова — страховка, если модель превысила лимит. */
    public static function fit(string $text, array $platform): string
    {
        $max = (int) ($platform['max_length'] ?? 0);
        if ($max <= 0 || mb_strlen($text) <= $max) {
            return $text;
        }
        $cut = mb_substr($text, 0, $max);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space !== false && $space > $max * 0.6 ? mb_substr($cut, 0, $space) : $cut, ' ,;:-—');
    }
}
