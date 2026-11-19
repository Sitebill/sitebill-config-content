<?php

namespace App\Configs\Content\Services\Ai;

use App\Ai\AiException;

/** Разбор ответа модели, который обязан быть JSON-массивом объектов (идеи, план периода). */
final class JsonList
{
    /**
     * Достаёт первый JSON-массив из ответа (модель может обернуть его в ```json или приписать пояснение).
     *
     * @return list<array<string, mixed>>
     *
     * @throws AiException
     */
    public static function parse(string $raw): array
    {
        $start = strpos($raw, '[');
        $end = strrpos($raw, ']');
        $data = ($start !== false && $end !== false && $end > $start) ? json_decode(substr($raw, $start, $end - $start + 1), true) : null;

        if (! is_array($data)) {
            throw new AiException('Модель вернула ответ в неожиданном формате. Попробуйте ещё раз.');
        }

        return array_values(array_filter($data, 'is_array'));
    }
}
