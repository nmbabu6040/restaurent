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
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\SalesController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\IngredientController;
use App\Http\Controllers\Admin\ProfitLossController;
use App\Http\Controllers\Admin\RiderController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\QrController;
use App\Http\Controllers\Admin\CmsPageController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\HomeSectionController;
use App\Http\Controllers\Admin\SeoController;
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

        Route::get('orders/poll', [OrderController::class, 'poll'])->name('orders.poll');
        Route::get('orders/{order}/print/{type}', [OrderController::class, 'print'])
            ->whereNumber('order')->whereIn('type', ['kot', 'receipt'])->name('orders.print');
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
        Route::post('orders/{order}/rider', [OrderController::class, 'assignRider'])->name('orders.rider');

        Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index');
        Route::get('reviews/{review}', [ReviewController::class, 'show'])->whereNumber('review')->name('reviews.show');
        Route::patch('reviews/{review}/status', [ReviewController::class, 'status'])->whereNumber('review')->name('reviews.status');
        Route::post('reviews/{review}/replies', [ReviewController::class, 'reply'])->whereNumber('review')->name('reviews.reply');
        Route::patch('review-replies/{reply}/status', [ReviewController::class, 'replyStatus'])->whereNumber('reply')->name('review-replies.status');

        Route::get('orders/{order}/invoice', [OrderController::class, 'invoice'])->whereNumber('order')->name('orders.invoice');
        Route::get('orders/{order}/challan', [OrderController::class, 'challan'])->whereNumber('order')->name('orders.challan');
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

        Route::resource('pages', CmsPageController::class)->except('show');
        Route::resource('home-sections', HomeSectionController::class)->except('show')->parameters(['home-sections' => 'section']);
        Route::post('home-sections/{section}/toggle', [HomeSectionController::class, 'toggle'])->name('home-sections.toggle');
        Route::post('home-sections/{section}/move/{direction}', [HomeSectionController::class, 'move'])->whereIn('direction', ['up', 'down'])->name('home-sections.move');

        Route::get('qr', [QrController::class, 'index'])->name('qr.index');
        Route::get('qr/download', [QrController::class, 'download'])->name('qr.download');
        Route::get('qr/print', [QrController::class, 'print'])->name('qr.print');

        Route::delete('reviews/{review}', [ReviewController::class, 'destroy'])->whereNumber('review')->name('reviews.destroy');
        Route::delete('review-replies/{reply}', [ReviewController::class, 'destroyReply'])->whereNumber('reply')->name('review-replies.destroy');

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

        Route::get('seo', [SeoController::class, 'edit'])->name('seo.edit');
        Route::put('seo', [SeoController::class, 'update'])->name('seo.update');

        Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('backups', [BackupController::class, 'run'])->name('backups.run');
        Route::get('backups/{name}', [BackupController::class, 'download'])->where('name', '[A-Za-z0-9._-]+')->name('backups.download');
        Route::delete('backups/{name}', [BackupController::class, 'destroy'])->where('name', '[A-Za-z0-9._-]+')->name('backups.destroy');
    });

    Route::middleware('can:manage-users')->group(function () {
        Route::resource('users', UserController::class)->except('show');
    });

    Route::middleware('can:view-reports')->group(function () {
        Route::get('sales', [SalesController::class, 'index'])->name('sales.index');
        Route::get('sales/export', [SalesController::class, 'export'])->name('sales.export');
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/export', [CustomerController::class, 'export'])->name('customers.export');
        Route::resource('expenses', ExpenseController::class)->except('show');
        Route::get('profit-loss', [ProfitLossController::class, 'index'])->name('profit.index');
        Route::get('profit-loss/export', [ProfitLossController::class, 'export'])->name('profit.export');

        Route::get('downloads', [ExportController::class, 'index'])->name('downloads.index');
        Route::get('downloads/{dataset}', [ExportController::class, 'download'])->where('dataset', '[a-z_]+')->name('downloads.get');
    });

    Route::middleware('can:manage-inventory')->group(function () {
        Route::resource('ingredients', IngredientController::class)->parameters(['ingredients' => 'ingredient']);
        Route::post('ingredients/{ingredient}/receive', [IngredientController::class, 'receive'])->name('ingredients.receive');
        Route::post('ingredients/{ingredient}/waste', [IngredientController::class, 'waste'])->name('ingredients.waste');
        Route::post('ingredients/{ingredient}/count', [IngredientController::class, 'count'])->name('ingredients.count');
    });

    Route::middleware('can:manage-riders')->group(function () {
        Route::resource('riders', RiderController::class)->parameters(['riders' => 'rider']);
        Route::post('riders/{rider}/settle', [RiderController::class, 'settle'])->name('riders.settle');
    });

    Route::get('activity', [ActivityLogController::class, 'index'])->middleware('can:view-logs')->name('activity.index');
});
