<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Боли аудитории»: конструктор разделов (боли, вопросы-ответы) хранится JSON'ом в самой аудитории (материал вида
 * `audience`), а публикация помнит, для какой аудитории написана и какие её боли решает.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_materials', function (Blueprint $table) {
            $table->json('pains')->nullable();
        });
        Schema::table('content_publications', function (Blueprint $table) {
            $table->unsignedBigInteger('audience_id')->nullable();
            $table->json('pain_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('content_publications', fn (Blueprint $t) => $t->dropColumn(['audience_id', 'pain_ids']));
        Schema::table('content_materials', fn (Blueprint $t) => $t->dropColumn('pains'));
    }
};
