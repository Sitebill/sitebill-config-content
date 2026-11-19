<?php

namespace App\Configs\Content\Services;

use Carbon\CarbonImmutable;
use App\Configs\Content\Models\Platform;
use App\Configs\Content\Models\Placement;
use App\Configs\Content\Models\Project;
use App\Configs\Content\Models\Publication;

/**
 * The assistant of the app. It is rule based and works offline: it reads the project card, the plan and the
 * placements and answers with findings and proposals. It never saves anything by itself; the owner accepts a
 * proposal in the UI. An LLM provider can later be put behind the same methods (see docs in the README).
 */
final class PlanAgent
{
    /** Finished or handled elsewhere: partly placed items are reported by the "unplaced" finding instead. */
    private const DONE = ['published', 'none'];

    // ------------------------------------------------------------------ review of the plan

    /** @return list<array{code: string, severity: string, publication_id: ?int, meta: ?string}> */
    public function review(?int $projectId, CarbonImmutable $today): array
    {
        $pubs = Publication::query()->when($projectId, fn ($q) => $q->where('project_id', $projectId))->get();
        $platforms = Platform::query()->when($projectId, fn ($q) => $q->where('project_id', $projectId))->get()->keyBy('id');
        $placements = Placement::query()->whereIn('publication_id', $pubs->pluck('id'))->get()->groupBy('publication_id');
        $out = [];
        $add = function (string $code, string $severity, ?int $pubId = null, ?string $meta = null) use (&$out) {
            $out[] = ['code' => $code, 'severity' => $severity, 'publication_id' => $pubId, 'meta' => $meta];
        };
        $day = fn (Publication $p) => $p->planned_date ? CarbonImmutable::parse($p->planned_date)->startOfDay() : null;

        // 1. Overdue: the date has passed and the material is not fully published.
        foreach ($pubs->filter(fn ($p) => $p->planned_date && $day($p) < $today && ! in_array($p->status, self::DONE, true))->sortBy('planned_date')->take(8) as $p) {
            $add('overdue', 'bad', $p->id, $p->title);
        }

        // 2. Not prepared: due within three days but still only an idea or a plan.
        foreach ($pubs->filter(fn ($p) => $p->planned_date && $day($p) >= $today && $day($p) <= $today->addDays(3) && in_array($p->status, ['idea', 'draft', 'in_work'], true))->sortBy('planned_date')->take(8) as $p) {
            $add('unprepared', 'warn', $p->id, $p->title);
        }

        // 4. Placed on some channels but not on all.
        foreach ($pubs->filter(fn ($p) => collect($placements->get($p->id, []))->contains(fn ($pl) => $pl->status === 'published'))->take(40) as $p) {
            $left = collect($placements->get($p->id, []))->filter(fn ($pl) => ! in_array($pl->status, ['published', 'skipped'], true));
            if ($left->isNotEmpty()) {
                $names = $left->map(fn ($pl) => $platforms->get($pl->platform_id)?->name)->filter()->implode(', ');
                $add('unplaced', 'warn', $p->id, $p->title.'|'.$names);
            }
        }

        // 5. Rubrics that were not touched for two weeks.
        $byRubric = $pubs->filter(fn ($p) => $p->rubric && $p->planned_date && $p->status !== 'none')->groupBy('rubric');
        foreach ($byRubric as $rubric => $items) {
            $last = $items->map(fn ($p) => $day($p))->max();
            if ($last && $last < $today->subDays(14)) {
                $add('stale_rubric', 'info', null, (string) $rubric);
            }
        }

        // 6. Days without content in the next week.
        $empty = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $today->addDays($i);
            if (! $pubs->contains(fn ($p) => $p->status !== 'none' && $p->planned_date && $day($p)->equalTo($d))) {
                $empty[] = $d->toDateString();
            }
        }
        if ($empty) {
            $add('empty_days', count($empty) >= 4 ? 'warn' : 'info', null, count($empty).'|'.implode(',', array_slice($empty, 0, 7)));
        }

        // 7. Too many publications of the same kind in a row, or in total.
        $recent = $pubs->filter(fn ($p) => $p->planned_date && $p->status !== 'none' && $day($p) >= $today->subDays(14) && $day($p) <= $today->addDays(14))
            ->sortBy('planned_date')->pluck('kind')->values();
        if ($recent->count() >= 5) {
            $share = $recent->countBy()->sortDesc();
            $kind = $share->keys()->first();
            $run = 0;
            $maxRun = 0;
            foreach ($recent as $i => $k) {
                $run = ($i > 0 && $recent[$i - 1] === $k) ? $run + 1 : 1;
                $maxRun = max($maxRun, $run);
            }
            if ($maxRun >= 4 || $share->first() / $recent->count() > 0.7) {
                $add('monotony', 'info', null, (string) $kind);
            }
        }

        // 8. Plan completion for the last 30 days.
        $window = $pubs->filter(fn ($p) => $p->planned_date && $day($p) >= $today->subDays(30) && $day($p) < $today && $p->status !== 'none');
        if ($window->count() >= 4) {
            $pct = (int) round($window->where('status', 'published')->count() * 100 / $window->count());
            if ($pct < 60) {
                $add('low_plan', $pct < 40 ? 'bad' : 'warn', null, (string) $pct);
            }
        }

        // 9. The channel where most placements stay unpublished.
        $stats = [];
        foreach ($placements->flatten() as $pl) {
            $stats[$pl->platform_id]['all'] = ($stats[$pl->platform_id]['all'] ?? 0) + ($pl->status === 'skipped' ? 0 : 1);
            $stats[$pl->platform_id]['done'] = ($stats[$pl->platform_id]['done'] ?? 0) + ($pl->status === 'published' ? 1 : 0);
        }
        $weak = collect($stats)->filter(fn ($s) => $s['all'] >= 4)->map(fn ($s) => $s['done'] / $s['all'])->sort();
        if ($weak->count() > 1 && $weak->first() < 0.5 && $platforms->has($weak->keys()->first())) {
            $add('weak_channel', 'info', null, $platforms[$weak->keys()->first()]->name.'|'.(int) round($weak->first() * 100));
        }

        return $out;
    }

    // ------------------------------------------------------------------ ideas and plans

    /** @return list<array<string, mixed>> */
    public function ideas(Project $project, int $count, string $locale): array
    {
        $ru = str_starts_with($locale, 'ru');
        $existing = Publication::query()->where('project_id', $project->id)->pluck('title')->map(fn ($t) => mb_strtolower($t))->all();
        $topic = trim((string) ($project->topic ?: $project->name));
        $kws = array_values(array_filter(array_map('trim', explode(',', (string) $project->keywords))));
        $aud = trim((string) $project->audience) ?: ($ru ? 'вашей аудитории' : 'your audience');
        mt_srand(crc32($project->id.'|'.count($existing)));
        $pool = $this->templates($ru);
        shuffle($pool);

        $out = [];
        foreach ($pool as $i => [$rubric, $kind, $title, $hook]) {
            $kw = $kws ? $kws[($i + count($out)) % count($kws)] : $topic;
            $fill = fn (string $s) => strtr($s, ['{topic}' => $topic, '{kw}' => $kw, '{name}' => $project->name, '{aud}' => $aud]);
            $t = $fill($title);
            if (in_array(mb_strtolower($t), $existing, true)) {
                continue;
            }
            $out[] = [
                'title' => $t,
                'kind' => $kind,
                'rubric' => $rubric,
                'body' => $this->draft($fill($hook), $project, $ru),
                'hashtags' => $this->hashtags($kws, $topic),
                'planned_date' => null,
                'planned_time' => null,
                'platform_ids' => [],
            ];
            if (count($out) >= $count) {
                break;
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function plan(Project $project, int $days, string $locale, CarbonImmutable $today): array
    {
        $platforms = Platform::query()->where('project_id', $project->id)->orderBy('id')->get();
        $perWeek = max(1, (int) ($platforms->max('per_week') ?: 3));
        $total = max(1, min(14, (int) round($perWeek * $days / 7)));
        $busy = Publication::query()->where('project_id', $project->id)->where('status', '!=', 'none')
            ->whereBetween('planned_date', [$today->toDateString(), $today->addDays($days - 1)->toDateString()])->pluck('planned_date')
            ->map(fn ($d) => CarbonImmutable::parse($d)->toDateString())->all();
        $free = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $today->addDays($i)->toDateString();
            if (! in_array($d, $busy, true)) {
                $free[] = $d;
            }
        }
        if (! $free) {
            return [];
        }
        $ideas = $this->ideas($project, $total, $locale);
        $quota = [];
        foreach ($platforms as $p) {
            $quota[$p->id] = $p->per_week === null ? 3 : max(0, (int) round($p->per_week * $days / 7));
        }
        $times = ['10:00', '12:30', '18:00'];
        $step = count($free) / max(1, count($ideas));
        foreach ($ideas as $i => &$idea) {
            $idea['planned_date'] = $free[min(count($free) - 1, (int) floor($i * $step))];
            $idea['planned_time'] = $times[$i % 3];
            arsort($quota);
            $chosen = array_slice(array_keys(array_filter($quota, fn ($q) => $q > 0)), 0, 2);
            foreach ($chosen as $id) {
                $quota[$id]--;
            }
            $idea['platform_ids'] = array_values($chosen ?: ($platforms->isNotEmpty() ? [$platforms->first()->id] : []));
        }

        return $ideas;
    }

    // ------------------------------------------------------------------ adaptation

    /** @return array<string, mixed> */
    public function adapt(Publication $source, Platform $platform, string $locale): array
    {
        $ru = str_starts_with($locale, 'ru');
        $body = trim((string) $source->body);
        $paras = array_values(array_filter(array_map('trim', preg_split('/\R{2,}/u', $body) ?: [])));
        $first = $paras[0] ?? $source->title;
        $tags = $this->limitTags((string) $source->hashtags, in_array($platform->kind, ['telegram', 'vk', 'ok'], true) ? 5 : 3);
        $link = $source->link ? "\n".$source->link : '';
        $cta = $ru ? 'Напишите нам — подскажем, с чего начать.' : 'Write to us and we will help you get started.';
        $max = $platform->max_length ?: null;

        [$title, $text] = match ($platform->kind) {
            'telegram', 'ok' => [$source->title, $this->cut(implode("\n\n", array_slice($paras ?: [$first], 0, 2)), $max ?: 900)."\n\n".$cta.$link."\n\n".$tags],
            'vk' => [$source->title, $this->cut($body ?: $first, $max ?: 1800)."\n\n".$cta.$link."\n\n".$tags],
            'tiktok', 'youtube', 'rutube' => [
                ($ru ? 'Видео: ' : 'Video: ').$source->title,
                ($ru ? "Хук (первые 3 секунды): " : "Hook (first 3 seconds): ").$this->cut($first, 120)."\n"
                .($ru ? "Суть в трёх пунктах:\n" : "Three points:\n").$this->bullets($paras, $ru)."\n"
                .($ru ? "Призыв: " : "Call to action: ").$cta."\n\n".$tags,
            ],
            'email' => [
                ($ru ? 'Тема письма: ' : 'Subject: ').$source->title,
                ($ru ? "Здравствуйте!\n\n" : "Hello!\n\n").$this->cut($body ?: $first, $max ?: 1500)."\n\n".$cta.$link,
            ],
            'dzen', 'habr', 'vcru', 'blog', 'site' => [
                $source->title,
                ($ru ? "Коротко: " : "In short: ").$this->cut($first, 220)."\n\n".($body ?: $first)."\n\n"
                .($ru ? "Вывод. " : "Conclusion. ").$cta.$link,
            ],
            default => [$source->title, $this->cut($body ?: $first, $max ?: 1200).$link],
        };
        if ($max && $platform->kind !== 'email') {
            $text = $this->cut($text, $max);
        }

        return [
            'title' => $this->cut($title, 200),
            'kind' => in_array($platform->kind, ['tiktok', 'youtube', 'rutube'], true) ? 'video' : $source->kind,
            'rubric' => $source->rubric,
            'body' => $text,
            'hashtags' => $tags,
            'planned_date' => $source->planned_date ? CarbonImmutable::parse($source->planned_date)->toDateString() : null,
            'planned_time' => $source->planned_time,
            'platform_ids' => [$platform->id],
        ];
    }

    // ------------------------------------------------------------------ text helpers

    /** Cuts at a sentence or word boundary and never splits a multibyte character. */
    public function cut(string $text, int $max): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $head = mb_substr($text, 0, max(1, $max - 1));
        $pos = max((int) mb_strrpos($head, '. '), (int) mb_strrpos($head, "\n"), (int) mb_strrpos($head, ' '));

        return rtrim(mb_substr($head, 0, $pos > $max * 0.5 ? $pos : $max - 1), " ,;:-").'…';
    }

    private function bullets(array $paras, bool $ru): string
    {
        $items = array_slice(array_slice($paras, 1) ?: $paras, 0, 3);
        $items = array_map(fn ($p) => '— '.$this->cut($p, 90), $items);
        while (count($items) < 3) {
            $items[] = '— '.($ru ? 'ещё один важный шаг' : 'one more important step');
        }

        return implode("\n", $items);
    }

    private function limitTags(string $tags, int $n): string
    {
        $list = array_values(array_filter(preg_split('/[\s,]+/u', trim($tags)) ?: []));

        return implode(' ', array_slice($list, 0, $n));
    }

    private function hashtags(array $kws, string $topic): string
    {
        $words = array_slice($kws ?: [$topic], 0, 4);

        return implode(' ', array_map(fn ($w) => '#'.preg_replace('/[^\p{L}\p{N}_]+/u', '', mb_strtolower($w)), $words));
    }

    private function draft(string $hook, Project $project, bool $ru): string
    {
        $tone = $project->tone ? ($ru ? "Тон: {$project->tone}.\n\n" : "Tone: {$project->tone}.\n\n") : '';

        return $ru
            ? "{$hook}\n\n{$tone}— Главная мысль в одном предложении.\n— Пример из практики.\n— Что можно сделать уже сегодня.\n\nНапишите нам, если хотите разобрать вашу ситуацию."
            : "{$hook}\n\n{$tone}— The main idea in one sentence.\n— An example from practice.\n— What you can do today.\n\nWrite to us if you want to go through your case.";
    }

    /** @return list<array{0: string, 1: string, 2: string, 3: string}> rubric, kind, title, opening line */
    private function templates(bool $ru): array
    {
        return $ru ? [
            ['Советы', 'post', '5 ошибок, которые все делают с темой «{topic}»', 'Разбираем пять частых ошибок в теме «{topic}» и как их избежать.'],
            ['За кадром', 'article', 'Как мы работаем с «{kw}»: закулисье', 'Показываем, как на самом деле устроена работа над «{kw}».'],
            ['Мифы', 'post', '«{kw}»: миф и реальность', 'Один популярный миф про «{kw}» и что происходит на самом деле.'],
            ['Кейсы', 'case', 'Кейс: как клиент получил результат с «{topic}»', 'История клиента: задача, решение и результат в цифрах.'],
            ['Чек-листы', 'post', 'Чек-лист: что проверить перед стартом в теме «{topic}»', 'Короткий чек-лист для тех, кто для {aud} только начинает.'],
            ['Вовлечение', 'post', 'Вопрос недели: что вас беспокоит в теме «{topic}»?', 'Спрашиваем аудиторию и собираем темы для следующих материалов.'],
            ['Факты', 'news', '«{topic}» в цифрах: три факта, которые удивляют', 'Три коротких факта, которые меняют взгляд на «{topic}».'],
            ['Видео', 'video', 'Видео за 60 секунд: «{kw}»', 'Сценарий короткого ролика: хук, три мысли, призыв.'],
            ['Вопросы и ответы', 'post', 'Отвечаем на вопросы про «{topic}»', 'Собрали самые частые вопросы {aud} и ответили на них.'],
            ['Новости', 'announcement', 'Что нового в «{name}» в этом месяце', 'Коротко о том, что изменилось у нас за месяц.'],
        ] : [
            ['Tips', 'post', '5 mistakes everyone makes with {topic}', 'Five common mistakes around {topic} and how to avoid them.'],
            ['Behind the scenes', 'article', 'How we work on {kw}: behind the scenes', 'A look at how work on {kw} really goes.'],
            ['Myths', 'post', '{kw}: myth vs. reality', 'One popular myth about {kw} and what is really going on.'],
            ['Cases', 'case', 'Case: how a client got results with {topic}', 'A client story: the task, the solution and the result in numbers.'],
            ['Checklists', 'post', 'Checklist: what to check before you start with {topic}', 'A short checklist for {aud} who are just starting.'],
            ['Engagement', 'post', 'Question of the week: what worries you about {topic}?', 'We ask the audience and collect topics for the next materials.'],
            ['Facts', 'news', '{topic} in numbers: three facts that surprise', 'Three short facts that change how you see {topic}.'],
            ['Video', 'video', 'A 60-second video: {kw}', 'A script for a short clip: hook, three points, call to action.'],
            ['Q&A', 'post', 'Answering your questions about {topic}', 'We collected the most frequent questions from {aud} and answered them.'],
            ['News', 'announcement', "What's new in {name} this month", 'A short note about what changed for us this month.'],
        ];
    }
}
