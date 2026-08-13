@extends('layouts.app')

@section('title', 'Правка проверки — Watchtower')

@section('content')
    <h2>Правка проверки</h2>
    <p class="muted"><code>{{ $check->ulid }}</code></p>

    <form method="post" action="{{ route('checks.update', $check->ulid) }}">
        @csrf
        @method('PUT')
        @include('checks._form')
    </form>
@endsection
