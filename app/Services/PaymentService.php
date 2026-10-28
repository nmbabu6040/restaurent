<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentService
{
    public function __construct(private OrderNotifier $notifier) {}

    public function enabled(): bool
    {
        return (bool) config('restaurant.ordering.online_payment')
            && filled(config('services.sslcommerz.store_id'))
            && filled(config('services.sslcommerz.store_password'));
    }

    private function base(): string
    {
        return config('services.sslcommerz.sandbox')
            ? 'https://sandbox.sslcommerz.com'
            : 'https://securepay.sslcommerz.com';
    }

    /** Create a payment attempt and return the hosted gateway page URL. */
    public function start(Order $order): string
    {
        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'sslcommerz',
            'tran_id' => $order->reference . '-' . Str::upper(Str::random(4)),
            'amount' => $order->total,
            'currency' => 'BDT',
            'status' => 'initiated',
        ]);

        try {
            $response = Http::asForm()->timeout(20)->post($this->base() . '/gwprocess/v4/api.php', [
                'store_id' => config('services.sslcommerz.store_id'),
                'store_passwd' => config('services.sslcommerz.store_password'),
                'total_amount' => number_format((float) $payment->amount, 2, '.', ''),
                'currency' => 'BDT',
                'tran_id' => $payment->tran_id,
                'success_url' => route('payments.success'),
                'fail_url' => route('payments.fail'),
                'cancel_url' => route('payments.cancel'),
                'ipn_url' => route('payments.ipn'),
                'cus_name' => $order->name,
                'cus_email' => $order->email ?: (setting('email') ?: 'noreply@example.com'),
                'cus_add1' => Str::limit($order->address ?: 'Pickup', 50, ''),
                'cus_city' => config('restaurant.ordering.city'),
                'cus_country' => 'Bangladesh',
                'cus_phone' => $order->phone,
                'shipping_method' => 'NO',
                'product_name' => 'Food order ' . $order->reference,
                'product_category' => 'Food',
                'product_profile' => 'general',
            ]);
        } catch (\Throwable $e) {
            $payment->update(['status' => 'failed']);
            Log::error('SSLCommerz init request failed (' . get_class($e) . ')');

            throw new RuntimeException('Gateway unreachable');
        }

        $data = $response->json() ?? [];
        $url = (string) ($data['GatewayPageURL'] ?? '');
        $host = (string) parse_url($url, PHP_URL_HOST);

        if (($data['status'] ?? '') !== 'SUCCESS' || ! str_starts_with($url, 'https://') || ! str_ends_with($host, 'sslcommerz.com')) {
            $payment->update(['status' => 'failed']);
            Log::warning('SSLCommerz init rejected: ' . ($data['failedreason'] ?? 'unknown reason'));

            throw new RuntimeException('Gateway rejected the request');
        }

        return $url;
    }

    /**
     * Confirm a payment with SSLCommerz's Order Validation API. Nothing from the browser or IPN body is trusted
     * except val_id, which is checked against the gateway. Safe to call repeatedly (success page and IPN).
     */
    public function settle(array $input): ?Payment
    {
        $valId = $input['val_id'] ?? null;

        if (! is_string($valId) || $valId === '' || strlen($valId) > 100) {
            return null;
        }

        try {
            $res = Http::timeout(20)->get($this->base() . '/validator/api/validationserverAPI.php', [
                'val_id' => $valId,
                'store_id' => config('services.sslcommerz.store_id'),
                'store_passwd' => config('services.sslcommerz.store_password'),
                'format' => 'json',
            ]);
        } catch (\Throwable $e) {
            // The message is not logged on purpose: this request carries the store password in its query string.
            Log::warning('SSLCommerz validation request failed (' . get_class($e) . ')');

            return null;
        }

        if (! $res->successful()) {
            Log::warning('SSLCommerz validation returned HTTP ' . $res->status());

            return null;
        }

        $v = $res->json();

        if (! is_array($v) || ! in_array($v['status'] ?? '', ['VALID', 'VALIDATED'], true)) {
            return null;
        }

        $newlyPaid = false;

        $payment = DB::transaction(function () use ($v, $valId, &$newlyPaid) {
            $payment = Payment::where('tran_id', (string) ($v['tran_id'] ?? ''))->lockForUpdate()->first();

            if (! $payment) {
                return null;
            }

            if ($payment->status === 'paid') {
                return $payment;
            }

            $amountOk = ($v['currency_type'] ?? null) === 'BDT'
                && CartService::cents($v['amount'] ?? 0) === CartService::cents($payment->amount);

            if (! $amountOk) {
                Log::warning("SSLCommerz amount or currency mismatch for {$payment->tran_id}");

                return null;
            }

            $payment->update([
                'status' => 'paid',
                'val_id' => $valId,
                'bank_tran_id' => $v['bank_tran_id'] ?? null,
                'card_type' => isset($v['card_type']) ? Str::limit((string) $v['card_type'], 60, '') : null,
                'paid_at' => now(),
            ]);

            $order = Order::whereKey($payment->order_id)->lockForUpdate()->first();
            $order->update(['payment_status' => 'paid']);

            if ($order->status === OrderStatus::Cancelled) {
                Log::warning("Payment received for cancelled order {$order->reference}");
                ActivityLog::record('payment.after_cancel', "Payment received for cancelled order {$order->reference}. Refund needed.", $order);
            } else {
                $newlyPaid = true;
            }

            return $payment;
        });

        if ($payment && $newlyPaid) {
            $this->notifier->restaurantAlert($payment->order);
        }

        return $payment;
    }

    public function markUnsuccessful(mixed $tranId, string $status): void
    {
        if (is_string($tranId) && $tranId !== '') {
            Payment::where('tran_id', $tranId)->where('status', 'initiated')->update(['status' => $status]);
        }
    }
}
