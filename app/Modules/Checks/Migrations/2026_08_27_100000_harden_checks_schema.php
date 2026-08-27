<?php

use App\Modules\Checks\Contracts\CheckInterval;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Правки схемы по ревью Story 1.2. Отдельной миграцией, а не правкой исходной:
 * create_checks_table уже выполнена на проде 13.08.2026. Правка выполненной
 * миграции не догоняет базу, в которой она отработала, — репозиторий и прод
 * разъехались бы молча, и заметно это стало бы при пересоздании машины.
 *
 * Четыре находки разом, потому что все четыре — одна операция ALTER на одной
 * таблице, и дробить их на четыре миграции значит четырежды переписать таблицу.
 *
 * SQL сырой: частичный индекс и CHECK построитель схемы Laravel не выражает.
 * Привязка к PostgreSQL осознанная — БД у ядра одна (AD-1), переносимость
 * схемы в задачи проекта не входит.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Точность времени. timestampTz() без аргумента даёт в PostgreSQL
        // timestamp(0) — дробная часть отбрасывается. Consistency Conventions
        // требуют миллисекунды: без них два события внутри одной секунды
        // становятся неотличимы по порядку, а на ступени 1 по этому порядку
        // считается сетка расписания.
        foreach (['interval_applied_at', 'created_at', 'updated_at', 'deleted_at'] as $column) {
            DB::statement("ALTER TABLE checks ALTER COLUMN {$column} TYPE timestamptz(3)");
        }

        // 2. created_at и updated_at приходили nullable (так их делает
        // timestampsTz), а CheckSnapshot типизирует их как non-nullable
        // CarbonImmutable. Расхождение чинится на стороне схемы: строки без
        // времени создания в этой таблице бессмысленны.
        DB::statement('UPDATE checks SET created_at = now() WHERE created_at IS NULL');
        DB::statement('UPDATE checks SET updated_at = now() WHERE updated_at IS NULL');
        DB::statement('ALTER TABLE checks ALTER COLUMN created_at SET NOT NULL');
        DB::statement('ALTER TABLE checks ALTER COLUMN updated_at SET NOT NULL');

        // 3. Набор значений держит база, а не только форма. Валидация ловит
        // ввод человека, но не консольную команду, не сидер и не ручной UPDATE
        // в psql. Значение вне набора роняло весь список: CheckInterval::from()
        // бросал ValueError на одной строке.
        $intervals = implode(', ', CheckInterval::values());

        DB::statement("ALTER TABLE checks ADD CONSTRAINT checks_interval_seconds_check CHECK (interval_seconds IN ({$intervals}))");
        DB::statement('ALTER TABLE checks ADD CONSTRAINT checks_expected_status_check CHECK (expected_status BETWEEN 100 AND 599)');

        // 4. Индекс под реальный запрос. Исполнитель забирает набор условием
        // WHERE is_active AND deleted_at IS NULL — частичный индекс и меньше
        // (удалённые и выключенные в него не входят), и планировщик выбирает
        // его увереннее, чем индекс по колонке целиком.
        DB::statement('DROP INDEX IF EXISTS checks_is_active_index');
        DB::statement('CREATE INDEX checks_active_index ON checks (is_active) WHERE is_active AND deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS checks_active_index');
        DB::statement('CREATE INDEX checks_is_active_index ON checks (is_active)');

        DB::statement('ALTER TABLE checks DROP CONSTRAINT IF EXISTS checks_expected_status_check');
        DB::statement('ALTER TABLE checks DROP CONSTRAINT IF EXISTS checks_interval_seconds_check');

        DB::statement('ALTER TABLE checks ALTER COLUMN updated_at DROP NOT NULL');
        DB::statement('ALTER TABLE checks ALTER COLUMN created_at DROP NOT NULL');

        foreach (['interval_applied_at', 'created_at', 'updated_at', 'deleted_at'] as $column) {
            DB::statement("ALTER TABLE checks ALTER COLUMN {$column} TYPE timestamptz(0)");
        }
    }
};
