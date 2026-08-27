<?php

namespace Tests\Feature\Checks;

use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Схема принадлежит модулю (AD-15) и хранит время в UTC (AD-8, NFR6).
 *
 * Тесты идут против PostgreSQL, а не против sqlite: timestamptz, поведение
 * уникального индекса и типы колонок на sqlite проверить нельзя в принципе.
 */
class ChecksSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_of_the_module_creates_the_checks_table(): void
    {
        // Имя таблицы задано хребтом (Consistency Conventions), а миграция
        // лежит внутри модуля — значит, провайдер модуля её действительно грузит.
        $this->assertTrue(Schema::hasTable('checks'));
    }

    public function test_table_carries_every_column_the_story_requires(): void
    {
        $this->assertTrue(Schema::hasColumns('checks', [
            'id',
            'ulid',
            'url',
            'interval_seconds',
            'expected_status',
            'is_active',
            'interval_applied_at',
            'deleted_at',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_all_moments_are_stored_with_a_time_zone(): void
    {
        // timestamp без зоны ломает AD-8 на первом же сравнении и делает это
        // молча — поэтому тип колонки проверяется, а не подразумевается.
        // Фильтр по table_schema обязателен: information_schema показывает все
        // схемы базы, и одноимённая таблица в любой другой схеме подмешала бы
        // свои строки в ответ. current_schema() — та схема, в которую пишет
        // соединение теста.
        $columns = DB::table('information_schema.columns')
            ->whereRaw('table_schema = current_schema()')
            ->where('table_name', 'checks')
            ->whereIn('column_name', ['interval_applied_at', 'deleted_at', 'created_at', 'updated_at'])
            ->get(['column_name', 'data_type', 'datetime_precision', 'is_nullable']);

        $this->assertCount(4, $columns);

        foreach ($columns as $column) {
            $this->assertSame('timestamp with time zone', $column->data_type, "Колонка {$column->column_name} потеряла зону");

            // Миллисекунды: без них два события внутри одной секунды
            // неотличимы по порядку, а по нему считается сетка расписания.
            $this->assertSame(3, (int) $column->datetime_precision, "Колонка {$column->column_name} потеряла миллисекунды");
        }
    }

    public function test_creation_moments_are_not_nullable(): void
    {
        // CheckSnapshot типизирует их как non-nullable CarbonImmutable —
        // расхождение схемы и типа чинится на стороне схемы.
        $nullable = DB::table('information_schema.columns')
            ->whereRaw('table_schema = current_schema()')
            ->where('table_name', 'checks')
            ->whereIn('column_name', ['created_at', 'updated_at'])
            ->pluck('is_nullable', 'column_name');

        $this->assertSame(['created_at' => 'NO', 'updated_at' => 'NO'], $nullable->all());
    }

    public function test_database_rejects_an_interval_outside_the_closed_set(): void
    {
        // Форма ловит человека, но не консольную команду, не сидер и не UPDATE
        // в psql. Значение вне набора роняло весь список на CheckInterval::from().
        $this->expectException(QueryException::class);

        DB::table('checks')->insert([...$this->row('01JZZZZZZZZZZZZZZZZZZZZZZ1'), 'interval_seconds' => 45]);
    }

    public function test_database_rejects_a_status_outside_the_http_range(): void
    {
        $this->expectException(QueryException::class);

        DB::table('checks')->insert([...$this->row('01JZZZZZZZZZZZZZZZZZZZZZZ2'), 'expected_status' => 42]);
    }

    public function test_ulid_is_unique_and_is_active_is_indexed(): void
    {
        // ulid — внешний ключ поиска, is_active — набор, который забирает
        // исполнитель. Оба индекса существуют не «на будущее», а по назначению.
        $indexes = collect(Schema::getIndexes('checks'));

        $this->assertTrue(
            $indexes->contains(fn (array $index) => $index['columns'] === ['ulid'] && $index['unique']),
            'Нет уникального индекса по ulid',
        );

        // Индекс по is_active — частичный: исполнитель забирает набор
        // условием WHERE is_active AND deleted_at IS NULL, и удалённым
        // с выключенными в индексе делать нечего.
        $this->assertTrue(
            $indexes->contains(fn (array $index) => $index['name'] === 'checks_active_index'),
            'Нет индекса по активному набору',
        );

        $definition = DB::selectOne("SELECT indexdef FROM pg_indexes WHERE indexname = 'checks_active_index'");

        $this->assertStringContainsString('WHERE', (string) $definition->indexdef, 'Индекс по активному набору перестал быть частичным');
    }

    public function test_database_rejects_a_duplicate_ulid(): void
    {
        // Уникальность на уровне схемы, а не на уровне приложения: генератор
        // ULID можно обойти прямым запросом, ограничение — нет.
        DB::table('checks')->insert($this->row('01JZZZZZZZZZZZZZZZZZZZZZZZ'));

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('checks')->insert($this->row('01JZZZZZZZZZZZZZZZZZZZZZZZ'));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $ulid): array
    {
        return [
            'ulid' => $ulid,
            'url' => 'https://example.com',
            'interval_seconds' => 60,
            'expected_status' => 200,
            'is_active' => true,
            'interval_applied_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
