<?php

use App\Http\Controllers\Api\V1\CheckSetController;
use App\Http\Middleware\WorkerToken;
use Illuminate\Support\Facades\Route;

// Префикс api добавляет сам withRouting(api: ...), поэтому здесь только v1.
// Ломающее изменение контракта — новый префикс версии, а не правка этого файла.
Route::prefix('v1')
    ->middleware([
        // Числовая форма намеренно. throttle:api потребовал бы именованного
        // лимитера, объявленного через RateLimiter::for('api'); в проекте его
        // нет, и ThrottleRequests бросил бы MissingRateLimiterException —
        // каждый запрос вернул бы 500. Порог с запасом: исполнитель ходит
        // раз в 30 секунд, остальное — перебор токена.
        'throttle:60,1',
        WorkerToken::class,
        // Считает ETag по телу ответа и сам отвечает 304 на совпавший
        // If-None-Match. Слабые валидаторы W/, список значений и * разбирает
        // фреймворк — руками этого не писать.
        'cache.headers:etag',
    ])
    ->group(function (): void {
        Route::get('/checks', CheckSetController::class)->name('api.v1.checks.index');
    });
