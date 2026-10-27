<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/menu', [MenuController::class, 'index'])->name('menu.index');
Route::get('/menu/{item:slug}', [MenuController::class, 'show'])->name('menu.show');

Route::get('/reservations', [ReservationController::class, 'create'])->name('reservations.create');
Route::get('/reservations/availability', [ReservationController::class, 'availability'])
    ->middleware('throttle:60,1')->name('reservations.availability');
Route::post('/reservations', [ReservationController::class, 'store'])
    ->middleware('throttle:reservations')->name('reservations.store');
Route::get('/reservations/{reservation:reference}', [ReservationController::class, 'show'])
    ->middleware('throttle:30,1')->name('reservations.show');

Route::get('/contact', [ContactController::class, 'create'])->name('contact.create');
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:contact')->name('contact.store');

Route::post('/newsletter', [NewsletterController::class, 'store'])
    ->middleware('throttle:newsletter')->name('newsletter.store');
Route::get('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])
    ->middleware('throttle:30,1')->name('newsletter.unsubscribe');

Route::get('/lang/{locale}', LocaleController::class)->name('lang.switch');
