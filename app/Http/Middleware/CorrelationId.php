<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Сквозной идентификатор запроса (NFR8).
 *
 * Принимаем чужой X-Correlation-Id, чтобы на ступени 3 трассировка
 * PHP -> Go-чекер склеилась по одному ключу. Не доверяем ему как данным:
 * значение обрезается и фильтруется, так как уходит в лог и в заголовок ответа.
 */
class CorrelationId
{
    private const HEADER = 'X-Correlation-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = (string) $request->headers->get(self::HEADER, '');
        $id = preg_match('/^[A-Za-z0-9._-]{1,64}$/', $incoming) === 1
            ? $incoming
            : (string) Str::uuid();

        $request->headers->set(self::HEADER, $id);
        Log::withContext(['correlation_id' => $id]);

        $response = $next($request);
        $response->headers->set(self::HEADER, $id);

        return $response;
    }
}
