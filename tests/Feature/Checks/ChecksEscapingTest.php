<?php

namespace Tests\Feature\Checks;

use App\Modules\Checks\Contracts\CheckDraft;
use App\Modules\Checks\Contracts\CheckRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Адрес проверки задаёт автор, а показывается он в разметке. Проверяем, что
 * значение из базы нигде не переходит границу «данные → код».
 *
 * Проверка заводится репозиторием, а не через форму, намеренно: правила
 * валидации меняются, а представление обязано оставаться безопасным при любом
 * содержимом строки, которое уже лежит в базе.
 */
class ChecksEscapingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Апостроф — ключ к прежней дыре: Blade превращал его в `&#039;`, а
     * HTML-парсер декодировал обратно ДО передачи значения атрибута движку JS.
     */
    private const PAYLOAD_URL = "https://example.com/health?q='-alert(1)-'";

    public function test_the_address_never_reaches_a_javascript_context(): void
    {
        $this->createCheck(self::PAYLOAD_URL);

        $response = $this->get('/checks');

        $response->assertOk();

        // Обработчиков в атрибутах нет вовсе: подтверждение вешает скрипт.
        $response->assertDontSee('onsubmit', false);

        // Адрес виден пользователю, но в исходнике лежит только экранированным:
        // assertSee экранирует ожидание, assertDontSee с false — нет.
        $response->assertSee(self::PAYLOAD_URL);
        $response->assertDontSee(self::PAYLOAD_URL, false);
    }

    private function createCheck(string $url): void
    {
        app(CheckRepository::class)->create(new CheckDraft(
            url: $url,
            intervalSeconds: 60,
            expectedStatus: 200,
            isActive: true,
        ));
    }
}
