<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function start(Order $order, PaymentService $payments)
    {
        abort_unless($payments->enabled() && $order->payment_method === 'sslcommerz', 404);

        if ($order->payment_status === 'paid' || $order->status === OrderStatus::Cancelled) {
            return redirect()->route('orders.show', $order->token);
        }

        try {
            return redirect()->away($payments->start($order));
        } catch (\Throwable) {
            return redirect()->route('orders.show', $order->token)
                ->with('error', __('orders.payment.failed_start'));
        }
    }

    public function success(Request $request, PaymentService $payments)
    {
        $payment = $payments->settle($request->all());
        $ok = $payment && $payment->status === 'paid';

        return $this->back($request->input('tran_id'), $ok ? 'paid_ok' : 'not_confirmed');
    }

    public function fail(Request $request, PaymentService $payments)
    {
        $payments->markUnsuccessful($request->input('tran_id'), 'failed');

        return $this->back($request->input('tran_id'), 'failed');
    }

    public function cancel(Request $request, PaymentService $payments)
    {
        $payments->markUnsuccessful($request->input('tran_id'), 'cancelled');

        return $this->back($request->input('tran_id'), 'cancelled');
    }

    public function ipn(Request $request, PaymentService $payments)
    {
        $payments->settle($request->all());

        return response('OK', 200);
    }

    private function back(mixed $tranId, string $outcome)
    {
        $payment = is_string($tranId) && $tranId !== ''
            ? Payment::with('order')->where('tran_id', $tranId)->first()
            : null;

        if (! $payment) {
            return redirect()->route('home');
        }

        app()->setLocale($payment->order->locale);

        return redirect()->route('orders.show', $payment->order->token)
            ->with($outcome === 'paid_ok' ? 'status' : 'error', __('orders.payment.' . $outcome));
    }
}
