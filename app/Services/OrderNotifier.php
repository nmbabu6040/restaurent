<?php

namespace App\Services;

use App\Mail\NewOrderAlertMail;
use App\Models\Order;
use Illuminate\Support\Facades\Mail;

class OrderNotifier
{
    public function restaurantAlert(Order $order): void
    {
        $to = config('restaurant.ordering.alert_email') ?: setting('email');

        if (! filled($to)) {
            return;
        }

        Mail::to($to)->queue(new NewOrderAlertMail($order->loadMissing('items')));
    }
}
