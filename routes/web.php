<?php

use App\Http\Controllers\SalaController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SalaController::class, 'inicio'])->name('inicio');
Route::post('/salas', [SalaController::class, 'crear'])->name('salas.crear');
Route::get('/sala/{sala}', [SalaController::class, 'ver'])->name('salas.ver');
Route::post('/sala/{sala}/unirse', [SalaController::class, 'unirse'])->name('salas.unirse');
Route::get('/sala/{sala}/estado', [SalaController::class, 'estado'])->name('salas.estado');
Route::post('/sala/{sala}/accion', [SalaController::class, 'accion'])->name('salas.accion');
Route::post('/sala/{sala}/mensaje', [SalaController::class, 'mensaje'])->middleware('throttle:30,1')->name('salas.mensaje');
Route::post('/sala/{sala}/revancha', [SalaController::class, 'revancha'])->name('salas.revancha');
