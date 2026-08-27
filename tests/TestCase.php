<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Предохранитель на имя базы. RefreshDatabase выполняет migrate:fresh —
     * то есть роняет все таблицы той базы, до которой дотянулся. Один
     * экспортированный DB_DATABASE в оболочке, один запуск тестов — и рабочая
     * база пуста. Проверка стоит здесь, а не в CI: до CI ещё две ступени,
     * а стереть базу можно сегодня.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) config('database.connections.'.config('database.default').'.database');

        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException(
                "Тесты отказываются работать против базы «{$database}»: имя обязано оканчиваться на _test. ".
                'Проверь DB_DATABASE в окружении — <env> в phpunit.xml перекрывает его только с force="true".'
            );
        }
    }
}
