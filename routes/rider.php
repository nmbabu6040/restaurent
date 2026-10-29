<?php

use App\Http\Controllers\Rider\PanelController;
use Illuminate\Support\Facades\Route;

Route::prefix('rider')->name('rider.')->middleware(['auth', 'can:access-rider'])->group(function () {
    Route::get('/', [PanelController::class, 'index'])->name('dashboard');
    Route::get('poll', [PanelController::class, 'poll'])->name('poll');
    Route::patch('online', [PanelController::class, 'toggleOnline'])->name('online');

    Route::get('deliveries/{delivery}', [PanelController::class, 'show'])->whereNumber('delivery')->name('deliveries.show');
    Route::post('deliveries/{delivery}/pickup', [PanelController::class, 'pickup'])->whereNumber('delivery')->name('deliveries.pickup');
    Route::post('deliveries/{delivery}/deliver', [PanelController::class, 'deliver'])->whereNumber('delivery')->name('deliveries.deliver');
    Route::post('deliveries/{delivery}/problem', [PanelController::class, 'problem'])->whereNumber('delivery')->name('deliveries.problem');
});
