<?php

namespace App\Modules\Checks\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class CheckUpdated
{
    use Dispatchable;

    /**
     * @param  bool  $intervalChanged  Сменился ли интервал: на ступени 1 это
     *                                 сигнал исполнителю пересобрать сетку,
     *                                 а не просто перечитать поля.
     */
    public function __construct(
        public readonly string $ulid,
        public readonly bool $intervalChanged,
    ) {}
}
