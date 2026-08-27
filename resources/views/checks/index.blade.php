@extends('layouts.app')

@section('title', 'Проверки — Watchtower')

@section('content')
    <p><a href="{{ route('checks.create') }}">Завести проверку</a></p>

    @if ($checks === [])
        <p class="muted">Проверок пока нет.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Идентификатор</th>
                    <th>Адрес</th>
                    <th>Интервал</th>
                    <th>Ждём код</th>
                    <th>Состояние</th>
                    <th>Интервал применён</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($checks as $check)
                    <tr>
                        {{-- Наружу уходит только ULID: внутренний bigint раскрыл бы
                             объём и порядок создания. --}}
                        <td><code>{{ $check->ulid }}</code></td>
                        <td>{{ $check->url }}</td>
                        <td>{{ $check->intervalLabel() }}</td>
                        <td>{{ $check->expectedStatus }}</td>
                        <td>{{ $check->isActive ? 'включена' : 'выключена' }}</td>
                        {{-- Время показывается в UTC: хранится и сравнивается оно
                             тоже в UTC (AD-8, NFR6), перевод в местную зону был бы
                             единственным местом расхождения. --}}
                        <td class="muted">{{ $check->intervalAppliedAt->toIso8601ZuluString('millisecond') }}</td>
                        <td>
                            <a href="{{ route('checks.edit', $check->ulid) }}">править</a>
                            {{-- Текст подтверждения едет data-атрибутом, а не внутри
                                 onsubmit. Атрибут — данные: Blade экранирует значение
                                 для HTML, браузер отдаёт его скрипту через dataset
                                 как строку, и разбором JS оно не проходит нигде.

                                 Прежняя схема подставляла адрес внутрь строки в
                                 onsubmit, и экранирования Blade там не хватало:
                                 HTML-парсер декодировал `&#039;` обратно в апостроф
                                 ДО того, как значение атрибута попадало в движок JS.
                                 Апостроф в адресе закрывал строку, остаток адреса
                                 становился кодом — хранимый XSS. --}}
                            <form class="inline" method="post" action="{{ route('checks.destroy', $check->ulid) }}"
                                  data-confirm="Удалить проверку {{ $check->url }}?">
                                @csrf
                                @method('DELETE')
                                <button type="submit">удалить</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Один делегированный обработчик на всю таблицу вместо обработчика в
             каждой строке: событие submit всплывает до документа. Сборки фронта
             на этой ступени нет (правило границы языков), поэтому скрипт лежит
             здесь же, как и стили в макете. --}}
        <script>
            document.addEventListener('submit', function (event) {
                var message = event.target.dataset.confirm;

                if (message && ! window.confirm(message)) {
                    event.preventDefault();
                }
            });
        </script>
    @endif
@endsection
