<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\MenuItem;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Reservation;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return view('admin.dashboard', [
            'stats' => [
                ['label' => 'Total reservations', 'value' => Reservation::count()],
                ['label' => 'Pending reservations', 'value' => Reservation::where('status', ReservationStatus::Pending->value)->count()],
                ['label' => "Today's reservations", 'value' => Reservation::blocking()->whereDate('reserved_at', now()->toDateString())->count()],

                ['label' => 'Pending orders', 'value' => Order::needsAttention()->count()],
                ['label' => "Today's orders", 'value' => Order::whereDate('created_at', now()->toDateString())->where('status', '!=', 'cancelled')->count()],
                ['label' => 'Menu items', 'value' => MenuItem::count()],
                ['label' => 'Unread messages', 'value' => ContactMessage::whereNull('read_at')->count()],
                ['label' => 'Newsletter subscribers', 'value' => NewsletterSubscriber::whereNull('unsubscribed_at')->count()],
            ],
            'upcoming' => Reservation::blocking()->where('reserved_at', '>=', now())->orderBy('reserved_at')->limit(5)->get(),
            'messages' => ContactMessage::latest()->limit(5)->get(),
            'recentOrders' => Order::latest('id')->limit(5)->get(),
        ]);
    }
}
