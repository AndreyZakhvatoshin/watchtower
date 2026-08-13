<?php

namespace App\Modules\Checks\Contracts;

use Carbon\CarbonImmutable;

/**
 * Проверка в том виде, в каком её видят снаружи модуля.
 *
 * Наружу не уходит ни Eloquent-модель, ни внутренний bigint (AD-6): первое
 * дало бы соседу доступ к схеме в обход контракта, второе раскрыло бы объём
 * и порядок создания.
 */
final readonly class CheckSnapshot
{
    public function __construct(
        public string $ulid,
        public string $url,
        public int $intervalSeconds,
        public int $expectedStatus,
        public bool $isActive,
        public CarbonImmutable $intervalAppliedAt,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    public function interval(): CheckInterval
    {
        return CheckInterval::from($this->intervalSeconds);
    }
}
