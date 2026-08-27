<?php

namespace Tests\Feature\Checks;

use App\Modules\Checks\Contracts\CheckDraft;
use App\Modules\Checks\Contracts\CheckRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * AD-14 — модуль наблюдаем с рождения. Экспортёр метрик появится в Story 5.1,
 * но лог обязателен уже сейчас: дописывать наблюдаемость задним числом дороже.
 */
class ChecksLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_lifecycle_events_are_written_to_the_log_as_the_checks_module(): void
    {
        $records = [];

        Log::listen(function ($message) use (&$records): void {
            $records[] = $message;
        });

        $repository = app(CheckRepository::class);

        $check = $repository->create(new CheckDraft(
            url: 'https://example.com/health',
            intervalSeconds: 60,
            expectedStatus: 200,
        ));

        $repository->update($check->ulid, new CheckDraft(
            url: 'https://example.com/health',
            intervalSeconds: 300,
            expectedStatus: 200,
        ));

        $repository->delete($check->ulid);

        // Отбор по модулю обязателен: в тот же канал пишут middleware и
        // фреймворк, и assertCount по всему потоку ловил бы чужие строки —
        // тест то падал бы, то проходил в зависимости от соседей.
        $records = array_values(array_filter(
            $records,
            static fn ($record): bool => ($record->context['module'] ?? null) === 'checks',
        ));

        $this->assertCount(3, $records, 'Создание, изменение и удаление обязаны оставить по записи');

        foreach ($records as $record) {
            // module читает JsonFormatter из контекста — без него строка уедет
            // в лог с именем канала вместо имени модуля.
            $this->assertSame('checks', $record->context['module'] ?? null);
            $this->assertSame($check->ulid, $record->context['ulid'] ?? null);
        }

        // Внутренний bigint в лог не уходит по той же причине, по какой не
        // уходит наружу: он раскрывает объём и порядок создания.
        foreach ($records as $record) {
            $this->assertArrayNotHasKey('id', $record->context);
        }
    }
}
