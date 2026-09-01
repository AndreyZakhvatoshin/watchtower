<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CheckResource;
use App\Modules\Checks\Contracts\CheckRepository;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

/**
 * Половина «тянем» контракта AD-3. Исполнитель забирает набор целиком,
 * свежесть определяется ETag, второго канала между ядром и исполнителем
 * не существует.
 */
class CheckSetController extends Controller
{
    public function __construct(private readonly CheckRepository $checks) {}

    public function __invoke(): AnonymousResourceCollection
    {
        // active() уже отсекает выключенные и мягко удалённые. Своей выборки
        // здесь нет и быть не может: транспорт обращается к модулю только
        // через контракт (AD-6).
        $set = $this->checks->active();

        Log::info('check set served', [
            'module' => 'checks',
            'count' => count($set),
        ]);

        // collection() кладёт массив под ключ data — конверт из конвенций
        // получается даром, руками его собирать не надо.
        return CheckResource::collection($set);
    }
}
