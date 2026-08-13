@extends('layouts.app')

@section('title', 'Новая проверка — Watchtower')

@section('content')
    <h2>Новая проверка</h2>

    <form method="post" action="{{ route('checks.store') }}">
        @csrf
        @include('checks._form')
    </form>
@endsection
