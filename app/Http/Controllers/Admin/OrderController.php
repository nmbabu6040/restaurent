<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Exceptions\DeliveryException;
use App\Models\Rider;
use App\Services\DeliveryService;
use App\Http\Controllers\Controller;
use App\Mail\OrderUpdateMail;
use App\Models\ActivityLog;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Models\Reservation;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->validate([
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'type' => ['nullable', Rule::enum(OrderType::class)],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'q' => ['nullable', 'string', 'max:100'],
            'needs_rider' => ['nullable', 'boolean'],
        ]);

        $orders = Order::query()
            ->when($f['status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->when($f['type'] ?? null, fn($q, $v) => $q->where('type', $v))
            ->when($f['date'] ?? null, fn($q, $v) => $q->whereDate('created_at', $v))
            ->when($request->boolean('needs_rider'), fn($q) => $q->needsRider())
            ->when($f['q'] ?? null, function ($q, $term) {
                $like = '%' . addcslashes($term, '%_\\') . '%';
                $q->where(fn($w) => $w->where('reference', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('phone', 'like', $like));
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        return view('admin.orders.show', [
            'order' => $order->load('items', 'payments', 'delivery.rider'),
            'riders' => Rider::active()->orderByDesc('is_online')->orderBy('name')->get(),
        ]);
    }

    public function assignRider(Request $request, Order $order, DeliveryService $deliveries)
    {
        $data = $request->validate(['rider_id' => ['required', 'integer', 'exists:riders,id']]);

        try {
            $delivery = $deliveries->assign($order, Rider::findOrFail($data['rider_id']), $request->user());
        } catch (DeliveryException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "Order given to {$delivery->rider->name}.");
    }

    public function updateStatus(Request $request, Order $order)
    {
        $data = $request->validate(['status' => ['required', Rule::enum(OrderStatus::class)]]);
        $new = OrderStatus::from($data['status']);

        $error = DB::transaction(function () use ($order, $new) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! in_array($new, $locked->status->transitions($locked->type), true)) {
                return 'That status change is not allowed.';
            }

            if ($new === OrderStatus::Confirmed && $locked->payment_method !== 'cod' && $locked->payment_status !== 'paid') {
                return 'This order is waiting for online payment. Confirm it after the payment is received.';
            }

            if ($new === OrderStatus::Cancelled) {
                $locked->markCancelled();

                return null;
            }

            $attrs = ['status' => $new, 'status_changed_at' => now()];

            // Cash orders are paid when handed over.
            if ($new === OrderStatus::Completed && $locked->payment_method === 'cod') {
                $attrs['payment_status'] = 'paid';
            }

            $locked->update($attrs);

            return null;
        });

        if ($error) {
            return back()->with('error', $error);
        }

        $order->refresh();

        if ($new === OrderStatus::Completed) {
            app(DeliveryService::class)->orderCompleted($order);
        }
        ActivityLog::record('order.' . $new->value, "Order {$order->reference} marked {$new->value}", $order);

        if ($order->email) {
            Mail::to($order->email)->locale($order->locale)->queue(new OrderUpdateMail($order, 'status'));
        }

        $message = "Order {$order->reference} marked " . $new->adminLabel() . '.';

        if ($new === OrderStatus::Cancelled && $order->payment_method !== 'cod' && $order->payment_status === 'paid') {
            $message .= ' This order was paid online: refund the customer from the SSLCommerz merchant panel.';
        }

        return back()->with('status', $message);
    }

    /** Cheap check the admin pages call every 20 seconds, to play the new-order alert. */
    public function poll()
    {
        $orders = Order::needsAttention();
        $reservations = Reservation::where('status', 'pending');

        return response()->json([
            'orders' => $orders->count(),
            'latest' => (int) $orders->max('id'),
            'reservations' => $reservations->count(),
            'reservation_latest' => (int) $reservations->max('id'),
        ])->header('Cache-Control', 'no-store');
    }

    public function print(Request $request, Order $order, string $type)
    {
        abort_unless(in_array($type, ['kot', 'receipt'], true), 404);

        ActivityLog::record('order.printed', "Printed {$type} for {$order->reference}", $order);

        return view('admin.orders.print', [
            'order' => $order->load('items', 'delivery.rider'),
            'type' => $type,
            'autoprint' => $request->boolean('autoprint'),
        ]);
    }
}
