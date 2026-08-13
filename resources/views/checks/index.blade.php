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
                        <td>{{ $check->interval()->label() }}</td>
                        <td>{{ $check->expectedStatus }}</td>
                        <td>{{ $check->isActive ? 'включена' : 'выключена' }}</td>
                        {{-- Время показывается в UTC: хранится и сравнивается оно
                             тоже в UTC (AD-8, NFR6), перевод в местную зону был бы
                             единственным местом расхождения. --}}
                        <td class="muted">{{ $check->intervalAppliedAt->toIso8601ZuluString('millisecond') }}</td>
                        <td>
                            <a href="{{ route('checks.edit', $check->ulid) }}">править</a>
                            <form class="inline" method="post" action="{{ route('checks.destroy', $check->ulid) }}"
                                  onsubmit="return confirm('Удалить проверку {{ $check->url }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit">удалить</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
