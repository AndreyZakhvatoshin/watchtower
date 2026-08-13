<?php

namespace App\Modules\Checks\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Факт свершившийся, прошедшее время (Consistency Conventions).
 *
 * Событие несёт только внешний идентификатор: подписчик, которому нужны
 * подробности, спрашивает их через CheckRepository, а не получает копию
 * состояния, которая протухнет.
 */
final class CheckCreated
{
    use Dispatchable;

    public function __construct(public readonly string $ulid) {}
}
