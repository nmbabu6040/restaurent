<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MenuCategoryController;
use App\Http\Controllers\Admin\MenuItemController;
use App\Http\Controllers\Admin\ReservationController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SubscriberController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\OrderController;
use App\Support\AdminResources;
use App\Http\Controllers\Admin\AppearanceController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/login', [AuthController::class, 'create'])->middleware('guest')->name('login');
Route::post('/admin/login', [AuthController::class, 'store'])->middleware(['guest', 'throttle:20,1'])->name('login.store');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'can:access-admin'])->group(function () {
    Route::post('logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::middleware('can:handle-guests')->group(function () {
        Route::get('reservations', [ReservationController::class, 'index'])->name('reservations.index');
        Route::get('reservations/{reservation}', [ReservationController::class, 'show'])->name('reservations.show');
        Route::patch('reservations/{reservation}/status', [ReservationController::class, 'updateStatus'])->name('reservations.status');

        Route::get('messages', [ContactMessageController::class, 'index'])->name('messages.index');
        Route::get('messages/{message}', [ContactMessageController::class, 'show'])->name('messages.show');
        Route::delete('messages/{message}', [ContactMessageController::class, 'destroy'])->name('messages.destroy');
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
    });

    Route::middleware('can:manage-menu')->group(function () {
        Route::resource('categories', MenuCategoryController::class)->except('show')->parameters(['categories' => 'category']);
        Route::resource('items', MenuItemController::class)->except('show')->parameters(['items' => 'item']);
        Route::patch('items/{item}/availability', [MenuItemController::class, 'toggleAvailability'])->name('items.availability');
    });

    Route::middleware('can:manage-content')->group(function () {
        Route::get('subscribers', [SubscriberController::class, 'index'])->name('subscribers.index');
        Route::get('subscribers/export', [SubscriberController::class, 'export'])->name('subscribers.export');
        Route::delete('subscribers/{subscriber}', [SubscriberController::class, 'destroy'])->name('subscribers.destroy');

        Route::prefix('content/{resource}')->name('content.')->whereIn('resource', array_keys(AdminResources::all()))->group(function () {
            Route::get('/', [ContentController::class, 'index'])->name('index');
            Route::get('create', [ContentController::class, 'create'])->name('create');
            Route::post('/', [ContentController::class, 'store'])->name('store');
            Route::get('{id}/edit', [ContentController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::put('{id}', [ContentController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('{id}', [ContentController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });
    });

    Route::middleware('can:manage-settings')->group(function () {
        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
        Route::get('appearance', [AppearanceController::class, 'edit'])->name('appearance.edit');
        Route::put('appearance', [AppearanceController::class, 'update'])->name('appearance.update');
    });

    Route::middleware('can:manage-users')->group(function () {
        Route::resource('users', UserController::class)->except('show');
    });

    Route::get('activity', [ActivityLogController::class, 'index'])->middleware('can:view-logs')->name('activity.index');
});
