<?php

namespace App\Modules\Checks\Contracts;

/**
 * Допустимые интервалы опроса.
 *
 * Набор закрыт намеренно: произвольное число делает сетку расписания (AD-8)
 * непредсказуемой, а свёртку на ступени 1 — дороже, чем она должна быть.
 * Решение автора от 13.08.2026.
 */
enum CheckInterval: int
{
    case HalfMinute = 30;
    case Minute = 60;
    case FiveMinutes = 300;
    case TenMinutes = 600;

    /**
     * @return list<int>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): int => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::HalfMinute => '30 секунд',
            self::Minute => '1 минута',
            self::FiveMinutes => '5 минут',
            self::TenMinutes => '10 минут',
        };
    }
}
