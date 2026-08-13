<?php

namespace App\Modules\Checks\Contracts;

/**
 * Единственный вход в модуль Checks (AD-6).
 *
 * Прямое обращение к Eloquent-модели, к таблице `checks` или к внутреннему
 * сервису из другого модуля запрещено. Правило станет автоматической проверкой
 * в Story 2.6 и гейтом CI в 6.5 — код пишется так, будто проверка уже есть.
 */
interface CheckRepository
{
    /**
     * Все живые проверки, включая выключенные, — для интерфейса автора.
     *
     * @return list<CheckSnapshot>
     */
    public function all(): array;

    /**
     * Набор, который заберёт исполнитель на ступени 1: включённые и неудалённые.
     *
     * @return list<CheckSnapshot>
     */
    public function active(): array;

    public function findByUlid(string $ulid): ?CheckSnapshot;

    public function create(CheckDraft $draft): CheckSnapshot;

    public function update(string $ulid, CheckDraft $draft): CheckSnapshot;

    public function delete(string $ulid): void;
}
