<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\BookingController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [BookingController::class, 'index'])->name('home');
Route::get('api/availability', [AvailabilityController::class, 'index'])->name('availability');
Route::post('agendamentos', [AppointmentController::class, 'store'])->name('appointments.store');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
