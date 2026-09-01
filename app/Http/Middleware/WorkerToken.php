<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Зона доступа «исполнитель» (AD-13): токен в заголовке, ротируемый, живёт
 * в секретах окружения. Сессий здесь нет и быть не должно — исполнитель
 * не человек и cookie не хранит.
 */
class WorkerToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // Приведение к строке обязательно: bearerToken() отдаёт null, когда
        // заголовка нет, а hash_equals(string, null) в PHP 8 бросает TypeError —
        // это дало бы 500 вместо 401 в самом частом сценарии отказа.
        $presented = (string) $request->bearerToken();

        /** @var list<string> $known */
        $known = config('watchtower.worker_tokens', []);

        foreach ($known as $token) {
            // hash_equals, а не ===: посимвольное сравнение завершается на
            // первом несовпадении, и время ответа выдаёт длину общего префикса.
            if (hash_equals($token, $presented)) {
                return $next($request);
            }
        }

        $this->refuse($request, $presented === '' ? 'token_missing' : 'token_mismatch');
    }

    /**
     * Отказ формируется одним выражением в одной точке: два разных места
     * throw дали бы разные тела при включённой отладке, и «нет токена»
     * стало бы отличимо от «неверный токен».
     *
     * Причина уходит в лог, но не в ответ — снаружи оба случая неразличимы.
     */
    private function refuse(Request $request, string $reason): never
    {
        Log::warning('worker token refused', [
            'module' => 'checks',
            'reason' => $reason,
            'path' => $request->path(),
            // Ни токена, ни его части, ни длины: в логе они не нужны никому,
            // а лог уезжает в файл и однажды уедет в Loki.
        ]);

        // Заголовок WWW-Authenticate обязателен по RFC 9110 §15.5.2, но
        // без параметров error и error_description из RFC 6750: они прямо
        // сообщили бы, отсутствовал токен или не подошёл.
        throw new UnauthorizedHttpException('Bearer', 'Доступ только по токену исполнителя.');
    }
}
