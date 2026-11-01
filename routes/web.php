<?php

use App\Http\Controllers\Auth\SessionController;
use Illuminate\Support\Facades\Route;

// No exponer los CRUD web heredados: se mantienen los endpoints /api con autorizacion tenant.
Route::get('/', function () {
    return view('welcome');
});

// Sesiones de primera parte para React + Sanctum. Protegidas por CSRF del grupo web.
Route::post('/login', [SessionController::class, 'store'])
    ->middleware(['guest:web', 'throttle:5,1'])
    ->name('login');

Route::post('/logout', [SessionController::class, 'destroy'])
    ->middleware('auth:web')
    ->name('logout');
