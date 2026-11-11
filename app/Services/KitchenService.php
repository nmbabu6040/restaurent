<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Exceptions\PosException;
use App\Mail\OrderUpdateMail;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class KitchenService
{
    /** Portions the kitchen has been asked to cook: restaurant bills count what was sent, online orders everything. */
    public static function wanted(Order $order, OrderItem $line): int
    {
        return $order->source === 'pos' ? $line->sent_qty : $line->quantity;
    }

    /**
     * Orders with something still to cook, oldest first.
     *
     * @return Collection<int, array{order: Order, lines: Collection, since: \Carbon\Carbon, minutes: int}>
     */
    public function tickets(): Collection
    {
        $orders = Order::query()
            ->where('created_at', '>=', now()->subHours(12))
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->where(fn($p) => $p->where('source', 'pos')
                    ->whereHas('items', fn($i) => $i->whereColumn('sent_qty', '>', 'ready_qty')))
                    ->orWhere(fn($o) => $o->where('source', '!=', 'pos')
                        ->whereIn('status', ['confirmed', 'preparing'])
                        ->whereHas('items', fn($i) => $i->whereColumn('quantity', '>', 'ready_qty')));
            })
            ->with('items')
            ->get();

        return $orders->map(function (Order $order) {
            $lines = $order->items
                ->map(fn($line) => ['line' => $line, 'qty' => self::wanted($order, $line) - $line->ready_qty])
                ->filter(fn($row) => $row['qty'] > 0)
                ->values();

            $since = $lines->map(fn($row) => $row['line']->sent_at ?? $order->created_at)
                ->sortBy(fn($date) => $date->getTimestamp())->first();

            return [
                'order' => $order,
                'lines' => $lines,
                'since' => $since,
                'minutes' => $since ? (int) floor($since->diffInMinutes(now(), true)) : 0,
            ];
        })->filter(fn($ticket) => $ticket['lines']->isNotEmpty())
            ->sortBy(fn($ticket) => $ticket['since']->getTimestamp())
            ->values();
    }

    /** Marks one line, or everything waiting on the order, as cooked. */
    public function markReady(Order $order, ?OrderItem $only = null): void
    {
        $moved = false;

        DB::transaction(function () use ($order, $only, &$moved) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === OrderStatus::Cancelled) {
                throw new PosException('This order was cancelled.');
            }

            $locked->load('items');
            $lines = $only ? $locked->items->where('id', $only->id) : $locked->items;

            if ($only && $lines->isEmpty()) {
                throw new PosException('That item is not on this order.');
            }

            $marked = 0;

            foreach ($lines as $line) {
                $target = self::wanted($locked, $line);

                if ($line->ready_qty < $target) {
                    $line->update(['ready_qty' => $target, 'ready_at' => now()]);
                    $marked++;
                }
            }

            if ($marked === 0) {
                throw new PosException('Nothing is waiting on this order.');
            }

            $locked->load('items');

            // A takeaway order from the website is ready for pickup once everything is cooked.
            if (
                $locked->source !== 'pos' && $locked->type === OrderType::Pickup && $locked->status === OrderStatus::Preparing
                && $locked->items->every(fn($line) => $line->ready_qty >= $line->quantity)
            ) {
                $locked->update(['status' => OrderStatus::Ready, 'status_changed_at' => now()]);
                $moved = true;
            }
        });

        ActivityLog::record('kitchen.ready', "Order {$order->reference} marked ready", $order);

        if ($moved) {
            $fresh = $order->fresh();

            if ($fresh->email) {
                Mail::to($fresh->email)->locale($fresh->locale)->queue(new OrderUpdateMail($fresh, 'status'));
            }
        }
    }
}
