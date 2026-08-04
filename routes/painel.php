<?php

use App\Http\Controllers\Painel\AgendaController;
use App\Http\Controllers\Painel\AppointmentController;
use App\Http\Controllers\Painel\BarberController;
use App\Http\Controllers\Painel\CustomerController;
use App\Http\Controllers\Painel\DashboardController;
use App\Http\Controllers\Painel\ServiceController;
use App\Http\Controllers\Painel\TimeBlockController;
use App\Http\Controllers\Painel\WorkingHourController;
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

    Route::get('clientes', [CustomerController::class, 'index'])->name('customers');

    // o barbeiro edita a própria grade; a coluna dos outros nem chega na tela
    Route::get('horarios', [WorkingHourController::class, 'index'])->name('hours');
    Route::put('horarios/{barber}', [WorkingHourController::class, 'update'])->name('hours.update');

    // faturamento e cadastros são assunto do dono
    Route::middleware('owner')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('servicos', [ServiceController::class, 'index'])->name('services');
        Route::post('servicos', [ServiceController::class, 'store'])->name('services.store');
        Route::put('servicos/{service}', [ServiceController::class, 'update'])->name('services.update');
        Route::delete('servicos/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');

        Route::get('barbeiros', [BarberController::class, 'index'])->name('barbers');
        Route::post('barbeiros', [BarberController::class, 'store'])->name('barbers.store');
        Route::put('barbeiros/{barber}', [BarberController::class, 'update'])->name('barbers.update');
    });
});
