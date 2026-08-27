<?php

use App\Http\Controllers\CheckController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Ключ маршрута — ULID, а не внутренний id: наружу последовательный
// идентификатор не выходит нигде, включая адресную строку.
//
// whereUlid ограничивает формат параметра на уровне маршрутизатора: мусор
// в адресе становится 404 до входа в контроллер, до обращения к базе и до
// записи в лог. Без ограничения каждая строка из адресной строки уходила
// в запрос.
Route::get('/checks', [CheckController::class, 'index'])->name('checks.index');
Route::get('/checks/create', [CheckController::class, 'create'])->name('checks.create');
Route::post('/checks', [CheckController::class, 'store'])->name('checks.store');
Route::get('/checks/{ulid}/edit', [CheckController::class, 'edit'])->whereUlid('ulid')->name('checks.edit');
Route::put('/checks/{ulid}', [CheckController::class, 'update'])->whereUlid('ulid')->name('checks.update');
Route::delete('/checks/{ulid}', [CheckController::class, 'destroy'])->whereUlid('ulid')->name('checks.destroy');
