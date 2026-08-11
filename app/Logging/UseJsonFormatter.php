<?php

namespace App\Logging;

use Illuminate\Log\Logger;

/**
 * Tap-класс: навешивает JsonFormatter на все обработчики канала.
 *
 * Через tap, а не через ключ 'formatter', потому что 'formatter' работает
 * только у драйверов monolog/custom, а нам нужен и daily тоже.
 */
class UseJsonFormatter
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter(new JsonFormatter);
        }
    }
}
