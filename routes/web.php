<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\BookingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BookingController::class, 'home'])->name('home');
Route::get('agendar', [BookingController::class, 'index'])->name('booking.index');
Route::get('api/availability', [AvailabilityController::class, 'index'])->name('availability');
Route::post('agendamentos', [AppointmentController::class, 'store'])->name('appointments.store');
Route::get('agendamentos', [AppointmentController::class, 'lookupPage'])->name('appointments.lookup');
Route::post('agendamentos/consultar', [AppointmentController::class, 'lookup'])->name('appointments.lookup.submit');
Route::get('agendamentos/{token}', [AppointmentController::class, 'show'])->name('appointments.show');
Route::get('agendamentos/{token}/agenda.ics', [AppointmentController::class, 'ics'])->name('appointments.ics');
Route::post('agendamentos/{token}/cancelar', [AppointmentController::class, 'cancel'])->name('appointments.cancel');

// o starter kit manda para `dashboard` depois do login; no painel, a porta de entrada é a agenda
Route::redirect('dashboard', '/painel/agenda')->middleware('auth')->name('dashboard');

require __DIR__.'/painel.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
