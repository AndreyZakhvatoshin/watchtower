<?php

use App\Http\Controllers\CheckController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Ключ маршрута — ULID, а не внутренний id: наружу последовательный
// идентификатор не выходит нигде, включая адресную строку.
Route::get('/checks', [CheckController::class, 'index'])->name('checks.index');
Route::get('/checks/create', [CheckController::class, 'create'])->name('checks.create');
Route::post('/checks', [CheckController::class, 'store'])->name('checks.store');
Route::get('/checks/{ulid}/edit', [CheckController::class, 'edit'])->name('checks.edit');
Route::put('/checks/{ulid}', [CheckController::class, 'update'])->name('checks.update');
Route::delete('/checks/{ulid}', [CheckController::class, 'destroy'])->name('checks.destroy');
