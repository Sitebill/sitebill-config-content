<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Таблицы конфигурации `content` (контент-фабрика, docs/roadmap/CONTENT_FACTORY.md). Схема — итог миграций
 * приложения rumantic.content-manager 1.1.0, но с foreign key на tenants и статусом `review` (согласование).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Проект — бренд или клиент, для которого делается контент.
        Schema::create('content_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
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

        // Площадка проекта; добавляется и вручную, если у неё нет API.
        Schema::create('content_platforms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
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

        Schema::create('content_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('project_id')->index();
            // Заполнен, когда текст — адаптация другой публикации (источник не меняется).
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
            $table->timestamps();
        });

        // Строка на пару «публикация — площадка»: у каждой площадки свой статус, дата и ссылка.
        Schema::create('content_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('publication_id')->index();
            $table->unsignedBigInteger('platform_id')->index();
            $table->string('status', 12)->default('todo');
            $table->dateTime('published_at')->nullable();
            $table->string('url', 500)->nullable();
            $table->string('comment', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('content_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('project_id')->nullable()->index();
            $table->string('kind', 16)->default('idea');
            $table->date('entry_date')->nullable()->index();
            // книги: read | planned | best; заметки: todo | done
            $table->string('state', 12)->nullable();
            // аудитория и хештеги: work | art
            $table->string('sphere', 8)->nullable();
            // книги — автор, хештеги — тема
            $table->string('topic', 160)->nullable();
            $table->string('title', 200);
            $table->longText('body')->nullable();
            $table->string('tags', 200)->nullable();
            $table->timestamps();
        });

        // «Важное» в календаре: день рождения, праздник, приём у врача и т. п.
        Schema::create('content_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 12)->default('other');
            $table->string('title', 200);
            $table->date('event_date')->index();
            $table->string('event_time', 5)->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        // Команда тенанта. Роль живёт здесь, а не в ролях ядра; админ сайта — всегда owner и в таблице не нужен.
        Schema::create('content_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 12);
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        foreach (['members', 'events', 'materials', 'placements', 'publications', 'platforms', 'projects'] as $t) {
            Schema::dropIfExists('content_'.$t);
        }
    }
};
