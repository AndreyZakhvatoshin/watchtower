<?php

namespace Tests\Feature\Checks;

use App\Modules\Checks\Contracts\CheckDraft;
use App\Modules\Checks\Contracts\CheckRepository;
use App\Modules\Checks\Contracts\CheckSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Контракт AD-3, половина «тянем»: GET /api/v1/checks с ETag и токеном.
 *
 * Фикстуры заводятся только через CheckRepository. Фабрика на внутренней модели
 * модуля была бы обращением к Internal/ мимо контракта (AD-6) — тем самым, что
 * станет автоматической проверкой в Story 2.6.
 */
class WorkerChecksApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-pervyy';

    private const SECOND_TOKEN = 'token-vtoroy';

    protected function setUp(): void
    {
        parent::setUp();

        config(['watchtower.worker_tokens' => [self::TOKEN, self::SECOND_TOKEN]]);

        // Счётчик ограничения частоты живёт в кэше, а CACHE_STORE=array переживает
        // весь процесс PHPUnit при одном ключе на все тесты. Без сброса порядок
        // тестов начал бы влиять на результат.
        Cache::flush();
    }

    public function test_worker_gets_the_active_set_with_an_etag(): void
    {
        $check = $this->createCheck();

        $response = $this->getSet();

        $response->assertOk();
        $response->assertHeader('ETag');

        $response->assertJsonPath('data.0.ulid', $check->ulid);
        $response->assertJsonPath('data.0.url', $check->url);
        $response->assertJsonPath('data.0.interval_seconds', $check->intervalSeconds);
        $response->assertJsonPath('data.0.expected_status', $check->expectedStatus);

        // Исполнитель считает от этого момента сетку расписания (AD-8) —
        // формат обязан совпадать с конвенциями до миллисекунды.
        $response->assertJsonPath(
            'data.0.interval_applied_at',
            $check->intervalAppliedAt->toIso8601ZuluString('millisecond'),
        );
    }

    public function test_internal_identifier_never_appears_in_the_body(): void
    {
        $this->createCheck();

        $body = $this->getSet()->getContent();

        $this->assertStringNotContainsString('"id"', (string) $body);
    }

    public function test_repeated_request_with_a_matching_etag_returns_304(): void
    {
        $this->createCheck();

        $etag = $this->getSet()->headers->get('ETag');

        $repeated = $this->getSet(['If-None-Match' => $etag]);

        $repeated->assertStatus(304);
        $this->assertSame('', $repeated->getContent());
        $this->assertSame($etag, $repeated->headers->get('ETag'));
    }

    public function test_two_identical_requests_return_byte_identical_bodies(): void
    {
        $this->createCheck();

        $first = $this->getSet();
        $second = $this->getSet();

        // Ловит волатильные поля: время генерации, счётчик, случайный порядок.
        // Любое такое поле сдвигает ETag каждый запрос, и 304 не вернётся никогда.
        $this->assertSame($first->getContent(), $second->getContent());
        $this->assertSame($first->headers->get('ETag'), $second->headers->get('ETag'));
    }

    public function test_changing_the_interval_changes_the_etag(): void
    {
        $check = $this->createCheck(['interval_seconds' => 60]);

        $this->assertEtagChangedBy(fn () => $this->repository()->update($check->ulid, $this->draft($check, ['interval_seconds' => 300])));
    }

    public function test_changing_the_address_changes_the_etag(): void
    {
        $check = $this->createCheck();

        $this->assertEtagChangedBy(fn () => $this->repository()->update($check->ulid, $this->draft($check, ['url' => 'https://example.com/other'])));
    }

    public function test_changing_the_expected_status_changes_the_etag(): void
    {
        $check = $this->createCheck();

        $this->assertEtagChangedBy(fn () => $this->repository()->update($check->ulid, $this->draft($check, ['expected_status' => 204])));
    }

    public function test_adding_a_check_changes_the_etag(): void
    {
        $this->createCheck();

        $this->assertEtagChangedBy(fn () => $this->createCheck(['url' => 'https://example.com/second']));
    }

    public function test_switching_a_check_off_changes_the_etag(): void
    {
        $check = $this->createCheck();

        $this->assertEtagChangedBy(fn () => $this->repository()->update($check->ulid, $this->draft($check, ['is_active' => false])));
    }

    public function test_deleting_a_check_changes_the_etag(): void
    {
        $check = $this->createCheck();

        $this->assertEtagChangedBy(fn () => $this->repository()->delete($check->ulid));
    }

    public function test_switched_off_and_deleted_checks_are_absent_from_the_set(): void
    {
        $live = $this->createCheck(['url' => 'https://example.com/live']);
        $off = $this->createCheck(['url' => 'https://example.com/off', 'is_active' => false]);
        $gone = $this->createCheck(['url' => 'https://example.com/gone']);

        $this->repository()->delete($gone->ulid);

        $response = $this->getSet();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.ulid', $live->ulid);

        // Оговорка: через контракт состояние «удалена, но активна» недостижимо —
        // delete() снимает is_active в той же транзакции. Значит этот тест не
        // различает работу SoftDeletes и работу флага, и доказательством обеих
        // веток считаться не может.
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString($off->ulid, $body);
        $this->assertStringNotContainsString($gone->ulid, $body);
    }

    public function test_a_missing_token_and_a_wrong_token_are_indistinguishable(): void
    {
        $this->createCheck();

        $without = $this->getJson('/api/v1/checks');
        $wrong = $this->getSet([], 'token-chuzhoy');

        $without->assertStatus(401);
        $wrong->assertStatus(401);

        $this->assertSame($without->getContent(), $wrong->getContent());
        $this->assertSame(
            $without->headers->get('WWW-Authenticate'),
            $wrong->headers->get('WWW-Authenticate'),
        );

        $without->assertJsonPath('error.code', 'unauthorized');

        // Заголовок обязателен по RFC 9110, но без параметров error и
        // error_description: они сделали бы два случая отказа различимыми.
        $this->assertSame('Bearer', $without->headers->get('WWW-Authenticate'));
    }

    public function test_a_refusal_carries_no_etag(): void
    {
        $this->createCheck();

        $this->getSet([], 'token-chuzhoy')->assertHeaderMissing('ETag');
    }

    public function test_a_refusal_is_logged_without_the_token(): void
    {
        $records = [];

        Log::listen(function ($record) use (&$records): void {
            $records[] = $record;
        });

        $this->getSet([], 'token-chuzhoy')->assertStatus(401);

        $mine = array_values(array_filter(
            $records,
            static fn ($record): bool => ($record->context['module'] ?? null) === 'checks',
        ));

        $this->assertCount(1, $mine, 'Отказ обязан оставить ровно одну запись модуля');
        $this->assertNotNull($mine[0]->context['correlation_id'] ?? null);

        foreach ($records as $record) {
            $line = $record->message.json_encode($record->context);
            $this->assertStringNotContainsString('token-chuzhoy', $line);
            $this->assertStringNotContainsString(self::TOKEN, $line);
        }
    }

    public function test_any_token_from_the_list_is_accepted(): void
    {
        $this->createCheck();

        $this->getSet([], self::SECOND_TOKEN)->assertOk();
    }

    public function test_an_empty_token_list_refuses_everyone(): void
    {
        config(['watchtower.worker_tokens' => []]);

        $this->createCheck();

        $this->getSet()->assertStatus(401);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function getSet(array $headers = [], ?string $token = null): TestResponse
    {
        return $this->getJson('/api/v1/checks', [
            'Authorization' => 'Bearer '.($token ?? self::TOKEN),
            ...$headers,
        ]);
    }

    private function assertEtagChangedBy(callable $change): void
    {
        $before = $this->getSet()->headers->get('ETag');

        $change();

        $after = $this->getSet(['If-None-Match' => $before]);

        $after->assertOk();
        $this->assertNotSame($before, $after->headers->get('ETag'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createCheck(array $attributes = []): CheckSnapshot
    {
        return $this->repository()->create(new CheckDraft(
            url: $attributes['url'] ?? 'https://example.com/health',
            intervalSeconds: $attributes['interval_seconds'] ?? 60,
            expectedStatus: $attributes['expected_status'] ?? 200,
            isActive: $attributes['is_active'] ?? true,
        ));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function draft(CheckSnapshot $check, array $attributes = []): CheckDraft
    {
        return new CheckDraft(
            url: $attributes['url'] ?? $check->url,
            intervalSeconds: $attributes['interval_seconds'] ?? $check->intervalSeconds,
            expectedStatus: $attributes['expected_status'] ?? $check->expectedStatus,
            isActive: $attributes['is_active'] ?? $check->isActive,
        );
    }

    private function repository(): CheckRepository
    {
        return app(CheckRepository::class);
    }
}
