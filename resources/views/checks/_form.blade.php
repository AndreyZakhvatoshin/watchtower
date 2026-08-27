@if ($errors->any())
    <ul class="errors">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif

<label for="url">Адрес</label>
{{-- type="url" даёт браузеру проверку формата до отправки и клавиатуру
     с двоеточием и слэшем на телефоне. Серверную валидацию не заменяет. --}}
<input type="url" id="url" name="url" value="{{ old('url', $check->url ?? '') }}" required>

<label for="interval_seconds">Интервал</label>
{{-- Список, а не свободное поле: произвольный интервал делает сетку
     расписания (AD-8) непредсказуемой. --}}
@php
    // Выбранное значение считается один раз и явно. Прежняя строка сравнивала
    // каждый вариант со значением из old(): значение вне набора не совпадало
    // ни с одним, ни один option не получал selected, и браузер молча
    // показывал первый — 30 секунд вместо отвергнутых 45. Человек видел
    // сообщение об ошибке и рядом с ним подставленный чужой интервал.
    $selectedInterval = (int) old('interval_seconds', $check->intervalSeconds ?? 60);
    $intervalIsKnown = in_array($selectedInterval, array_map(fn ($case) => $case->value, $intervals), true);
@endphp
<select id="interval_seconds" name="interval_seconds" required>
    @unless ($intervalIsKnown)
        {{-- Пустой вариант вместо тихой подстановки: выбор остаётся за человеком. --}}
        <option value="" selected>— выберите интервал —</option>
    @endunless
    @foreach ($intervals as $interval)
        <option value="{{ $interval->value }}" @selected($selectedInterval === $interval->value)>
            {{ $interval->label() }}
        </option>
    @endforeach
</select>

<label for="expected_status">Ожидаемый код ответа</label>
<input type="number" id="expected_status" name="expected_status" min="100" max="599"
       value="{{ old('expected_status', $check->expectedStatus ?? 200) }}" required>

<div class="actions">
    {{-- Снятый флажок браузер не присылает вовсе, поэтому рядом лежит скрытое
         поле: без него выключить проверку было бы невозможно. --}}
    <input type="hidden" name="is_active" value="0">
    <label for="is_active" style="margin:0">
        <input type="checkbox" id="is_active" name="is_active" value="1"
               @checked((bool) old('is_active', $check->isActive ?? true))>
        включена
    </label>
</div>

<div class="actions">
    <button type="submit">Сохранить</button>
    <a href="{{ route('checks.index') }}">Отмена</a>
</div>
