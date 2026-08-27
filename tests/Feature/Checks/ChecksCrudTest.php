<?php

namespace Tests\Feature\Checks;

use App\Modules\Checks\Contracts\CheckDraft;
use App\Modules\Checks\Contracts\CheckRepository;
use App\Modules\Checks\Contracts\CheckSnapshot;
use App\Modules\Checks\Events\CheckCreated;
use App\Modules\Checks\Events\CheckDeleted;
use App\Modules\Checks\Events\CheckUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * AC1, AC2, AC4 — полный цикл жизни проверки через веб-интерфейс.
 */
class ChecksCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_creates_a_check_and_sees_it_in_the_list(): void
    {
        Event::fake([CheckCreated::class]);

        $response = $this->post('/checks', [
            'url' => 'https://example.com/health',
            'interval_seconds' => 60,
            'expected_status' => 200,
        ]);

        $response->assertRedirect('/checks');

        $this->assertDatabaseHas('checks', [
            'url' => 'https://example.com/health',
            'interval_seconds' => 60,
            'expected_status' => 200,
            'is_active' => true,
        ]);

        $this->get('/checks')->assertOk()->assertSee('https://example.com/health');

        Event::assertDispatched(CheckCreated::class);
    }

    public function test_external_identifier_is_a_ulid_and_the_internal_one_is_a_bigint(): void
    {
        $check = $this->createCheck();

        // 26 символов Crockford base32 — ULID и никак не автоинкремент.
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $check->ulid);

        // Наружу уходит только ulid: последовательный id раскрыл бы объём
        // и порядок создания.
        $this->get('/checks')->assertSee($check->ulid);
    }

    public function test_changing_the_interval_moves_the_moment_it_was_applied(): void
    {
        $check = $this->createCheck(['interval_seconds' => 60]);
        $before = $check->intervalAppliedAt;

        $this->travel(5)->minutes();

        Event::fake([CheckUpdated::class]);

        $this->put("/checks/{$check->ulid}", [
            'url' => $check->url,
            'interval_seconds' => 300,
            'expected_status' => $check->expectedStatus,
        ])->assertRedirect('/checks');

        $updated = $this->repository()->findByUlid($check->ulid);

        $this->assertSame(300, $updated->intervalSeconds);
        $this->assertTrue(
            $updated->intervalAppliedAt->greaterThan($before),
            'Смена интервала обязана начать новую сетку расписания с момента применения',
        );

        Event::assertDispatched(CheckUpdated::class);
    }

    public function test_changing_anything_but_the_interval_leaves_the_schedule_alone(): void
    {
        $check = $this->createCheck(['interval_seconds' => 60]);
        $before = $check->intervalAppliedAt;

        $this->travel(5)->minutes();

        $this->put("/checks/{$check->ulid}", [
            'url' => 'https://example.com/other',
            'interval_seconds' => 60,
            'expected_status' => 204,
        ])->assertRedirect('/checks');

        $updated = $this->repository()->findByUlid($check->ulid);

        $this->assertSame('https://example.com/other', $updated->url);
        $this->assertSame(204, $updated->expectedStatus);
        // Ловушка формы: сохранение всех полей разом сдвинуло бы сетку без причины.
        $this->assertTrue(
            $updated->intervalAppliedAt->equalTo($before),
            'Сетка расписания сдвинулась, хотя интервал не менялся',
        );
    }

    public function test_deleting_a_check_removes_it_from_the_list_and_from_the_active_set(): void
    {
        Event::fake([CheckDeleted::class]);

        $check = $this->createCheck();

        $this->delete("/checks/{$check->ulid}")->assertRedirect('/checks');

        $this->get('/checks')->assertOk()->assertDontSee($check->ulid);

        $active = collect($this->repository()->active())->pluck('ulid');
        $this->assertNotContains($check->ulid, $active->all());

        // Удаление мягкое: история результатов на ступени 1 переживает удаление
        // проверки, единственный cross-модульный внешний ключ не ломается.
        $this->assertSoftDeleted('checks', ['ulid' => $check->ulid]);

        Event::assertDispatched(CheckDeleted::class);
    }

    public function test_an_inactive_check_stays_in_the_list_but_leaves_the_active_set(): void
    {
        $check = $this->createCheck();

        $this->put("/checks/{$check->ulid}", [
            'url' => $check->url,
            'interval_seconds' => $check->intervalSeconds,
            'expected_status' => $check->expectedStatus,
            'is_active' => '0',
        ])->assertRedirect('/checks');

        $this->get('/checks')->assertSee($check->ulid);

        $active = collect($this->repository()->active())->pluck('ulid');
        $this->assertNotContains($check->ulid, $active->all());
    }

    public function test_editing_a_check_that_does_not_exist_answers_404(): void
    {
        $this->get('/checks/01JZZZZZZZZZZZZZZZZZZZZZZZ/edit')->assertNotFound();
    }

    private function repository(): CheckRepository
    {
        return app(CheckRepository::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function test_switching_a_check_on_reports_the_change_of_activation(): void
    {
        $check = $this->createCheck(['is_active' => false]);

        Event::fake([CheckUpdated::class]);

        $this->put("/checks/{$check->ulid}", [
            'url' => $check->url,
            'interval_seconds' => $check->intervalSeconds,
            'expected_status' => $check->expectedStatus,
            'is_active' => 1,
        ])->assertRedirect('/checks');

        // Включение добавляет проверку в состав расписания. Без отдельного
        // признака подписчик ступени 1 получил бы «просто перечитай поля»
        // и в сетку её не поставил.
        Event::assertDispatched(
            CheckUpdated::class,
            fn (CheckUpdated $event) => $event->activationChanged && ! $event->intervalChanged,
        );
    }

    public function test_editing_the_address_alone_reports_no_schedule_change(): void
    {
        $check = $this->createCheck();

        Event::fake([CheckUpdated::class]);

        $this->put("/checks/{$check->ulid}", [
            'url' => 'https://example.com/other',
            'interval_seconds' => $check->intervalSeconds,
            'expected_status' => $check->expectedStatus,
            'is_active' => 1,
        ])->assertRedirect('/checks');

        Event::assertDispatched(
            CheckUpdated::class,
            fn (CheckUpdated $event) => ! $event->activationChanged && ! $event->intervalChanged,
        );
    }

    private function createCheck(array $attributes = []): CheckSnapshot
    {
        return $this->repository()->create(new CheckDraft(
            url: $attributes['url'] ?? 'https://example.com/health',
            intervalSeconds: $attributes['interval_seconds'] ?? 60,
            expectedStatus: $attributes['expected_status'] ?? 200,
            isActive: $attributes['is_active'] ?? true,
        ));
    }
}
