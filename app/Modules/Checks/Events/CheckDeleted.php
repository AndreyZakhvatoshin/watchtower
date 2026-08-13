<?php

namespace App\Modules\Checks\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class CheckDeleted
{
    use Dispatchable;

    public function __construct(public readonly string $ulid) {}
}
