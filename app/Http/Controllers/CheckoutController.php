<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Mail\OrderUpdateMail;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\OrderNotifier;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Mail;

class CheckoutController extends Controller
{
    public function __construct(
        private CartService $cart,
        private CheckoutService $checkout,
        private OrderNotifier $notifier,
        private PaymentService $payments,
    ) {}

    public function create()
    {
        $types = $this->checkout->availableTypes();
        abort_if(empty($types), 404);

        $summary = $this->cart->summary();

        if ($summary['lines']->isEmpty() || $summary['subtotal'] < $summary['min_order']) {
            return redirect()->route('cart.show');
        }

        $totals = [];
        foreach ($types as $type) {
            $t = $this->cart->totals($summary['subtotal'], $type, $summary['discount']);
            $totals[$type->value] = [
                'fee' => $t['delivery_fee'] > 0 ? money_c($t['delivery_fee']) : __('orders.free'),
                'total' => money_c($t['total']),
            ];
        }

        return view('checkout.create', [
            'summary' => $summary,
            'types' => $types,
            'totals' => $totals,
            'onlinePayment' => $this->payments->enabled(),
        ]);
    }

    public function store(CheckoutRequest $request)
    {
        $order = $this->checkout->place($request->validated(), app()->getLocale());

        if ($order->email) {
            Mail::to($order->email)->locale($order->locale)->queue(new OrderUpdateMail($order, 'placed'));
        }

        // Online orders alert the restaurant only once they are paid (see PaymentService::settle).
        if ($order->payment_method === 'cod') {
            $this->notifier->restaurantAlert($order);
        }

        $url = route('orders.show', $order->token);

        return $request->wantsJson()
            ? response()->json(['redirect' => $url], 201)
            : redirect($url);
    }
}
