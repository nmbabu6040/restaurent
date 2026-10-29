<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function show(Order $order)
    {
        return view('orders.show', ['order' => $order->load('items', 'delivery.rider')]);
    }

    public function status(Order $order)
    {
        $order->loadMissing('delivery');

        return response()->json([
            'status' => $order->status->value,
            'label' => $order->status->label(),
            'final' => $order->status->isFinal(),
            // Changes when the order status or its rider changes, so the page can refresh itself.
            'signature' => $order->status->value . '|' . ($order->delivery?->status->value ?? ''),
        ]);
    }

    public function track()
    {
        return view('orders.track');
    }

    public function lookup(Request $request)
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:20'],
            'phone' => ['required', 'string', 'regex:/^[0-9+\-\s()]{7,20}$/'],
        ]);

        $tail = fn(string $phone) => substr(preg_replace('/\D/', '', $phone), -9);
        $order = Order::where('reference', Str::upper(trim($data['reference'])))->first();

        if (! $order || $tail($order->phone) !== $tail($data['phone'])) {
            return back()->withInput()->withErrors(['reference' => __('orders.track.not_found')]);
        }

        return redirect()->route('orders.show', $order->token);
    }
}
