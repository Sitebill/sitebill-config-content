<?php

namespace App\Configs\Content\Services\Ai;

use Carbon\CarbonImmutable;

/**
 * План публикаций на период: даты, время, рубрики и площадки с учётом желаемой частоты на площадку (`per_week`),
 * уже запланированного и «важных» дат. Это предложение: ничего не пишется в план, пока пользователь не примет.
 * Ответ — JSON-массив; даты и площадки проверяются здесь, чтобы модель не вывела план за период или на чужую площадку.
 */
final class PeriodPlanFeature extends ContentFeature
{
    public function key(): string
    {
        return 'content.period_plan';
    }

    protected function task(array $input): string
    {
        return 'Составь план публикаций на период: распредели темы по датам и площадкам так, чтобы выдержать желаемую частоту, '
            . 'чередуй форматы и рубрики, не ставь две сложные публикации в один день, учитывай «важные» даты и уже запланированное. '
            . 'Для каждой записи — дата, время, заголовок, формат, рубрика, завязка (1–2 предложения) и номер площадки из списка.';
    }

    protected function answerFormat(): string
    {
        return 'Ответь ТОЛЬКО JSON-массивом объектов вида {"date": "YYYY-MM-DD", "time": "HH:MM", "title": "...", "kind": "post|article|news|case|video|announcement", "rubric": "...", "hook": "...", "platform_id": 123} — без пояснений и markdown.';
    }

    protected function userBlocks(array $input): array
    {
        $platforms = array_map(
            fn ($p) => "#{$p['id']} {$p['name']} ({$p['kind']})" . (! empty($p['per_week']) ? ", желаемо {$p['per_week']} в неделю" : ''),
            array_slice((array) ($input['platforms'] ?? []), 0, 20),
        );
        $busy = array_map(fn ($b) => "{$b['date']}: " . mb_substr((string) $b['title'], 0, 80), array_slice((array) ($input['busy'] ?? []), 0, 60));
        $events = array_map(fn ($e) => "{$e['date']}: " . mb_substr((string) $e['title'], 0, 80), array_slice((array) ($input['events'] ?? []), 0, 30));

        return [
            "ПЕРИОД: с {$input['from']} по {$input['to']} включительно",
            $platforms ? "ПЛОЩАДКИ:\n" . implode("\n", $platforms) : '',
            $busy ? "УЖЕ ЗАПЛАНИРОВАНО:\n" . implode("\n", $busy) : '',
            $events ? "ВАЖНЫЕ ДАТЫ:\n" . implode("\n", $events) : '',
        ];
    }

    public function maxTokens(): int
    {
        return 3000;
    }

    /** Оставляет записи внутри периода, с известной площадкой и допустимым форматом; не больше 60. */
    public function postProcess(string $raw, array $input): string
    {
        $from = CarbonImmutable::parse($input['from'])->startOfDay();
        $to = CarbonImmutable::parse($input['to'])->endOfDay();
        $platformIds = array_map(fn ($p) => (int) $p['id'], (array) ($input['platforms'] ?? []));

        $out = [];
        foreach (JsonList::parse($raw) as $row) {
            $title = trim((string) ($row['title'] ?? ''));
            try {
                $date = CarbonImmutable::createFromFormat('Y-m-d', (string) ($row['date'] ?? ''))->startOfDay();
            } catch (\Throwable) {
                continue;
            }
            if ($title === '' || $date->lt($from) || $date->gt($to)) {
                continue;
            }
            $pid = (int) ($row['platform_id'] ?? 0);
            $out[] = [
                'date' => $date->toDateString(),
                'time' => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) ($row['time'] ?? '')) ? $row['time'] : null,
                'title' => mb_substr($title, 0, 200),
                'kind' => in_array($row['kind'] ?? null, IdeasFeature::KINDS, true) ? $row['kind'] : 'post',
                'rubric' => ($r = trim((string) ($row['rubric'] ?? ''))) !== '' ? mb_substr($r, 0, 80) : null,
                'hook' => mb_substr(trim((string) ($row['hook'] ?? '')), 0, 600),
                'platform_id' => in_array($pid, $platformIds, true) ? $pid : null,
            ];
        }
        usort($out, fn ($a, $b) => [$a['date'], $a['time']] <=> [$b['date'], $b['time']]);

        return json_encode(array_slice($out, 0, 60), JSON_UNESCAPED_UNICODE);
    }
}
