<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Схема принадлежит модулю (AD-15): миграция лежит внутри Checks, а не в
 * database/migrations, и грузится ChecksServiceProvider.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checks', function (Blueprint $table): void {
            // Внутри — bigint, наружу — ULID. Последовательный внешний
            // идентификатор раскрыл бы объём и порядок создания.
            $table->id();
            $table->string('ulid', 26)->unique();

            $table->string('url', 2048);
            $table->unsignedInteger('interval_seconds');
            $table->unsignedSmallInteger('expected_status');
            $table->boolean('is_active')->default(true);

            // Момент, с которого считается новая сетка расписания (AD-8, FR32).
            // Сетку считает исполнитель, ядро только сообщает момент.
            $table->timestampTz('interval_applied_at');

            $table->timestampsTz();
            $table->softDeletesTz();

            // Набор, который забирает исполнитель. Индекс не «на будущее»:
            // это единственная выборка, которая будет идти каждые 30 секунд.
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checks');
    }
};
