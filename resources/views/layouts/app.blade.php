<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Watchtower')</title>
    {{-- Стили встроены намеренно: сборки фронта на этой ступени нет и не будет
         (правило границы языков — Blade, никакого SPA). --}}
    <style>
        :root { color-scheme: light dark; }
        body { font: 16px/1.5 system-ui, sans-serif; margin: 0 auto; max-width: 60rem; padding: 2rem 1rem; }
        h1 { font-size: 1.5rem; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border-bottom: 1px solid #8883; padding: .5rem; text-align: left; vertical-align: top; }
        th { font-weight: 600; }
        code { font-size: .875em; }
        form.inline { display: inline; }
        label { display: block; margin: 1rem 0 .25rem; font-weight: 600; }
        input[type=text], input[type=number], select { font: inherit; padding: .375rem; width: 100%; max-width: 32rem; }
        .errors { border-left: 3px solid #c00; padding-left: .75rem; }
        .errors li { color: #c00; }
        .status { border-left: 3px solid #090; padding-left: .75rem; }
        .muted { opacity: .7; }
        .actions { margin-top: 1.5rem; display: flex; gap: .75rem; align-items: center; }
        button { font: inherit; padding: .375rem .75rem; }
    </style>
</head>
<body>
    <h1><a href="{{ route('checks.index') }}">Watchtower</a></h1>

    @if (session('status'))
        <p class="status">{{ session('status') }}</p>
    @endif

    @yield('content')
</body>
</html>
