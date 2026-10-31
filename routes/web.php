<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderController;
use App\Http\Middleware\EnsureOrderingEnabled;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\NotFoundController;
use App\Http\Controllers\CmsPageController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');

Route::get('/menu', [MenuController::class, 'index'])->name('menu.index');
Route::get('/menu/{item:slug}', [MenuController::class, 'show'])->name('menu.show');

Route::post('/menu/{item:slug}/reviews', [ReviewController::class, 'store'])->middleware('throttle:reviews')->name('reviews.store');
Route::post('/reviews/{review}/replies', [ReviewController::class, 'reply'])->whereNumber('review')->middleware('throttle:review-replies')->name('reviews.reply');

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

Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/gallery', [PageController::class, 'gallery'])->name('gallery');
Route::get('/offers', [PageController::class, 'offers'])->name('offers');
Route::get('/testimonials', [PageController::class, 'testimonials'])->name('testimonials');
Route::get('/faq', [PageController::class, 'faq'])->name('faq');
Route::get('/page/{slug}', [CmsPageController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('pages.show');
Route::get('/privacy-policy', [CmsPageController::class, 'privacy'])->name('privacy');
Route::get('/terms-and-conditions', [CmsPageController::class, 'terms'])->name('terms');

Route::get('/lang/{locale}', LocaleController::class)->name('lang.switch');

Route::middleware([EnsureOrderingEnabled::class, 'throttle:120,1'])->group(function () {
    Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
    Route::post('/cart/items', [CartController::class, 'add'])->name('cart.add');
    Route::patch('/cart/items/{line}', [CartController::class, 'update'])
        ->where('line', '[0-9]+_[0-9]+_[0-9.]*')->name('cart.update');
    Route::delete('/cart/items/{line}', [CartController::class, 'destroy'])
        ->where('line', '[0-9]+_[0-9]+_[0-9.]*')->name('cart.remove');
    Route::post('/cart/coupon', [CartController::class, 'applyCoupon'])->name('cart.coupon.apply');
    Route::delete('/cart/coupon', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');

    Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:orders')->name('checkout.store');
});

// Order tracking stays available even if ordering is switched off later.
Route::get('/orders/track', [OrderController::class, 'track'])->name('orders.track');
Route::post('/orders/track', [OrderController::class, 'lookup'])->middleware('throttle:order-track')->name('orders.lookup');
Route::get('/orders/{order:token}', [OrderController::class, 'show'])->middleware('throttle:60,1')->name('orders.show');
Route::get('/orders/{order:token}/invoice', [OrderController::class, 'invoice'])->middleware('throttle:30,1')->name('orders.invoice');
Route::get('/orders/{order:token}/status', [OrderController::class, 'status'])->middleware('throttle:60,1')->name('orders.status');

Route::post('/orders/{order:token}/pay', [PaymentController::class, 'start'])->middleware('throttle:payments')->name('payments.start');

Route::prefix('payments/sslcommerz')->middleware('throttle:120,1')->name('payments.')->group(function () {
    Route::post('success', [PaymentController::class, 'success'])->name('success');
    Route::post('fail', [PaymentController::class, 'fail'])->name('fail');
    Route::post('cancel', [PaymentController::class, 'cancel'])->name('cancel');
    Route::post('ipn', [PaymentController::class, 'ipn'])->name('ipn');
});

require __DIR__ . '/admin.php';
require __DIR__ . '/rider.php';

Route::fallback(NotFoundController::class);
