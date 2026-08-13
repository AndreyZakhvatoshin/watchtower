<?php

namespace Tests\Feature\Checks;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * AC3 — недопустимые данные возвращают ошибки полей и не создают строку.
 */
class ChecksValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'адрес пустой' => [['url' => ''], 'url'],
            'адрес не разбирается' => [['url' => 'не адрес вовсе'], 'url'],
            'схема ftp вместо http' => [['url' => 'ftp://example.com'], 'url'],
            'схемы нет вообще' => [['url' => 'example.com'], 'url'],
            'интервал вне набора' => [['interval_seconds' => 45], 'interval_seconds'],
            'интервал не число' => [['interval_seconds' => 'часто'], 'interval_seconds'],
            'интервал отрицательный' => [['interval_seconds' => -60], 'interval_seconds'],
            'код ответа ниже диапазона' => [['expected_status' => 99], 'expected_status'],
            'код ответа выше диапазона' => [['expected_status' => 600], 'expected_status'],
            'код ответа не число' => [['expected_status' => 'ок'], 'expected_status'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidPayloads')]
    public function test_invalid_input_returns_field_errors_and_saves_nothing(array $override, string $field): void
    {
        $response = $this->from('/checks/create')->post('/checks', [
            ...$this->validPayload(),
            ...$override,
        ]);

        $response->assertRedirect('/checks/create');
        $response->assertSessionHasErrors($field);

        $this->assertDatabaseCount('checks', 0);
    }

    public function test_the_arbitrary_interval_is_rejected_even_when_it_looks_reasonable(): void
    {
        // Произвольные интервалы делают сетку расписания (AD-8) непредсказуемой
        // и усложняют свёртку на ступени 1. Набор закрыт: 30/60/300/600.
        foreach ([1, 15, 120, 3600] as $interval) {
            $this->post('/checks', [...$this->validPayload(), 'interval_seconds' => $interval])
                ->assertSessionHasErrors('interval_seconds');
        }

        $this->assertDatabaseCount('checks', 0);
    }

    public function test_every_allowed_interval_is_accepted(): void
    {
        foreach ([30, 60, 300, 600] as $interval) {
            $this->post('/checks', [
                ...$this->validPayload(),
                'url' => "https://example.com/{$interval}",
                'interval_seconds' => $interval,
            ])->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('checks', 4);
    }

    public function test_update_with_invalid_data_leaves_the_stored_check_untouched(): void
    {
        $this->post('/checks', $this->validPayload());

        $ulid = DB::table('checks')->value('ulid');

        $this->from("/checks/{$ulid}/edit")->put("/checks/{$ulid}", [
            ...$this->validPayload(),
            'interval_seconds' => 45,
        ])->assertSessionHasErrors('interval_seconds');

        $this->assertDatabaseHas('checks', [
            'ulid' => $ulid,
            'interval_seconds' => 60,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'url' => 'https://example.com/health',
            'interval_seconds' => 60,
            'expected_status' => 200,
        ];
    }
}
