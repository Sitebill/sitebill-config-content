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
            'audience' => $t('Работа: владельцы квартир', 'Work: apartment owners'),
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
            'audience' => $t('Работа: руководители студий и фриланс-команд', 'Work: studio and freelance team leads'),
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

        // Целевые аудитории с «болями» (docs/roadmap/CONTENT_FACTORY.md §19): у каждой — разделы с болями и вопросами-ответами.
        // id болей фиксированы: на них ссылаются публикации (pain_ids), так виден полный цикл «аудитория → боль → текст».
        $day = fn (int $n) => $today->addDays($n)->toDateString();
        $row = fn (string $id, string $title, string $description) => ['id' => $id, 'title' => $title, 'description' => $description];
        $qa = fn (string $id, string $question, string $answer) => ['id' => $id, 'question' => $question, 'answer' => $answer];
        $audLen = Material::create([
            'kind' => 'audience', 'entry_date' => $day(-10), 'sphere' => 'work',
            'title' => $t('Работа: владельцы квартир', 'Work: apartment owners'),
            'body' => $t('28–45 лет, делают ремонт, ищут спокойный дизайн без лишних затрат.', 'Aged 28–45, renovating, looking for calm design without overspending.'),
            'pains' => [
                ['id' => 'ls1', 'title' => $t('Боли владельцев квартир', 'Pains of apartment owners'), 'pains' => [
                    $row('l1', $t('Страх перерасхода бюджета', 'Fear of going over budget'), $t('Ремонт почти всегда выходит дороже сметы; боятся скрытых платежей и «доплат по ходу».', 'A renovation almost always costs more than the estimate; owners fear hidden charges and extras along the way.')),
                    $row('l2', $t('Непонятно, с чего начать', 'No idea where to start'), $t('Не знают порядок работ: замеры, планировка, электрика, черновая и чистовая отделка.', 'They do not know the order of work: measuring, layout, wiring, rough and finishing works.')),
                    $row('l3', $t('Тёмная и неудобная планировка', 'A dark and awkward layout'), $t('Мало света, неудобные зоны, мебель «не встаёт», на кухне некуда поставить технику.', 'Not enough light, awkward zones, furniture does not fit, no place for appliances in the kitchen.')),
                    $row('l4', $t('Боязнь неудачного дизайна', 'Fear of a poor design'), $t('Не уверены во вкусе, боятся, что быстро надоест или получится «как у всех».', 'They doubt their taste and fear it will get boring fast or look like everyone else’s.')),
                ], 'questions' => [
                    $qa('lq1', $t('Сколько стоит дизайн-проект?', 'How much does a design project cost?'), $t('Зависит от площади и состава: считаем по прайсу студии, цену фиксируем в договоре до начала работ.', 'It depends on the area and scope: we use the studio price list and fix the price in the contract before work starts.')),
                    $qa('lq2', $t('С чего начинается работа?', 'How does the work start?'), $t('С замеров и разговора о том, как вы живёте: из него складывается задание на проект.', 'With measurements and a talk about how you live: it becomes the brief for the project.')),
                ]],
                ['id' => 'ls2', 'title' => $t('За что готовы платить', 'What they are ready to pay for'), 'pains' => [
                    $row('l5', $t('Спокойствие и предсказуемость', 'Calm and predictability'), $t('Фиксированная смета, этапы с понятными сроками, отчёты по ходу работ.', 'A fixed estimate, stages with clear deadlines, progress reports.')),
                    $row('l6', $t('Готовое решение «под ключ»', 'A ready turnkey solution'), $t('Чтобы не искать подрядчиков и мебель самим: проект, закупки и авторский надзор.', 'No need to look for contractors and furniture: a project, purchasing and supervision.')),
                ], 'questions' => []],
                ['id' => 'ls3', 'title' => $t('Возражения', 'Objections'), 'pains' => [
                    $row('l7', $t('«Дизайнер — это дорого, сделаю сам»', '“A designer is expensive, I will do it myself”'), $t('Считают, что хватит подборки картинок из интернета; не видят, сколько стоят ошибки в планировке.', 'They think a pile of pictures from the internet is enough and do not see what layout mistakes cost.')),
                ], 'questions' => [
                    $qa('lq3', $t('Можно ли без дизайнера?', 'Can I do it without a designer?'), $t('Можно, но ошибки в планировке и электрике обходятся дороже проекта — покажите это на примере кейса.', 'You can, but layout and wiring mistakes cost more than a project — show it with a case.')),
                ]],
            ],
        ]);
        $audTf = Material::create([
            'kind' => 'audience', 'entry_date' => $day(-9), 'sphere' => 'work',
            'title' => $t('Работа: руководители студий и фриланс-команд', 'Work: studio and freelance team leads'),
            'body' => $t('Руководители небольших студий и команд до 20 человек: ведут проекты, выставляют счета клиентам, считают загрузку.', 'Heads of small studios and teams of up to 20: they run projects, invoice clients and plan workload.'),
            'pains' => [
                ['id' => 'ts1', 'title' => $t('Боли руководителей', 'Pains of team leads'), 'pains' => [
                    $row('t1', $t('Непонятно, куда уходит время', 'No clue where the time goes'), $t('Часы теряются на мелкие задачи и переключения; отчёты собирают «по памяти» в конце недели.', 'Hours vanish in small tasks and switching; reports are put together from memory at the end of the week.')),
                    $row('t2', $t('Неточные счета клиентам', 'Inaccurate client invoices'), $t('Часы занижают или завышают, из-за чего спорят с клиентом и теряют деньги.', 'Hours are under- or overstated, which leads to disputes with clients and lost money.')),
                    $row('t3', $t('Команда воспринимает трекинг как слежку', 'The team sees tracking as surveillance'), $t('Сопротивляются, отмечают время задним числом, данные получаются недостоверными.', 'People resist and log time retroactively, so the data is unreliable.')),
                    $row('t4', $t('Инструменты слишком сложные', 'Tools are too complex'), $t('Большие трекеры перегружены функциями: настройка занимает дольше, чем польза.', 'Big trackers are overloaded: setting them up takes longer than they pay back.')),
                ], 'questions' => [
                    $qa('tq1', $t('Сколько времени занимает внедрение?', 'How long does the rollout take?'), $t('Команда из 5–8 человек начинает работать за один день: список проектов, приглашения и короткая вводная.', 'A team of 5–8 starts within a day: a project list, invitations and a short briefing.')),
                    $qa('tq2', $t('Увидит ли руководитель всё?', 'Will the lead see everything?'), $t('Руководитель видит часы по проектам и задачам, но не скриншоты и не активность за компьютером.', 'The lead sees hours per project and task, but no screenshots and no computer activity.')),
                ]],
                ['id' => 'ts2', 'title' => $t('Что интересно и за что платят', 'What interests them and what they pay for'), 'pains' => [
                    $row('t5', $t('Отчёты по проектам и рентабельность', 'Project reports and profitability'), $t('Видеть, какие проекты приносят деньги, а какие съедают часы.', 'See which projects earn money and which only eat hours.')),
                    $row('t6', $t('Счета клиентам из отчёта по времени', 'Client invoices from a time report'), $t('Выставлять счёт в пару кликов, без пересборки данных в таблицах.', 'Invoice in a couple of clicks without rebuilding data in spreadsheets.')),
                ], 'questions' => []],
                ['id' => 'ts3', 'title' => $t('Возражения', 'Objections'), 'pains' => [
                    $row('t7', $t('«У нас есть таблицы, зачем платить»', '“We have spreadsheets, why pay”'), $t('Таблицы кажутся бесплатными; не считают часы, которые уходят на их ведение и сверку.', 'Spreadsheets feel free; people do not count the hours spent maintaining and reconciling them.')),
                ], 'questions' => []],
            ],
        ]);
        Material::create([
            'kind' => 'audience', 'entry_date' => $day(-10), 'sphere' => 'art',
            'title' => $t('Творчество: читатели прозы', 'Creative: fiction readers'),
            'body' => $t('Любители коротких рассказов и лёгкой грусти.', 'Readers of short stories and gentle melancholy.'),
            'pains' => [
                ['id' => 'fs1', 'title' => $t('Боли читателей', 'Pains of readers'), 'pains' => [
                    $row('f1', $t('Не хватает времени на чтение', 'Not enough time to read'), $t('Длинные книги откладываются; хочется закончить историю за один вечер.', 'Long books get postponed; people want to finish a story in one evening.')),
                    $row('f2', $t('Устали от пафоса', 'Tired of pathos'), $t('Хочется тихих историй о понятных людях, а не громких сюжетов.', 'They want quiet stories about relatable people rather than loud plots.')),
                ], 'questions' => [
                    $qa('fq1', $t('Где можно читать?', 'Where can I read?'), $t('Рассказы выходят в канале и в рассылке; архив — на сайте.', 'Stories come out in the channel and the newsletter; the archive is on the site.')),
                ]],
            ],
        ]);
        // Какие боли решает каждая публикация (по порядку строк ниже): так в демо виден цикл «аудитория → боль → текст».
        $painsOf = [
            0 => ['l3'], 1 => ['l1', 'l5'], 2 => ['l4'], 3 => ['l2', 'l1'], 4 => ['l4', 'l5'], 5 => [], 6 => ['l4'], 7 => ['l3'], 8 => ['l3', 'l2'], 9 => ['l5', 'l6'], 10 => ['l2'], 11 => [], 12 => ['l7', 'l1'],
            13 => ['t1', 't5'], 14 => ['t1', 't2'], 15 => ['t3'], 16 => ['t2', 't6'], 17 => [], 18 => ['t3', 't4'], 19 => ['t1'], 20 => ['t2', 't5'], 21 => ['t1'],
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

        foreach ($rows as $n => [$project, $offset, $time, $kind, $rubric, $status, $priority, $assignee, $keys, $title, $body]) {
            $set = $project->id === $lenProject->id ? $len : $tf;
            $pub = Publication::create([
                'project_id' => $project->id, 'title' => $title, 'kind' => $kind, 'rubric' => $rubric,
                'sphere' => match ($kind) { 'video' => 'art', 'announcement' => 'other', default => 'work' },
                // Шесть статусов: идея, черновик, в работе, готов к публикации, опубликован, без статуса.
                'status' => ['planned' => 'idea', 'draft_ready' => 'draft', 'review' => 'ready', 'approved' => 'ready', 'scheduled' => 'ready', 'partial' => 'in_work'][$status] ?? $status,
                'priority' => $priority, 'planned_date' => $today->addDays($offset)->toDateString(), 'planned_time' => $time,
                'body' => $body ?: null, 'assignee' => $assignee ?: null,
                'audience_id' => ($project->id === $lenProject->id ? $audLen : $audTf)->id, 'pain_ids' => $painsOf[$n] ?? [],
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
