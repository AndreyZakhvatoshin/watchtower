<?php

namespace Tests\Feature\Checks;

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
        $types = DB::table('information_schema.columns')
            ->where('table_name', 'checks')
            ->whereIn('column_name', ['interval_applied_at', 'deleted_at', 'created_at', 'updated_at'])
            ->pluck('data_type', 'column_name');

        $this->assertCount(4, $types);

        foreach ($types as $column => $type) {
            $this->assertSame('timestamp with time zone', $type, "Колонка {$column} потеряла зону");
        }
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

        $this->assertTrue(
            $indexes->contains(fn (array $index) => $index['columns'] === ['is_active']),
            'Нет индекса по is_active',
        );
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
