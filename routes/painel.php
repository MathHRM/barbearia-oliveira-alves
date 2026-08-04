<?php

use App\Http\Controllers\Painel\AgendaController;
use App\Http\Controllers\Painel\AppointmentController;
use App\Http\Controllers\Painel\TimeBlockController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('painel')->name('painel.')->group(function () {
    Route::redirect('/', '/painel/agenda');

    Route::get('agenda', [AgendaController::class, 'index'])->name('agenda');

    Route::post('agendamentos', [AppointmentController::class, 'store'])->name('appointments.store');
    Route::post('agendamentos/{appointment}/compareceu', [AppointmentController::class, 'attended'])->name('appointments.attended');
    Route::post('agendamentos/{appointment}/faltou', [AppointmentController::class, 'noShow'])->name('appointments.no-show');
    Route::post('agendamentos/{appointment}/cancelar', [AppointmentController::class, 'cancel'])->name('appointments.cancel');

    Route::post('bloqueios', [TimeBlockController::class, 'store'])->name('blocks.store');
    Route::delete('bloqueios/{block}', [TimeBlockController::class, 'destroy'])->name('blocks.destroy');
});
