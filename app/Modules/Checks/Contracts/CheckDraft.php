<?php

namespace App\Modules\Checks\Contracts;

/**
 * Намерение завести или изменить проверку.
 *
 * Отдельный тип, а не массив: соседний модуль и транспорт передают ровно
 * те поля, которыми проверка описывается, и промах ловится типами, а не
 * отладкой на проде.
 */
final readonly class CheckDraft
{
    public function __construct(
        public string $url,
        public int $intervalSeconds,
        public int $expectedStatus,
        public bool $isActive = true,
    ) {}
}
