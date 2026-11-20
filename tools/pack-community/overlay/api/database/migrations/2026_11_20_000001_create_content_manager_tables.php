<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Таблицы приложения rumantic.content-manager (схема с нуля, версия 1.2). Префикс app_rumantic_content_manager_ обязателен;
 * внешних ключей на таблицы хоста нет: приложение должно ставиться и сниматься, не завися от схемы ядра.
 */
return new class extends Migration
{
    private const P = 'app_rumantic_content_manager_';

    public function up(): void
    {
        // Проект — бренд или клиент, для которого делается контент.
        Schema::create(self::P.'projects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('topic', 200)->nullable();
            $table->text('audience')->nullable();
            $table->string('tone', 200)->nullable();
            $table->string('keywords', 300)->nullable();
            $table->string('competitors', 300)->nullable();
            $table->string('manager', 120)->nullable();
            $table->string('color', 20)->default('#6366f1');
            $table->timestamps();
        });

        Schema::create(self::P.'platforms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->string('name', 120);
            $table->string('kind', 20)->default('other');
            $table->string('url', 300)->nullable();
            $table->string('audience', 300)->nullable();
            $table->string('formats', 200)->nullable();
            $table->unsignedInteger('max_length')->nullable();
            $table->unsignedSmallInteger('per_week')->nullable();
            $table->string('api_status', 12)->default('manual');
            $table->timestamps();
        });

        Schema::create(self::P.'publications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            // Заполнен, когда текст — адаптация другой публикации.
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('title', 200);
            $table->string('kind', 20)->default('post');
            $table->string('rubric', 80)->nullable();
            $table->string('sphere', 8)->default('other')->index();
            $table->string('status', 16)->default('idea')->index();
            $table->string('priority', 8)->default('normal');
            $table->date('planned_date')->nullable()->index();
            $table->string('planned_time', 5)->nullable();
            $table->longText('body')->nullable();
            $table->string('hashtags', 300)->nullable();
            $table->string('link', 500)->nullable();
            $table->string('assignee', 120)->nullable();
            $table->text('comment')->nullable();
            // Для какой аудитории написано и какие её боли решает.
            $table->unsignedBigInteger('audience_id')->nullable();
            $table->json('pain_ids')->nullable();
            $table->timestamps();
        });

        // Пара «публикация — площадка»: у каждой площадки свой статус, дата и ссылка.
        Schema::create(self::P.'placements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('publication_id')->index();
            $table->unsignedBigInteger('platform_id')->index();
            $table->string('status', 12)->default('todo');
            $table->dateTime('published_at')->nullable();
            $table->string('url', 500)->nullable();
            $table->string('comment', 300)->nullable();
            $table->timestamps();
        });

        Schema::create(self::P.'materials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('project_id')->nullable()->index();
            $table->string('kind', 16)->default('idea');
            $table->date('entry_date')->nullable()->index();
            $table->string('state', 12)->nullable();
            $table->string('sphere', 8)->nullable();
            $table->string('topic', 160)->nullable();
            $table->string('title', 200);
            $table->longText('body')->nullable();
            $table->string('tags', 200)->nullable();
            // Аудитория: конструктор разделов (боли, вопросы-ответы) JSON'ом.
            $table->json('pains')->nullable();
            $table->timestamps();
        });

        // «Важное» в календаре.
        Schema::create(self::P.'events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('kind', 12)->default('other');
            $table->string('title', 200);
            $table->date('event_date')->index();
            $table->string('event_time', 5)->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        // Лента публикации: комментарии и решения согласующего.
        Schema::create(self::P.'comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('publication_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('kind', 10)->default('comment');
            $table->text('body')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'publication_id'], 'app_cm_comments_pub');
        });
    }

    public function down(): void
    {
        foreach (['comments', 'events', 'materials', 'placements', 'publications', 'platforms', 'projects'] as $t) {
            Schema::dropIfExists(self::P.$t);
        }
    }
};
