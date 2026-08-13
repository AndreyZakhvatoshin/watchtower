<?php

namespace Tests\Unit\Checks;

use App\Modules\Checks\Contracts\CheckInterval;
use PHPUnit\Framework\TestCase;

class CheckIntervalTest extends TestCase
{
    public function test_the_allowed_set_is_exactly_the_one_the_author_decided_on(): void
    {
        // Решение от 13.08.2026: 30/60/300/600 секунд. Набор закрыт — сетка
        // расписания (AD-8) и свёртка на ступени 1 опираются на его конечность.
        $this->assertSame([30, 60, 300, 600], CheckInterval::values());
    }

    public function test_an_interval_outside_the_set_has_no_case(): void
    {
        $this->assertNull(CheckInterval::tryFrom(45));
        $this->assertNull(CheckInterval::tryFrom(3600));
    }

    public function test_every_case_carries_a_label_for_the_form(): void
    {
        foreach (CheckInterval::cases() as $case) {
            $this->assertNotSame('', $case->label());
        }
    }
}
