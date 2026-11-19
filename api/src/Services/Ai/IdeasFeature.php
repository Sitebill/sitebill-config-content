<?php

namespace App\Configs\Content\Services\Ai;

/** Идеи публикаций для проекта: с учётом уже вышедшего (не повторяться) и рубрик. Ответ — JSON-массив. */
final class IdeasFeature extends ContentFeature
{
    public const KINDS = ['post', 'article', 'news', 'case', 'video', 'announcement'];

    public function key(): string
    {
        return 'content.ideas';
    }

    protected function task(array $input): string
    {
        $n = max(1, min(12, (int) ($input['count'] ?? 6)));

        return "Предложи {$n} идей публикаций для проекта: разные форматы и рубрики, конкретные темы, а не общие слова. "
            . 'Не повторяй то, что уже есть в плане. Каждая идея — заголовок и короткая завязка (1–2 предложения: о чём и чем полезно).';
    }

    protected function answerFormat(): string
    {
        return 'Ответь ТОЛЬКО JSON-массивом объектов вида {"title": "...", "kind": "post|article|news|case|video|announcement", "rubric": "...", "hook": "..."} — без пояснений и markdown.';
    }

    protected function userBlocks(array $input): array
    {
        $existing = array_slice((array) ($input['existing_titles'] ?? []), 0, 40);
        $rubrics = array_slice((array) ($input['rubrics'] ?? []), 0, 20);

        return [
            $existing ? "УЖЕ ЕСТЬ В ПЛАНЕ (не повторять):\n- " . implode("\n- ", array_map(fn ($t) => mb_substr((string) $t, 0, 120), $existing)) : '',
            $rubrics ? 'ИСПОЛЬЗУЕМЫЕ РУБРИКИ: ' . implode(', ', array_map(fn ($r) => mb_substr((string) $r, 0, 60), $rubrics)) : '',
        ];
    }

    public function maxTokens(): int
    {
        return 1800;
    }

    /** Нормализует ответ в JSON: только известные поля и форматы, не более N идей. */
    public function postProcess(string $raw, array $input): string
    {
        $out = [];
        foreach (JsonList::parse($raw) as $row) {
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $out[] = [
                'title' => mb_substr($title, 0, 200),
                'kind' => in_array($row['kind'] ?? null, self::KINDS, true) ? $row['kind'] : 'post',
                'rubric' => ($r = trim((string) ($row['rubric'] ?? ''))) !== '' ? mb_substr($r, 0, 80) : null,
                'hook' => mb_substr(trim((string) ($row['hook'] ?? '')), 0, 600),
            ];
        }

        return json_encode(array_slice($out, 0, max(1, min(12, (int) ($input['count'] ?? 6)))), JSON_UNESCAPED_UNICODE);
    }
}
