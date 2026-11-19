<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Обсуждение и согласование публикации (docs/roadmap/CONTENT_FACTORY.md, фаза 3): комментарии команды и решения
 * согласующего («согласовано» / «на доработку») в одной ленте. Решение — тоже запись, чтобы история была видна.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('publication_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // comment | approve | return
            $table->string('kind', 10)->default('comment');
            $table->text('body')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'publication_id']);
            $table->foreign('publication_id')->references('id')->on('content_publications')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_comments');
    }
};
