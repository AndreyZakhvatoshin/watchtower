<?php

namespace App\Http\Resources;

use App\Modules\Checks\Contracts\CheckSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Проверка в том виде, в каком её забирает исполнитель.
 *
 * @property-read CheckSnapshot $resource
 */
class CheckResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $check = $this->resource;

        return [
            // Наружу только ULID: внутренний bigint не покидает модуль.
            // Этим же идентификатором исполнитель адресует результат
            // в Story 1.4 — он и есть check_id ключа идемпотентности (AD-4).
            'ulid' => $check->ulid,
            'url' => $check->url,
            'interval_seconds' => $check->intervalSeconds,
            'expected_status' => $check->expectedStatus,

            // Момент, от которого исполнитель строит сетку расписания (AD-8):
            // точки k · interval_seconds от эпохи, из них берутся только те,
            // что не раньше этого момента.
            'interval_applied_at' => $check->intervalAppliedAt->toIso8601ZuluString('millisecond'),

            // is_active наружу не уходит: в наборе только включённые,
            // и выключение выражается исчезновением из набора, а не флагом.
            // Поле, которое всегда true, — шум, сдвигающий ETag без смысла.
        ];
    }
}
