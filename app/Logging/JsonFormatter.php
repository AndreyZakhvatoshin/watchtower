<?php

namespace App\Logging;

use Monolog\Formatter\NormalizerFormatter;
use Monolog\LogRecord;

/**
 * Формат строки лога, зафиксированный NFR8: ts, level, module, correlation_id, msg.
 *
 * Порядок ключей стабилен — на него опирается грепанье глазами до появления Loki
 * на ступени 3. Остальной контекст уезжает в ctx, чтобы не размывать верхний уровень.
 */
class JsonFormatter extends NormalizerFormatter
{
    public function __construct()
    {
        // ISO 8601 с микросекундами и зоной: сортируется лексикографически и
        // не теряет порядок событий внутри секунды.
        parent::__construct('Y-m-d\TH:i:s.uP');
    }

    public function format(LogRecord $record): string
    {
        /** @var array<string, mixed> $normalized */
        $normalized = parent::format($record);

        $context = $normalized['context'] ?? [];
        $extra = $normalized['extra'] ?? [];

        // module задаётся вызывающим кодом: Log::withContext(['module' => 'checker']).
        // Пока модулей нет, остаётся имя канала — «app».
        $module = $context['module'] ?? $extra['module'] ?? $record->channel;
        unset($context['module'], $extra['module']);

        $correlationId = $context['correlation_id'] ?? $extra['correlation_id'] ?? null;
        unset($context['correlation_id'], $extra['correlation_id']);

        $line = [
            'ts' => $normalized['datetime'],
            'level' => strtolower($record->level->getName()),
            'module' => $module,
            'correlation_id' => $correlationId,
            'msg' => $record->message,
        ];

        if ($context !== []) {
            $line['ctx'] = $context;
        }

        if ($extra !== []) {
            $line['extra'] = $extra;
        }

        return $this->toJson($line, true)."\n";
    }

    public function formatBatch(array $records): string
    {
        return implode('', array_map($this->format(...), $records));
    }
}
