<?php

namespace App\Modules\Checks\Contracts;

use Carbon\CarbonImmutable;

/**
 * Проверка в том виде, в каком её видят снаружи модуля.
 *
 * Наружу не уходит ни Eloquent-модель, ни внутренний bigint (AD-6): первое
 * дало бы соседу доступ к схеме в обход контракта, второе — объём и полную
 * перечислимость (id+1 существует, и его можно запросить).
 *
 * Что ULID НЕ скрывает — порядок создания. Старшие 48 бит это метка времени
 * с точностью до миллисекунды, поэтому сортировка по ULID совпадает с
 * сортировкой по времени, а разница двух значений читается календарно.
 * Прежняя формулировка «раскрыл бы объём и порядок» была неверна во второй
 * половине: против объёма ULID работает, против порядка — нет. Если порядок
 * когда-нибудь станет скрывать нужно, это UUIDv4, а не ULID.
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

    public function interval(): ?CheckInterval
    {
        // tryFrom, а не from: значение вне набора не должно ронять весь список.
        // CHECK в схеме такую строку теперь не пропустит, но представление
        // обязано пережить и её — строка может приехать восстановлением
        // бэкапа старой схемы или расширением набора, забывшим про миграцию.
        return CheckInterval::tryFrom($this->intervalSeconds);
    }

    public function intervalLabel(): string
    {
        return $this->interval()?->label() ?? "{$this->intervalSeconds} с (вне набора)";
    }
}
