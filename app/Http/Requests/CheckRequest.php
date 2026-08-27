<?php

namespace App\Http\Requests;

use App\Modules\Checks\Contracts\CheckDraft;
use App\Modules\Checks\Contracts\CheckInterval;
use App\Rules\NotLinkLocalAddress;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Валидация живёт в транспорте, а не в модуле (AD-6): модуль принимает
 * CheckDraft и вправе считать его осмысленным.
 *
 * Правки и создание проверяются одинаково — расхождение правил между двумя
 * формами это классический способ завести данные, которые нельзя создать,
 * но можно сохранить.
 */
class CheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Аутентификация интерфейса — Story 1.11. До неё разрыв осознанный.
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // url:http,https отвергает и мусор, и схему ftp, и адрес без схемы.
            // NotLinkLocalAddress добивает то, что url пропускает: 169.254.0.0/16
            // и fe80::/10. Петля и RFC1918 разрешены намеренно — наблюдение за
            // собственной инфраструктурой это цель проекта, а не обход правила.
            'url' => ['required', 'string', 'max:2048', 'url:http,https', new NotLinkLocalAddress],
            'interval_seconds' => ['required', 'integer', Rule::in(CheckInterval::values())],
            'expected_status' => ['required', 'integer', 'between:100,599'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'url' => 'адрес',
            'interval_seconds' => 'интервал',
            'expected_status' => 'ожидаемый код ответа',
            'is_active' => 'включена',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // Шаблоны фреймворка английские, а имена полей из attributes()
            // русские — вместе они дают «The адрес field is required.».
            // Смешанная строка чинится не переводом всего пакета сообщений,
            // а своими текстами на те правила, которые здесь и стоят.
            'url.required' => 'Адрес обязателен.',
            'url.max' => 'Адрес длиннее 2048 символов не бывает.',
            'url.url' => 'Адрес должен быть ссылкой по http или https.',
            'interval_seconds.required' => 'Интервал обязателен.',
            'interval_seconds.integer' => 'Интервал задаётся числом секунд.',
            'expected_status.required' => 'Ожидаемый код ответа обязателен.',
            'expected_status.integer' => 'Код ответа задаётся числом.',
            'is_active.boolean' => 'Состояние проверки — включена или выключена.',
            'interval_seconds.in' => 'Интервал выбирается из списка: 30, 60, 300 или 600 секунд.',
            'expected_status.between' => 'Код ответа HTTP лежит между 100 и 599.',
        ];
    }

    public function toDraft(): CheckDraft
    {
        return new CheckDraft(
            url: $this->string('url')->toString(),
            intervalSeconds: $this->integer('interval_seconds'),
            expectedStatus: $this->integer('expected_status'),
            // Флажок, который не отметили, браузер не присылает вовсе.
            // Различие форм тут реальное, и раньше комментарий его описывал,
            // а код — нет: правка без поля молча включала выключенную проверку.
            // Создание без поля — «включена»: новую проверку заводят, чтобы она
            // работала. Правка без поля — «выключена»: форма правки всегда шлёт
            // скрытое значение, и его отсутствие означает снятый флажок,
            // а не «оставь как было».
            isActive: $this->isMethod('POST')
                ? ($this->has('is_active') ? $this->boolean('is_active') : true)
                : $this->boolean('is_active'),
        );
    }
}
