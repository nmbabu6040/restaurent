<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Exceptions\DeliveryException;
use App\Mail\OrderUpdateMail;
use App\Models\ActivityLog;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class DeliveryService
{
    /** Gives a delivery order to a rider (or moves it to another rider). The rider's current fee is saved on the delivery. */
    public function assign(Order $order, Rider $rider, ?User $by): Delivery
    {
        return DB::transaction(function () use ($order, $rider, $by) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->type !== OrderType::Delivery) {
                throw new DeliveryException('Only delivery orders can be given to a rider.');
            }

            if (! in_array($locked->status, [OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::OutForDelivery], true)) {
                throw new DeliveryException('Confirm the order before assigning a rider.');
            }

            $rider = Rider::whereKey($rider->id)->first();

            if (! $rider || ! $rider->is_active) {
                throw new DeliveryException('This rider is not active.');
            }

            $existing = Delivery::where('order_id', $locked->id)->lockForUpdate()->first();

            if ($existing?->status === DeliveryStatus::Delivered) {
                throw new DeliveryException('This order has already been delivered.');
            }

            $attributes = [
                'rider_id' => $rider->id,
                'status' => DeliveryStatus::Assigned,
                'rider_fee' => $rider->fee_per_delivery,
                'cash_collected' => 0,
                'assigned_by' => $by?->id,
                'assigned_at' => now(),
                'picked_up_at' => null,
                'delivered_at' => null,
                'failed_reason' => null,
            ];

            $delivery = $existing ?? new Delivery(['order_id' => $locked->id]);
            $delivery->fill($attributes)->save();

            ActivityLog::record('delivery.assigned', "Order {$locked->reference} given to rider {$rider->name}", $locked);

            return $delivery;
        });
    }

    /** The rider has the food. A delivery order still "preparing" moves on to "out for delivery". */
    public function pickUp(Delivery $delivery): Delivery
    {
        $order = null;
        $moved = false;

        $result = DB::transaction(function () use ($delivery, &$order, &$moved) {
            $d = Delivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($d->order_id)->lockForUpdate()->firstOrFail();

            if ($d->status !== DeliveryStatus::Assigned) {
                throw new DeliveryException('This delivery is not waiting for pickup.');
            }

            if (! in_array($order->status, [OrderStatus::Preparing, OrderStatus::OutForDelivery], true)) {
                throw new DeliveryException('The kitchen has not finished this order yet.');
            }

            $d->update(['status' => DeliveryStatus::PickedUp, 'picked_up_at' => now()]);

            if ($order->status === OrderStatus::Preparing) {
                $order->update(['status' => OrderStatus::OutForDelivery, 'status_changed_at' => now()]);
                $moved = true;
            }

            return $d;
        });

        ActivityLog::record('delivery.picked_up', "Order {$order->reference} picked up", $order);

        if ($moved) {
            $this->tellCustomer($order);
        }

        return $result;
    }

    /** Handed over to the customer. A cash order is now paid and the cash is on the rider. */
    public function deliver(Delivery $delivery): Delivery
    {
        $order = null;

        $result = DB::transaction(function () use ($delivery, &$order) {
            $d = Delivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($d->order_id)->lockForUpdate()->firstOrFail();

            if ($d->status !== DeliveryStatus::PickedUp) {
                throw new DeliveryException('Mark the order as picked up first.');
            }

            if ($order->status !== OrderStatus::OutForDelivery) {
                throw new DeliveryException('This order is not out for delivery.');
            }

            $cash = $order->payment_method === 'cod';

            $d->update([
                'status' => DeliveryStatus::Delivered,
                'delivered_at' => now(),
                'cash_collected' => $cash ? $order->total : 0,
            ]);

            $order->update(['status' => OrderStatus::Completed, 'status_changed_at' => now()] + ($cash ? ['payment_status' => 'paid'] : []));

            return $d;
        });

        ActivityLog::record('delivery.delivered', "Order {$order->reference} delivered", $order);
        $this->tellCustomer($order);

        return $result;
    }

    /** The rider could not deliver. The order stays as it is and the office decides: send another rider, or cancel. */
    public function fail(Delivery $delivery, string $reason): Delivery
    {
        $order = null;

        $result = DB::transaction(function () use ($delivery, $reason, &$order) {
            $d = Delivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($d->order_id)->first();

            if (! $d->status->isOpen()) {
                throw new DeliveryException('This delivery is already closed.');
            }

            $d->update(['status' => DeliveryStatus::Failed, 'failed_reason' => mb_substr($reason, 0, 255)]);

            return $d;
        });

        ActivityLog::record('delivery.failed', "Order {$order->reference}: delivery problem: {$reason}", $order);

        return $result;
    }

    /** The office completed the order itself: close the rider's delivery too. */
    public function orderCompleted(Order $order): void
    {
        $delivery = Delivery::where('order_id', $order->id)
            ->whereIn('status', ['assigned', 'picked_up', 'failed'])
            ->first();

        $delivery?->update([
            'status' => DeliveryStatus::Delivered,
            'picked_up_at' => $delivery->picked_up_at ?? now(),
            'delivered_at' => now(),
            'cash_collected' => $order->payment_method === 'cod' ? $order->total : 0,
        ]);
    }

    private function tellCustomer(Order $order): void
    {
        $order->refresh();

        if ($order->email) {
            Mail::to($order->email)->locale($order->locale)->queue(new OrderUpdateMail($order, 'status'));
        }
    }
}
