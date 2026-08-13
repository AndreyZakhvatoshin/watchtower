<?php

namespace App\Modules\Checks\Internal;

use App\Modules\Checks\Contracts\CheckDraft;
use App\Modules\Checks\Contracts\CheckRepository;
use App\Modules\Checks\Contracts\CheckSnapshot;
use App\Modules\Checks\Events\CheckCreated;
use App\Modules\Checks\Events\CheckDeleted;
use App\Modules\Checks\Events\CheckUpdated;
use Illuminate\Support\Facades\Log;

class EloquentCheckRepository implements CheckRepository
{
    private const MODULE = 'checks';

    public function all(): array
    {
        return Check::query()
            ->orderByDesc('id')
            ->get()
            ->map($this->toSnapshot(...))
            ->all();
    }

    public function active(): array
    {
        // Удалённые отсекает глобальный scope SoftDeletes — фильтр по deleted_at
        // руками был бы ловушкой, которая однажды забудется в одной выборке.
        return Check::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map($this->toSnapshot(...))
            ->all();
    }

    public function findByUlid(string $ulid): ?CheckSnapshot
    {
        $check = Check::query()->where('ulid', $ulid)->first();

        return $check === null ? null : $this->toSnapshot($check);
    }

    public function create(CheckDraft $draft): CheckSnapshot
    {
        $check = new Check([
            'url' => $draft->url,
            'interval_seconds' => $draft->intervalSeconds,
            'expected_status' => $draft->expectedStatus,
            'is_active' => $draft->isActive,
            // Сетка расписания начинается в момент рождения проверки.
            'interval_applied_at' => now(),
        ]);

        $check->save();

        // Перечитываем: в базе timestamptz хранит то, что реально записано,
        // а не то, что осталось в памяти до усечения дробной части.
        $check->refresh();

        $this->log('check created', $check);
        CheckCreated::dispatch($check->ulid);

        return $this->toSnapshot($check);
    }

    public function update(string $ulid, CheckDraft $draft): CheckSnapshot
    {
        $check = Check::query()->where('ulid', $ulid)->firstOrFail();

        // Сравнение старого и нового значения явное. Форма сохраняет все поля
        // разом, и без этой проверки сетка расписания сдвигалась бы на правке
        // адреса — тихо и без единой ошибки.
        $intervalChanged = $check->interval_seconds !== $draft->intervalSeconds;

        $check->fill([
            'url' => $draft->url,
            'interval_seconds' => $draft->intervalSeconds,
            'expected_status' => $draft->expectedStatus,
            'is_active' => $draft->isActive,
        ]);

        if ($intervalChanged) {
            $check->interval_applied_at = now();
        }

        $check->save();
        $check->refresh();

        $this->log('check updated', $check, ['interval_changed' => $intervalChanged]);
        CheckUpdated::dispatch($check->ulid, $intervalChanged);

        return $this->toSnapshot($check);
    }

    public function delete(string $ulid): void
    {
        $check = Check::query()->where('ulid', $ulid)->firstOrFail();

        // Удаление мягкое: история результатов на ступени 1 переживает удаление
        // проверки, а единственный разрешённый cross-модульный внешний ключ
        // на checks.id не ломается. is_active снимается заодно — так проверка
        // уходит из активного набора даже там, где кто-то забудет про scope.
        $check->is_active = false;
        $check->save();
        $check->delete();

        $this->log('check deleted', $check);
        CheckDeleted::dispatch($check->ulid);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function log(string $message, Check $check, array $extra = []): void
    {
        // module читает JsonFormatter из контекста; correlation_id уже лежит
        // там от middleware. Внутренний id в лог не уходит по той же причине,
        // по какой не уходит наружу.
        Log::info($message, [
            'module' => self::MODULE,
            'ulid' => $check->ulid,
            ...$extra,
        ]);
    }

    private function toSnapshot(Check $check): CheckSnapshot
    {
        return new CheckSnapshot(
            ulid: $check->ulid,
            url: $check->url,
            intervalSeconds: $check->interval_seconds,
            expectedStatus: $check->expected_status,
            isActive: $check->is_active,
            intervalAppliedAt: $check->interval_applied_at,
            createdAt: $check->created_at,
            updatedAt: $check->updated_at,
        );
    }
}
