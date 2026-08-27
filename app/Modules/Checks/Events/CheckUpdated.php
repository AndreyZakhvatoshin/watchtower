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
     * @param  bool  $activationChanged  Сменилось ли is_active. Отдельный флаг,
     *                                   а не часть intervalChanged: включение
     *                                   проверки добавляет её в сетку, а
     *                                   выключение убирает — оба перехода
     *                                   меняют состав расписания, и без этого
     *                                   признака они приезжали как «просто
     *                                   перечитай поля».
     */
    public function __construct(
        public readonly string $ulid,
        public readonly bool $intervalChanged,
        public readonly bool $activationChanged,
    ) {}
}
