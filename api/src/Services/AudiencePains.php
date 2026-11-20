<?php

namespace App\Configs\Content\Services;

use App\Configs\Content\Models\Material;

/**
 * «Боли аудитории» (docs/roadmap/CONTENT_FACTORY.md): разделы с таблицами «вид боли — описание» и «вопрос — ответ».
 * Структура: [{id, title, pains: [{id, title, description}], questions: [{id, question, answer}]}].
 * Приходит с клиента строкой JSON, поэтому перед записью всё чистится и ограничивается по размеру; в промпт ИИ
 * попадает только то, что отмечено в публикации.
 */
final class AudiencePains
{
    private const MAX_SECTIONS = 30;

    private const MAX_ROWS = 100;

    /** JSON-строка из формы → чистый массив (или null, если пусто/невалидно). */
    public static function sanitize(?string $json): ?array
    {
        if ($json === null || trim($json) === '') {
            return null;
        }
        $raw = json_decode($json, true);
        if (! is_array($raw)) {
            return null;
        }
        $str = fn ($v, int $max) => mb_substr(trim((string) $v), 0, $max);
        $id = fn ($v) => preg_match('/^[A-Za-z0-9_-]{1,24}$/', (string) $v) ? (string) $v : bin2hex(random_bytes(4));

        $sections = [];
        foreach (array_slice(array_values($raw), 0, self::MAX_SECTIONS) as $s) {
            if (! is_array($s)) {
                continue;
            }
            $pains = [];
            foreach (array_slice(array_values((array) ($s['pains'] ?? [])), 0, self::MAX_ROWS) as $p) {
                $p = (array) $p;
                $row = ['id' => $id($p['id'] ?? null), 'title' => $str($p['title'] ?? '', 200), 'description' => $str($p['description'] ?? '', 4000)];
                if ($row['title'] !== '' || $row['description'] !== '') {
                    $pains[] = $row;
                }
            }
            $questions = [];
            foreach (array_slice(array_values((array) ($s['questions'] ?? [])), 0, self::MAX_ROWS) as $q) {
                $q = (array) $q;
                $row = ['id' => $id($q['id'] ?? null), 'question' => $str($q['question'] ?? '', 500), 'answer' => $str($q['answer'] ?? '', 4000)];
                if ($row['question'] !== '' || $row['answer'] !== '') {
                    $questions[] = $row;
                }
            }
            $sections[] = ['id' => $id($s['id'] ?? null), 'title' => $str($s['title'] ?? '', 200), 'pains' => $pains, 'questions' => $questions];
        }

        return $sections ?: null;
    }

    /** Все id болей аудитории (для проверки отмеченных в публикации). */
    public static function painIds(?array $sections): array
    {
        $ids = [];
        foreach ($sections ?? [] as $s) {
            foreach ($s['pains'] ?? [] as $p) {
                $ids[] = (string) $p['id'];
            }
        }

        return $ids;
    }

    /**
     * Данные для промпта: аудитория, отмеченные боли и вопросы-ответы тех разделов, из которых боли отмечены.
     * null — если аудитория не выбрана или ничего не отмечено.
     */
    public static function forPrompt(?int $audienceId, array $painIds): ?array
    {
        if (! $audienceId || ! $painIds) {
            return null;
        }
        $m = Material::query()->where('kind', 'audience')->find($audienceId);
        if (! $m || ! is_array($m->pains)) {
            return null;
        }
        $pains = [];
        $qa = [];
        foreach ($m->pains as $s) {
            $hit = false;
            foreach ($s['pains'] ?? [] as $p) {
                if (in_array((string) $p['id'], $painIds, true)) {
                    $pains[] = ['section' => $s['title'] ?? '', 'title' => $p['title'], 'description' => $p['description']];
                    $hit = true;
                }
            }
            if ($hit) {
                foreach ($s['questions'] ?? [] as $q) {
                    $qa[] = ['question' => $q['question'], 'answer' => $q['answer']];
                }
            }
        }

        return $pains ? ['audience' => $m->title, 'pains' => $pains, 'qa' => $qa] : null;
    }
}
