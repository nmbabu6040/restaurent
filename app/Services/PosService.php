<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Exceptions\PosException;
use App\Models\ActivityLog;
use App\Models\CashSession;
use App\Models\DiningTable;
use App\Models\FoodAddon;
use App\Models\FoodVariation;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Everything that happens on a restaurant bill. Prices always come from the database, never from the screen. */
class PosService
{
    public function __construct(private InventoryService $inventory) {}

    public function open(OrderType $type, array $data, User $by): Order
    {
        if (! in_array($type, [OrderType::DineIn, OrderType::Pickup], true)) {
            throw new PosException('Choose dine-in or takeaway.');
        }

        return DB::transaction(function () use ($type, $data, $by) {
            $table = null;

            if ($type === OrderType::DineIn) {
                $table = DiningTable::where('is_active', true)->whereKey($data['dining_table_id'] ?? 0)->lockForUpdate()->first();

                if (! $table) {
                    throw new PosException('Choose a table.');
                }

                if (Order::posOpen()->where('dining_table_id', $table->id)->exists()) {
                    throw new PosException("{$table->name} already has an open bill.");
                }
            }

            do {
                $reference = 'MO-' . Str::upper(Str::random(8));
            } while (Order::where('reference', $reference)->exists());

            return Order::create([
                'reference' => $reference,
                'token' => Str::random(40),
                'source' => 'pos',
                'type' => $type,
                'name' => filled($data['name'] ?? null) ? $data['name'] : ($table?->name ?? 'Walk-in'),
                'phone' => $data['phone'] ?? '',
                'dining_table_id' => $table?->id,
                'table_name' => $table?->name,
                'guests' => $data['guests'] ?? null,
                'user_id' => $by->id,
                'subtotal' => 0,
                'delivery_fee' => 0,
                'discount' => 0,
                'total' => 0,
                'payment_method' => 'cash',
                'payment_status' => 'unpaid',
                'status' => OrderStatus::Pending,
                'status_changed_at' => now(),
                'locale' => 'en',
            ]);
        });
    }

    public function addLine(Order $order, MenuItem $item, ?int $variationId, array $addonIds, int $qty): OrderItem
    {
        if ($qty < 1 || $qty > 50) {
            throw new PosException('The quantity must be between 1 and 50.');
        }

        return DB::transaction(function () use ($order, $item, $variationId, $addonIds, $qty) {
            $order = $this->lock($order);
            $item = MenuItem::with(['activeVariations', 'activeAddons', 'category'])->whereKey($item->id)->first();

            if (! $item || ! $item->is_available || ! $item->category?->is_active) {
                throw new PosException(($item?->name_en ?? 'This dish') . ' is not available.');
            }

            $variation = null;

            if ($item->activeVariations->isNotEmpty()) {
                $variation = $item->activeVariations->firstWhere('id', $variationId);

                if (! $variation) {
                    throw new PosException('Choose a size for ' . $item->name_en . '.');
                }
            }

            $ids = collect($addonIds)->map(fn($id) => (int) $id)->unique()->sort()->values();
            $addons = $item->activeAddons->whereIn('id', $ids)->values();

            if ($addons->count() !== $ids->count()) {
                throw new PosException('One of the selected extras is not available.');
            }

            $unit = CartService::cents($variation ? $variation->price : $item->price)
                + (int) $addons->sum(fn($a) => CartService::cents($a->price));
            $key = CartService::lineKey($item->id, $variation?->id, $ids->all());

            $line = $order->items()->where('line_key', $key)->first();

            if ($line) {
                $quantity = min(99, $line->quantity + $qty);
                $line->update(['quantity' => $quantity, 'line_total' => $this->dec(CartService::cents($line->unit_price) * $quantity)]);
            } else {
                $line = $order->items()->create([
                    'menu_item_id' => $item->id,
                    'name_en' => $item->name_en,
                    'name_bn' => $item->name_bn,
                    'variation_en' => $variation?->name_en,
                    'variation_bn' => $variation?->name_bn,
                    'addons' => $addons->isEmpty() ? null : $addons->map(fn($a) => [
                        'name_en' => $a->name_en,
                        'name_bn' => $a->name_bn,
                        'price' => (float) $a->price,
                    ])->values()->all(),
                    'unit_price' => $this->dec($unit),
                    'quantity' => $qty,
                    'line_total' => $this->dec($unit * $qty),
                    'sent_qty' => 0,
                    'line_key' => $key,
                ]);
            }

            $this->recalculate($order);

            return $line;
        });
    }

    public function setQuantity(Order $order, OrderItem $line, int $qty, User $by): void
    {
        DB::transaction(function () use ($order, $line, $qty, $by) {
            $order = $this->lock($order);
            $line = $order->items()->whereKey($line->id)->firstOrFail();

            if ($qty <= 0) {
                $this->deleteLine($order, $line, $by);

                return;
            }

            $qty = min(99, $qty);

            if ($qty < $line->sent_qty) {
                ActivityLog::record('pos.void_item', "{$order->reference}: {$line->name_en} reduced from {$line->sent_qty} to {$qty} after it was sent to the kitchen", $order);
            }

            $line->update([
                'quantity' => $qty,
                'sent_qty' => min($line->sent_qty, $qty),
                'line_total' => $this->dec(CartService::cents($line->unit_price) * $qty),
            ]);

            $this->recalculate($order);
        });
    }

    public function removeLine(Order $order, OrderItem $line, User $by): void
    {
        DB::transaction(function () use ($order, $line, $by) {
            $order = $this->lock($order);
            $this->deleteLine($order, $order->items()->whereKey($line->id)->firstOrFail(), $by);
        });
    }

    private function deleteLine(Order $order, OrderItem $line, User $by): void
    {
        if ($line->sent_qty > 0) {
            ActivityLog::record('pos.void_item', "{$order->reference}: {$line->name_en} x{$line->sent_qty} removed after it was sent to the kitchen", $order);
        }

        $line->delete();
        $this->recalculate($order);
    }

    public function setDiscount(Order $order, string $type, float $value, ?string $reason, User $by): void
    {
        DB::transaction(function () use ($order, $type, $value, $reason, $by) {
            $order = $this->lock($order);
            $order->load('items');
            $subtotal = $order->items->sum(fn($l) => (float) $l->line_total);

            if ($value <= 0) {
                $order->update(['discount_percent' => null, 'discount' => 0, 'discount_reason' => null]);
                $this->recalculate($order);

                return;
            }

            if ($type === 'percent' && $value > 100) {
                throw new PosException('A discount cannot be more than 100%.');
            }

            if ($type === 'fixed' && $value > $subtotal) {
                throw new PosException('The discount cannot be more than the bill.');
            }

            $effective = $type === 'percent' ? $value : ($subtotal > 0 ? $value / $subtotal * 100 : 0);
            $limit = (float) config('restaurant.pos.staff_max_discount');

            if (! $by->can('manage-menu') && $effective > $limit + 0.001) {
                throw new PosException("You can give up to {$limit}% discount. Ask a manager for more.");
            }

            $order->update([
                'discount_percent' => $type === 'percent' ? $value : null,
                'discount' => $type === 'fixed' ? $value : 0,
                'discount_reason' => $reason,
            ]);
            $this->recalculate($order);

            ActivityLog::record('pos.discount', "{$order->reference}: " . ($type === 'percent' ? "{$value}%" : money($value)) . ' discount' . ($reason ? " ({$reason})" : ''), $order);
        });
    }

    public function updateInfo(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data) {
            $order = $this->lock($order);
            $attributes = [];

            foreach (['notes', 'guests'] as $field) {
                if (array_key_exists($field, $data)) {
                    $attributes[$field] = $data[$field];
                }
            }

            if (filled($data['name'] ?? null)) {
                $attributes['name'] = $data['name'];
            }

            if (array_key_exists('phone', $data)) {
                $attributes['phone'] = $data['phone'] ?? '';
            }

            if (filled($data['dining_table_id'] ?? null) && (int) $data['dining_table_id'] !== (int) $order->dining_table_id) {
                if ($order->type !== OrderType::DineIn) {
                    throw new PosException('Only dine-in bills have a table.');
                }

                $table = DiningTable::where('is_active', true)->whereKey($data['dining_table_id'])->first();

                if (! $table) {
                    throw new PosException('That table is not available.');
                }

                if (Order::posOpen()->where('dining_table_id', $table->id)->whereKeyNot($order->id)->exists()) {
                    throw new PosException("{$table->name} already has an open bill.");
                }

                $attributes += ['dining_table_id' => $table->id, 'table_name' => $table->name];
            }

            $order->update($attributes);

            return $order;
        });
    }

    /**
     * Marks what is new for the kitchen. Returns each line with the quantity to cook now.
     *
     * @return Collection<int, array{line: OrderItem, qty: int}>
     */
    public function send(Order $order): Collection
    {
        return DB::transaction(function () use ($order) {
            $order = $this->lock($order);
            $order->load('items');

            $rows = $order->items
                ->filter(fn($l) => $l->quantity > $l->sent_qty)
                ->map(fn($l) => ['line' => $l, 'qty' => $l->quantity - $l->sent_qty])
                ->values();

            if ($rows->isEmpty()) {
                throw new PosException('Nothing new to send to the kitchen.');
            }

            foreach ($rows as $row) {
                $row['line']->update(['sent_qty' => $row['line']->quantity]);
            }

            if ($order->status === OrderStatus::Pending) {
                $order->update(['status' => OrderStatus::Preparing, 'status_changed_at' => now()]);
            }

            ActivityLog::record('pos.sent', "{$order->reference} sent to the kitchen", $order);

            return $rows;
        });
    }

    /**
     * Takes the payment and closes the bill. Stock and the food cost are taken now (a failed stock check
     * rolls everything back and the bill stays open).
     *
     * @param array<int, array{method: string, amount: mixed, tendered?: mixed, reference?: ?string}> $payments
     */
    public function pay(Order $order, array $payments, User $by): Order
    {
        return DB::transaction(function () use ($order, $payments, $by) {
            $order = $this->lock($order);
            $order->load('items');

            if ($order->items->isEmpty()) {
                throw new PosException('Add at least one item first.');
            }

            $this->recalculate($order);
            $due = CartService::cents($order->total);
            $sum = array_sum(array_map(fn($p) => CartService::cents($p['amount']), $payments));

            if ($sum !== $due) {
                throw new PosException('The payments add up to ' . money_c($sum) . ' but the bill is ' . money_c($due) . '.');
            }

            $plan = $this->inventory->apply($order, $this->stockLines($order));

            foreach ($order->items as $line) {
                $entry = $plan[(string) $line->id] ?? null;
                $line->update(array_filter([
                    'unit_cost' => $entry['unit_cost'] ?? null,
                    'consumed' => $entry['consumed'] ?? null,
                    'sent_qty' => $line->quantity,
                ], fn($v) => $v !== null));
            }

            $session = CashSession::open()->latest('id')->first();

            foreach ($payments as $p) {
                OrderPayment::create([
                    'order_id' => $order->id,
                    'method' => $p['method'],
                    'amount' => $this->dec(CartService::cents($p['amount'])),
                    'tendered' => $p['method'] === 'cash' ? ($p['tendered'] ?? $p['amount']) : null,
                    'reference' => $p['reference'] ?? null,
                    'user_id' => $by->id,
                    'cash_session_id' => $session?->id,
                ]);
            }

            $primary = collect($payments)->sortByDesc(fn($p) => CartService::cents($p['amount']))->first()['method'];

            $order->update([
                'payment_method' => $primary,
                'payment_status' => 'paid',
                'status' => OrderStatus::Completed,
                'status_changed_at' => now(),
            ]);

            ActivityLog::record('pos.paid', "{$order->reference} paid: " . money($order->total), $order);

            return $order;
        });
    }

    public function cancel(Order $order, User $by): void
    {
        DB::transaction(function () use ($order, $by) {
            $order = $this->lock($order);
            $order->load('items');

            if ($order->items->contains(fn($l) => $l->sent_qty > 0) && ! $by->can('manage-menu')) {
                throw new PosException('This bill was already sent to the kitchen. Ask a manager to cancel it.');
            }

            $order->markCancelled();
            ActivityLog::record('pos.cancelled', "{$order->reference} cancelled", $order);
        });
    }

    /** A paid bill was a mistake: money goes back out of the drawer and the stock comes back. Managers only (checked by the route). */
    public function void(Order $order, string $reason, User $by): void
    {
        DB::transaction(function () use ($order, $reason, $by) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->source !== 'pos' || $order->payment_status !== 'paid' || $order->status === OrderStatus::Cancelled) {
                throw new PosException('Only a paid restaurant bill can be voided.');
            }

            $session = CashSession::open()->latest('id')->first();

            foreach ($order->posPayments()->where('amount', '>', 0)->get() as $payment) {
                OrderPayment::create([
                    'order_id' => $order->id,
                    'method' => $payment->method,
                    'amount' => -1 * (float) $payment->amount,
                    'reference' => 'VOID',
                    'user_id' => $by->id,
                    'cash_session_id' => $session?->id,
                ]);
            }

            $order->update(['payment_status' => 'refunded']);
            $order->markCancelled();

            ActivityLog::record('pos.void', "Bill {$order->reference} voided: {$reason}", $order);
        });
    }

    public function recalculate(Order $order): void
    {
        $order->load('items');

        $subtotal = (int) $order->items->sum(fn($l) => CartService::cents($l->line_total));
        $discount = $order->discount_percent !== null
            ? (int) round($subtotal * (float) $order->discount_percent / 100)
            : CartService::cents($order->discount);
        $discount = min($discount, $subtotal);

        $rate = $order->type === OrderType::DineIn ? (float) config('restaurant.pos.service_charge') : 0.0;
        $service = (int) round(($subtotal - $discount) * $rate / 100);

        $order->update([
            'subtotal' => $this->dec($subtotal),
            'discount' => $this->dec($discount),
            'service_charge' => $this->dec($service),
            'total' => $this->dec($subtotal - $discount + $service),
        ]);
    }

    /** Only open restaurant bills can be changed. */
    private function lock(Order $order): Order
    {
        $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

        if (
            $locked->source !== 'pos' || $locked->payment_status === 'paid'
            || in_array($locked->status, [OrderStatus::Completed, OrderStatus::Cancelled], true)
        ) {
            throw new PosException('This bill is closed.');
        }

        return $locked;
    }

    /** Lines in the shape InventoryService expects, rebuilt from what the order line saved. */
    private function stockLines(Order $order): Collection
    {
        return $order->items->map(function (OrderItem $line) {
            $item = $line->menu_item_id ? MenuItem::withTrashed()->with('recipeLines.ingredient')->find($line->menu_item_id) : null;

            if (! $item) {
                return null;
            }

            [, $variationId, $addons] = array_pad(explode('_', (string) $line->line_key), 3, '');
            $addonIds = array_values(array_filter(array_map('intval', explode('.', $addons))));

            return [
                'key' => (string) $line->id,
                'item' => $item,
                'variation' => (int) $variationId > 0 ? FoodVariation::find((int) $variationId) : null,
                'addons' => FoodAddon::whereIn('id', $addonIds)->get(),
                'qty' => $line->quantity,
            ];
        })->filter()->values();
    }

    private function dec(int|float $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
