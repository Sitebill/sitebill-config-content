<?php

namespace App\Configs\Content\Services;

use Carbon\CarbonImmutable;
use App\Configs\Content\Models\Event;
use App\Configs\Content\Models\Material;
use App\Configs\Content\Models\Placement;
use App\Configs\Content\Models\Platform;
use App\Configs\Content\Models\Project;
use App\Configs\Content\Models\Publication;

/** Two sample projects with a plan around "today", so the dashboard and the review have something to say. */
final class DemoData
{
    public function seed(bool $ru, CarbonImmutable $today): void
    {
        $t = fn (string $r, string $e) => $ru ? $r : $e;
        mt_srand(7);

        $lenProject = Project::create([
            'name' => $t('Студия интерьеров «Лён»', 'Linen interior studio'),
            'description' => $t('Небольшая студия, которая делает спокойные интерьеры для квартир и домов.', 'A small studio making calm interiors for apartments and houses.'),
            'topic' => $t('интерьер квартиры', 'home interiors'),
            'audience' => $t('владельцы квартир 28–45 лет, делающие ремонт', 'apartment owners aged 28–45 planning a renovation'),
            'tone' => $t('спокойный, экспертный, без пафоса', 'calm, expert, no hype'),
            'keywords' => $t('ремонт, дизайн интерьера, планировка, свет', 'renovation, interior design, floor plan, lighting'),
            'competitors' => $t('студии района, маркетплейсы мебели', 'local studios, furniture marketplaces'),
            'manager' => $t('Анна Крылова', 'Anna Brooks'),
            'color' => '#10b981',
        ]);
        $timeflow = Project::create([
            'name' => $t('Таймфлоу — сервис тайм-трекинга', 'Timeflow — time tracking SaaS'),
            'description' => $t('SaaS для небольших команд: учёт времени, отчёты и счета клиентам.', 'SaaS for small teams: time tracking, reports and client invoices.'),
            'topic' => $t('учёт рабочего времени', 'time tracking'),
            'audience' => $t('руководители студий и фриланс-команд', 'heads of studios and freelance teams'),
            'tone' => $t('дружелюбный, по делу, с примерами', 'friendly, to the point, with examples'),
            'keywords' => $t('тайм-трекинг, продуктивность, отчёты, фриланс', 'time tracking, productivity, reports, freelance'),
            'competitors' => 'Toggl, Clockify',
            'manager' => $t('Игорь Власов', 'Ian Carter'),
            'color' => '#6366f1',
        ]);

        $mk = function (Project $p, string $name, string $kind, string $url, ?int $max, int $perWeek, string $audience, string $formats, string $api = 'manual') {
            return Platform::create(['project_id' => $p->id, 'name' => $name, 'kind' => $kind, 'url' => $url, 'max_length' => $max, 'per_week' => $perWeek, 'audience' => $audience, 'formats' => $formats, 'api_status' => $api]);
        };
        $len = [
            'tg' => $mk($lenProject, 'Telegram', 'telegram', 'https://t.me/example_len', 1500, 4, $t('подписчики, 3 200', 'subscribers, 3,200'), $t('пост, фото', 'post, photo'), 'connected'),
            'vk' => $mk($lenProject, $t('ВКонтакте', 'VK'), 'vk', 'https://vk.com/example_len', 2500, 3, $t('сообщество, 5 800', 'community, 5,800'), $t('пост, альбом', 'post, album')),
            'dz' => $mk($lenProject, $t('Дзен', 'Zen'), 'dzen', 'https://dzen.ru/example_len', null, 1, $t('читатели статей', 'article readers'), $t('статья', 'article')),
            'blog' => $mk($lenProject, $t('Блог на сайте', 'Site blog'), 'blog', 'https://example.com/blog', null, 1, $t('посетители сайта', 'site visitors'), $t('статья, кейс', 'article, case')),
        ];
        $tf = [
            'tg' => $mk($timeflow, 'Telegram', 'telegram', 'https://t.me/example_timeflow', 1200, 3, $t('подписчики, 1 900', 'subscribers, 1,900'), $t('пост', 'post'), 'connected'),
            'vc' => $mk($timeflow, 'VC.ru', 'vcru', 'https://vc.ru/u/example', null, 1, $t('предприниматели', 'entrepreneurs'), $t('статья', 'article')),
            'habr' => $mk($timeflow, 'Habr', 'habr', 'https://habr.com/ru/users/example/', null, 1, $t('разработчики', 'developers'), $t('статья', 'article')),
            'mail' => $mk($timeflow, $t('Рассылка', 'Newsletter'), 'email', 'https://example.com/newsletter', 3000, 1, $t('подписчики, 840', 'subscribers, 840'), $t('письмо', 'email')),
        ];

        // [project, day offset, time, kind, rubric, status, priority, assignee, platforms, title, body]
        $rows = [
            // Linen studio
            [$lenProject, -12, '10:00', 'post', $t('Советы', 'Tips'), 'published', 'normal', $t('Анна', 'Anna'), ['tg', 'vk'], $t('Как выбрать свет для маленькой кухни', 'How to choose lighting for a small kitchen'), $t("Три слоя света решают всё: общий, рабочий и акцентный.\n\nНачните с рабочей зоны над столешницей.", "Three layers of light do the job: general, task and accent.\n\nStart with the work area above the counter.")],
            [$lenProject, -10, '12:30', 'article', $t('Кейсы', 'Cases'), 'published', 'high', $t('Анна', 'Anna'), ['blog', 'dz', 'tg'], $t('Кейс: двушка 54 м² за 3 месяца', 'Case: a 54 m² two-room flat in 3 months'), $t("Задача: превратить тёмную двушку в светлую квартиру для семьи.\n\nРешение: перенесли одну перегородку и добавили зонированный свет.", "The task: turn a dark flat into a bright home for a family.\n\nThe solution: we moved one partition and added zoned lighting.")],
            [$lenProject, -8, '18:00', 'post', $t('Мифы', 'Myths'), 'published', 'normal', $t('Анна', 'Anna'), ['tg', 'vk'], $t('Миф: светлые стены делают комнату больше', 'Myth: light walls make a room bigger'), $t("Не всегда. Размер комнаты меняют пропорции и свет, а не цвет.", "Not always. Proportions and light change the size of a room, not colour.")],
            [$lenProject, -6, '10:00', 'post', $t('Чек-листы', 'Checklists'), 'partial', 'normal', $t('Анна', 'Anna'), ['tg', 'vk', 'blog'], $t('Чек-лист: что проверить перед ремонтом', 'Checklist: what to check before a renovation'), $t("1. Замеры.\n2. Электрика.\n3. Бюджет с запасом 15%.", "1. Measurements.\n2. Wiring.\n3. A budget with a 15% reserve.")],
            [$lenProject, 4, '12:30', 'article', $t('За кадром', 'Behind the scenes'), 'approved', 'normal', $t('Анна', 'Anna'), ['dz', 'blog'], $t('Как мы собираем мудборд для клиента', 'How we build a mood board for a client'), $t("Мудборд — это договор о вкусе. Показываем, как к нему приходим.", "A mood board is an agreement about taste. Here is how we reach it.")],
            [$lenProject, -2, '18:00', 'post', $t('Вовлечение', 'Engagement'), 'in_work', 'high', $t('Анна', 'Anna'), ['tg', 'vk'], $t('Вопрос недели: какая комната вас раздражает больше всего?', 'Question of the week: which room annoys you the most?'), ''],
            [$lenProject, 0, '10:00', 'post', $t('Советы', 'Tips'), 'review', 'high', $t('Анна', 'Anna'), ['tg', 'vk'], $t('5 ошибок при выборе дивана', '5 mistakes when choosing a sofa'), $t("Размер, глубина, обивка, каркас и ножки: на чём чаще всего экономят зря.", "Size, depth, upholstery, frame and legs: where people save money in vain.")],
            [$lenProject, 1, '12:30', 'post', $t('Факты', 'Facts'), 'draft_ready', 'normal', $t('Анна', 'Anna'), ['tg'], $t('Интерьер в цифрах: 3 факта о высоте потолков', 'Interiors in numbers: 3 facts about ceiling height'), $t("Стандартные 2,5 м — не приговор: рассказываем, как визуально поднять потолок.", "A standard 2.5 m is not a sentence: how to lift a ceiling visually.")],
            [$lenProject, 2, '18:00', 'video', $t('Видео', 'Video'), 'in_work', 'normal', $t('Игорь', 'Ian'), ['vk'], $t('Видео за 60 секунд: как расставить мебель в студии', 'A 60-second video: how to arrange furniture in a studio'), ''],
            [$lenProject, 3, '10:00', 'article', $t('Кейсы', 'Cases'), 'planned', 'normal', $t('Анна', 'Anna'), ['blog', 'dz'], $t('Кейс: кухня-гостиная 28 м²', 'Case: a 28 m² kitchen-living room'), ''],
            [$lenProject, 5, '12:30', 'post', $t('Вопросы и ответы', 'Q&A'), 'planned', 'low', '', ['tg', 'vk'], $t('Отвечаем на вопросы про перепланировку', 'Answering questions about re-planning'), ''],
            [$lenProject, 7, '18:00', 'post', $t('Новости', 'News'), 'idea', 'low', '', [], $t('Что нового в студии в этом месяце', "What's new in the studio this month"), ''],
            [$lenProject, 9, '10:00', 'post', $t('Мифы', 'Myths'), 'idea', 'normal', '', [], $t('Миф: дорогой ремонт — всегда качественный', 'Myth: an expensive renovation is always a good one'), ''],
            // Timeflow
            [$timeflow, -13, '11:00', 'article', $t('Кейсы', 'Cases'), 'published', 'high', $t('Игорь', 'Ian'), ['vc', 'habr', 'tg'], $t('Как студия из 8 человек вернула 11 часов в неделю', 'How an 8-person studio got 11 hours a week back'), $t("Команда вела учёт времени в таблицах. Рассказываем, что изменилось после перехода на трекер.", "The team tracked time in spreadsheets. Here is what changed after moving to a tracker.")],
            [$timeflow, -9, '10:00', 'post', $t('Советы', 'Tips'), 'published', 'normal', $t('Игорь', 'Ian'), ['tg', 'mail'], $t('3 привычки, которые делают отчёты точными', '3 habits that make reports accurate'), $t("Фиксируйте старт сразу, называйте задачи глаголами, закрывайте день.", "Start the timer at once, name tasks with verbs, close the day.")],
            [$timeflow, -7, '17:00', 'post', $t('Мифы', 'Myths'), 'partial', 'normal', $t('Игорь', 'Ian'), ['tg', 'vc'], $t('Миф: тайм-трекинг — это слежка', 'Myth: time tracking is surveillance'), $t("Мы считаем, что учёт времени нужен прежде всего самой команде.", "We believe time tracking is first of all for the team itself.")],
            [$timeflow, 1, '10:00', 'post', $t('Новости', 'News'), 'scheduled', 'normal', $t('Игорь', 'Ian'), ['tg'], $t('В Таймфлоу появились счета клиентам', 'Timeflow now has client invoices'), $t("Выставляйте счета прямо из отчёта по времени.", "Invoice straight from a time report.")],
            [$timeflow, -1, '12:00', 'article', $t('За кадром', 'Behind the scenes'), 'review', 'normal', $t('Игорь', 'Ian'), ['habr'], $t('Как мы храним время в UTC и не путаемся', 'How we store time in UTC and stay sane'), $t("Разбираем три ошибки с часовыми поясами и как мы их закрыли.", "Three time-zone bugs and how we closed them.")],
            [$timeflow, 0, '17:00', 'post', $t('Чек-листы', 'Checklists'), 'in_work', 'high', $t('Игорь', 'Ian'), ['tg', 'mail'], $t('Чек-лист: как внедрить тайм-трекинг в команде за неделю', 'Checklist: roll out time tracking in a team within a week'), ''],
            [$timeflow, 2, '10:00', 'post', $t('Вовлечение', 'Engagement'), 'planned', 'normal', $t('Игорь', 'Ian'), ['tg'], $t('Вопрос недели: на что у вас уходит больше всего времени?', 'Question of the week: what eats most of your time?'), ''],
            [$timeflow, 4, '11:00', 'article', $t('Кейсы', 'Cases'), 'idea', 'normal', '', [], $t('Кейс: как фрилансер поднял ставку на 30%', 'Case: how a freelancer raised the rate by 30%'), ''],
            [$timeflow, 6, '10:00', 'post', $t('Факты', 'Facts'), 'idea', 'low', '', [], $t('Тайм-трекинг в цифрах: 3 факта', 'Time tracking in numbers: 3 facts'), ''],
        ];

        foreach ($rows as [$project, $offset, $time, $kind, $rubric, $status, $priority, $assignee, $keys, $title, $body]) {
            $set = $project->id === $lenProject->id ? $len : $tf;
            $pub = Publication::create([
                'project_id' => $project->id, 'title' => $title, 'kind' => $kind, 'rubric' => $rubric,
                'sphere' => match ($kind) { 'video' => 'art', 'announcement' => 'other', default => 'work' },
                // Шесть статусов: идея, черновик, в работе, готов к публикации, опубликован, без статуса.
                'status' => ['planned' => 'idea', 'draft_ready' => 'draft', 'review' => 'ready', 'approved' => 'ready', 'scheduled' => 'ready', 'partial' => 'in_work'][$status] ?? $status,
                'priority' => $priority, 'planned_date' => $today->addDays($offset)->toDateString(), 'planned_time' => $time,
                'body' => $body ?: null, 'assignee' => $assignee ?: null,
                'hashtags' => $project->id === $lenProject->id ? '#'.$t('интерьер', 'interior').' #'.$t('ремонт', 'renovation') : '#'.$t('тайм_трекинг', 'timetracking').' #'.$t('продуктивность', 'productivity'),
                'link' => $project->id === $lenProject->id ? 'https://example.com/studio?utm_source=social' : 'https://example.com/timeflow?utm_source=social',
            ]);
            foreach ($keys as $i => $key) {
                // published: all done; partial: the first channel is done, the rest wait; scheduled: all scheduled
                $pl = match ($status) {
                    'published' => 'published',
                    'partial' => $i === 0 ? 'published' : ($i === 1 ? 'in_work' : 'todo'),
                    'scheduled' => 'scheduled',
                    'approved' => 'todo',
                    default => 'todo',
                };
                Placement::create([
                    'publication_id' => $pub->id, 'platform_id' => $set[$key]->id, 'status' => $pl,
                    'published_at' => $pl === 'published' ? $today->addDays($offset)->setTimeFromTimeString($time) : null,
                    'url' => $pl === 'published' ? $set[$key]->url.'/'.(1000 + $pub->id) : null,
                ]);
            }
        }

        $d = fn (int $n) => $today->addDays($n)->toDateString();
        // [kind, entry_date, state, sphere, topic, title, body, tags]
        $materials = [
            ['book', $d(-20), 'read', null, $t('Н. Ильин', 'N. Ilyin'), $t('Пиши, сокращай', 'Write Tight'), $t('Главное: убрать воду и канцелярит.', 'Main takeaway: cut the filler.'), null],
            ['book', $d(-3), 'best', null, $t('М. Ильяхов', 'M. Ilyakhov'), $t('Сторителлинг для бизнеса', 'Storytelling for Business'), $t('Лучшая книга по структуре историй.', 'The best book on story structure.'), null],
            ['book', $d(0), 'planned', null, $t('С. Кинг', 'S. King'), $t('Как писать книги', 'On Writing'), null, null],
            ['diary', $d(-1), null, null, null, $t('Мысли за день', 'Thoughts of the day'), $t('Сегодня получилось закончить статью. Завтра — идеи на неделю.', 'Finished the article today. Tomorrow: ideas for the week.'), null],
            ['note', $d(1), 'todo', null, null, $t('Написать пост про свет', 'Write a post about lighting'), null, null],
            ['note', $d(-2), 'done', null, null, $t('Оплатить хостинг', 'Pay for hosting'), null, null],
            ['audience', $d(-10), null, 'work', null, $t('Работа: владельцы квартир', 'Work: apartment owners'), $t('28–45 лет, делают ремонт, ищут спокойный дизайн без лишних затрат.', 'Aged 28–45, renovating, looking for calm design without overspending.'), null],
            ['audience', $d(-10), null, 'art', null, $t('Творчество: читатели прозы', 'Creative: fiction readers'), $t('Любители коротких рассказов и лёгкой грусти.', 'Readers of short stories and gentle melancholy.'), null],
            ['idea', $d(-5), null, null, null, $t('Идея: серия «До и после»', 'Idea: a "Before / after" series'), $t('Раз в две недели показывать одну комнату: фото, план, бюджет.', 'Every two weeks show one room: photos, plan, budget.'), null],
            ['hashtags', $d(-8), null, 'work', $t('Интерьер', 'Interiors'), $t('Хештеги интерьера', 'Interior hashtags'), '#'.$t('интерьер', 'interior').' #'.$t('дизайнинтерьера', 'interiordesign').' #'.$t('ремонт', 'renovation'), null],
            ['hashtags', $d(-8), null, 'art', $t('Проза', 'Fiction'), $t('Хештеги для рассказов', 'Fiction hashtags'), '#'.$t('рассказ', 'shortstory').' #'.$t('проза', 'prose').' #'.$t('литература', 'literature'), null],
            ['case', $d(-12), null, null, null, $t('Кейс: двушка за 3 месяца', 'Case: a two-room flat in 3 months'), $t('Клиент → задача → что сделали → результат в цифрах → цитата клиента.', 'Client → the task → what we did → the result in numbers → a client quote.'), null],
        ];
        foreach ($materials as [$kind, $date, $state, $sphere, $topic, $title, $body, $tags]) {
            Material::create(['kind' => $kind, 'entry_date' => $date, 'state' => $state, 'sphere' => $sphere, 'topic' => $topic, 'title' => $title, 'body' => $body, 'tags' => $tags]);
        }

        // «Важное» в календаре
        foreach ([['birthday', 3, null, $t('День рождения: Мария Иванова', 'Birthday: Maria Ivanova'), $t('Купить подарок', 'Buy a gift')], ['doctor', 6, '10:30', $t('Приём у врача', 'Doctor appointment'), null], ['holiday', 10, null, $t('Праздник', 'Holiday'), null]] as [$k, $off, $time, $title, $comment]) {
            Event::create(['kind' => $k, 'title' => $title, 'event_date' => $d($off), 'event_time' => $time, 'comment' => $comment]);
        }
    }
}
