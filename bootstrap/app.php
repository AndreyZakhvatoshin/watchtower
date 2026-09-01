<?php

use App\Http\Middleware\CorrelationId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Первым в стеке: correlation_id должен попасть в лог любой записи,
        // включая те, что пишут более поздние middleware. prepend кладёт
        // в глобальный стек, то есть и в веб, и в API.
        $middleware->prepend(CorrelationId::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Единый конверт ошибок из конвенций. Заводится здесь, а не в
        // middleware: тогда через него проходят и 422 валидации, и 429 от
        // ограничения частоты, и 404 несуществующего маршрута — всё, что
        // рождается вне нашего кода. Обработчик по умолчанию отдал бы
        // {"message": ...}, то есть мимо конвенции.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $e->getStatusCode();

            return response()->json([
                'error' => [
                    // Слаг стабилен и машиночитаем: исполнитель смотрит на него,
                    // а не на текст, который переводится и переписывается.
                    'code' => match ($status) {
                        401 => 'unauthorized',
                        403 => 'forbidden',
                        404 => 'not_found',
                        405 => 'method_not_allowed',
                        422 => 'validation_failed',
                        429 => 'too_many_requests',
                        default => $status >= 500 ? 'server_error' : 'error',
                    },
                    'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Запрос не выполнен.',
                ],
            ], $status, $e->getHeaders());
        });
    })->create();
