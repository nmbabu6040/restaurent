<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use App\Models\Expense;
use App\Models\StockMovement;
use App\Models\Delivery;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public const PRESETS = [
        'today' => 'Today',
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
        '90d' => 'Last 90 days',
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'custom' => 'Custom dates',
    ];

    /**
     * Revenue minus food cost, wastage and expenses.
     * Food cost uses the cost saved on each order line when it was placed.
     * With $full = false only the summary is built (cheaper, used on the dashboard).
     */
    /**
     * Revenue minus food cost, wastage, rider fees and expenses.
     * Food cost uses the cost saved on each order line when it was placed.
     * Rider fees count on the day the delivery was made.
     * With $full = false only the summary is built (cheaper, used on the dashboard).
     */
    public function profitLoss(Carbon $from, Carbon $to, string $group, bool $full = true): array
    {
        $sales = $this->summary($from, $to);
        $lines = fn() => $this->salesWhere(DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id'))
            ->whereBetween('orders.created_at', [$from, $to]);

        $cost = $lines()
            ->selectRaw('COALESCE(SUM(order_items.unit_cost * order_items.quantity), 0) as cost,
                COALESCE(SUM(CASE WHEN order_items.unit_cost <= 0 THEN 1 ELSE 0 END), 0) as uncosted')
            ->first();

        $wasteRows = StockMovement::query()->where('type', 'waste')->whereBetween('created_at', [$from, $to])
            ->get(['quantity', 'unit_cost', 'created_at']);
        $wastage = $wasteRows->sum(fn($m) => abs((float) $m->quantity) * (float) $m->unit_cost);

        $courierRows = Delivery::query()->where('status', 'delivered')->whereBetween('delivered_at', [$from, $to])
            ->get(['delivered_at', 'rider_fee']);
        $courier = (float) $courierRows->sum('rider_fee');

        $expenseRows = Expense::query()->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->get(['expense_date', 'category', 'amount']);
        $expenses = (float) $expenseRows->sum('amount');

        $revenue = $sales['revenue'];
        $cogs = round((float) $cost->cost, 2);
        $gross = round($revenue - $cogs, 2);
        $net = round($gross - $wastage - $courier - $expenses, 2);

        $result = ['summary' => [
            'revenue' => round($revenue, 2),
            'cogs' => $cogs,
            'gross' => $gross,
            'gross_margin' => $revenue > 0 ? round($gross / $revenue * 100, 1) : 0.0,
            'wastage' => round($wastage, 2),
            'courier' => round($courier, 2),
            'expenses' => round($expenses, 2),
            'net' => $net,
            'net_margin' => $revenue > 0 ? round($net / $revenue * 100, 1) : 0.0,
            'uncosted' => (int) $cost->uncosted,
        ]];

        if (! $full) {
            return $result;
        }

        $revenueSeries = $this->series($from, $to, $group);
        $buckets = [];
        foreach (array_keys($this->periods($from, $to, $group)) as $i => $key) {
            $buckets[$key] = ['label' => $revenueSeries[$i]['label'], 'revenue' => $revenueSeries[$i]['revenue'], 'costs' => 0.0];
        }

        $lineRows = $lines()->get(['orders.created_at as at', 'order_items.unit_cost as cost', 'order_items.quantity as qty']);
        foreach ($lineRows as $row) {
            $key = $this->key(Carbon::parse($row->at), $group);
            if (isset($buckets[$key])) {
                $buckets[$key]['costs'] += (float) $row->cost * (int) $row->qty;
            }
        }
        foreach ($wasteRows as $m) {
            $key = $this->key($m->created_at, $group);
            if (isset($buckets[$key])) {
                $buckets[$key]['costs'] += abs((float) $m->quantity) * (float) $m->unit_cost;
            }
        }
        foreach ($courierRows as $d) {
            $key = $this->key($d->delivered_at, $group);
            if (isset($buckets[$key])) {
                $buckets[$key]['costs'] += (float) $d->rider_fee;
            }
        }
        foreach ($expenseRows as $e) {
            $key = $this->key($e->expense_date, $group);
            if (isset($buckets[$key])) {
                $buckets[$key]['costs'] += (float) $e->amount;
            }
        }

        $result['series'] = array_values(array_map(fn($b) => [
            'label' => $b['label'],
            'revenue' => round($b['revenue'], 2),
            'costs' => round($b['costs'], 2),
            'profit' => round($b['revenue'] - $b['costs'], 2),
        ], $buckets));

        $result['expense_categories'] = $expenseRows->groupBy('category')
            ->map(fn($g) => round((float) $g->sum('amount'), 2))->sortDesc()->all();

        // Dish margins are before order-level discounts and delivery fees.
        $result['dishes'] = $lines()
            ->selectRaw('order_items.name_en as name, SUM(order_items.quantity) as qty, SUM(order_items.line_total) as revenue,
                SUM(order_items.unit_cost * order_items.quantity) as cost')
            ->groupBy('order_items.name_en')
            ->orderByDesc('revenue')
            ->limit(30)
            ->get()
            ->map(function ($r) {
                $revenue = (float) $r->revenue;
                $cost = (float) $r->cost;

                return [
                    'name' => $r->name,
                    'qty' => (int) $r->qty,
                    'revenue' => $revenue,
                    'cost' => $cost,
                    'profit' => $revenue - $cost,
                    'margin' => $revenue > 0 ? round(($revenue - $cost) / $revenue * 100, 1) : null,
                    'costed' => $cost > 0,
                ];
            })->all();

        return $result;
    }

    /** @return array{0: Carbon, 1: Carbon} start of the first day, end of the last day */
    public function resolveRange(string $preset, ?string $from = null, ?string $to = null): array
    {
        $today = now()->startOfDay();
        $end = now()->endOfDay();

        return match ($preset) {
            'today' => [$today, $end],
            '7d' => [$today->copy()->subDays(6), $end],
            '90d' => [$today->copy()->subDays(89), $end],
            'this_month' => [now()->startOfMonth(), $end],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'custom' => [
                Carbon::createFromFormat('Y-m-d', (string) $from)->startOfDay(),
                Carbon::createFromFormat('Y-m-d', (string) $to)->endOfDay(),
            ],
            default => [$today->copy()->subDays(29), $end],
        };
    }

    /**
     * "Sales" = orders that are not cancelled and are either cash or already paid online.
     * Works on both Eloquent and query builders (columns are table-qualified).
     */
    public function salesWhere($query)
    {
        return $query
            ->where('orders.status', '!=', 'cancelled')
            ->where(fn($w) => $w->where('orders.payment_method', 'cod')->orWhere('orders.payment_status', 'paid'));
    }

    public static function change(float|int $now, float|int $previous): ?float
    {
        return $previous > 0 ? round((($now - $previous) / $previous) * 100, 1) : null;
    }

    public function summary(Carbon $from, Carbon $to): array
    {
        $row = $this->salesWhere(Order::query())
            ->whereBetween('orders.created_at', [$from, $to])
            ->toBase()
            ->selectRaw('COUNT(*) as orders,
                COALESCE(SUM(total), 0) as revenue,
                COALESCE(SUM(subtotal), 0) as subtotal,
                COALESCE(SUM(delivery_fee), 0) as delivery,
                COALESCE(SUM(discount), 0) as discount,
                COALESCE(SUM(CASE WHEN coupon_code IS NOT NULL THEN 1 ELSE 0 END), 0) as coupons')
            ->first();

        $items = (int) $this->salesWhere(DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id'))
            ->whereBetween('orders.created_at', [$from, $to])
            ->sum('order_items.quantity');

        $orders = (int) $row->orders;
        $revenue = (float) $row->revenue;

        return [
            'orders' => $orders,
            'revenue' => $revenue,
            'subtotal' => (float) $row->subtotal,
            'delivery' => (float) $row->delivery,
            'discount' => (float) $row->discount,
            'coupons' => (int) $row->coupons,
            'items' => $items,
            'aov' => $orders > 0 ? $revenue / $orders : 0.0,
        ];
    }

    /** @return array<int, array{label:string, revenue:float, orders:int}> one entry per day, week or month, gaps included */
    public function series(Carbon $from, Carbon $to, string $group): array
    {
        $buckets = [];
        foreach ($this->periods($from, $to, $group) as $key => $label) {
            $buckets[$key] = ['label' => $label, 'revenue' => 0.0, 'orders' => 0];
        }

        $rows = $this->salesWhere(Order::query())
            ->whereBetween('orders.created_at', [$from, $to])
            ->get(['created_at', 'total']);

        foreach ($rows as $row) {
            $key = $this->key($row->created_at, $group);

            if (isset($buckets[$key])) {
                $buckets[$key]['revenue'] += (float) $row->total;
                $buckets[$key]['orders']++;
            }
        }

        return array_values($buckets);
    }

    /** @return array{type: array, payment: array} each key => ['orders' => n, 'revenue' => x] */
    public function breakdown(Carbon $from, Carbon $to): array
    {
        $rows = $this->salesWhere(Order::query())
            ->whereBetween('orders.created_at', [$from, $to])
            ->toBase()
            ->get(['type', 'payment_method', 'total']);

        $by = fn(string $column) => $rows->groupBy($column)
            ->map(fn($group) => ['orders' => $group->count(), 'revenue' => (float) $group->sum('total')])
            ->all();

        return ['type' => $by('type'), 'payment' => $by('payment_method')];
    }

    /** All orders (cancelled ones too), by status. */
    public function statusCounts(Carbon $from, Carbon $to): array
    {
        return Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->toBase()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->map(fn($c) => (int) $c)
            ->all();
    }

    /** Uses the names saved on each order line, so renamed dishes still add up correctly. */
    public function topItems(Carbon $from, Carbon $to, int $limit = 10): array
    {
        $query = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id');

        return $this->salesWhere($query)
            ->whereBetween('orders.created_at', [$from, $to])
            ->selectRaw('order_items.name_en as name, SUM(order_items.quantity) as qty, SUM(order_items.line_total) as revenue')
            ->groupBy('order_items.name_en')
            ->orderByDesc('qty')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn($r) => ['name' => $r->name, 'qty' => (int) $r->qty, 'revenue' => (float) $r->revenue])
            ->all();
    }

    /** @return array<int, int> orders per hour of the day, index 0 to 23 */
    public function hourly(Carbon $from, Carbon $to): array
    {
        $hours = array_fill(0, 24, 0);

        $rows = $this->salesWhere(Order::query())
            ->whereBetween('orders.created_at', [$from, $to])
            ->get(['created_at']);

        foreach ($rows as $row) {
            $hours[(int) $row->created_at->format('G')]++;
        }

        return $hours;
    }

    public static function customerKey(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        return strlen($digits) > 9 ? substr($digits, -9) : $digits;
    }

    /**
     * @return array{summary: array, series: array, top: array}
     */
    public function customerReport(Carbon $from, Carbon $to, string $group): array
    {
        [$customers, $active] = $this->scanCustomers($from, $to, $group);

        $buckets = [];
        foreach ($this->periods($from, $to, $group) as $key => $label) {
            $buckets[$key] = ['label' => $label, 'new' => 0, 'returning' => 0];
        }

        $new = 0;
        $activeCount = 0;
        $repeat = 0;
        $rangeOrders = 0;

        foreach ($customers as $customer) {
            $repeat += $customer['orders'] >= 2 ? 1 : 0;
            $new += $customer['first']->between($from, $to) ? 1 : 0;
            $activeCount += $customer['range_orders'] > 0 ? 1 : 0;
            $rangeOrders += $customer['range_orders'];
        }

        foreach ($active as $bucket => $set) {
            foreach (array_keys($set) as $key) {
                $isNew = $this->key($customers[$key]['first'], $group) === (string) $bucket;

                if (isset($buckets[$bucket])) {
                    $buckets[$bucket][$isNew ? 'new' : 'returning']++;
                }
            }
        }

        $total = count($customers);

        return [
            'summary' => [
                'total' => $total,
                'active' => $activeCount,
                'new' => $new,
                'returning' => $activeCount - $new,
                'repeat_rate' => $total > 0 ? round($repeat / $total * 100, 1) : 0.0,
                'orders_per_customer' => $activeCount > 0 ? round($rangeOrders / $activeCount, 2) : 0.0,
            ],
            'series' => array_values($buckets),
            'top' => collect($customers)
                ->filter(fn($c) => $c['range_orders'] > 0)
                ->sortByDesc('range_spent')
                ->take(10)
                ->values()
                ->all(),
        ];
    }

    /** Every customer who ever placed a sales order (for the CSV export). */
    public function allCustomers(): array
    {
        return array_values($this->scanCustomers(now(), now(), 'daily')[0]);
    }

    /** One pass over all sales orders, oldest first. */
    private function scanCustomers(Carbon $from, Carbon $to, string $group): array
    {
        $customers = [];
        $active = [];

        $orders = $this->salesWhere(Order::query())
            ->orderBy('orders.id')
            ->select(['id', 'name', 'phone', 'total', 'created_at'])
            ->cursor();

        foreach ($orders as $order) {
            $key = self::customerKey($order->phone);

            $customer = $customers[$key] ?? [
                'name' => $order->name,
                'phone' => $order->phone,
                'orders' => 0,
                'spent' => 0.0,
                'first' => $order->created_at,
                'last' => $order->created_at,
                'range_orders' => 0,
                'range_spent' => 0.0,
            ];

            $customer['orders']++;
            $customer['spent'] += (float) $order->total;
            $customer['last'] = $order->created_at;
            $customer['name'] = $order->name;
            $customer['phone'] = $order->phone;

            if ($order->created_at->between($from, $to)) {
                $customer['range_orders']++;
                $customer['range_spent'] += (float) $order->total;
                $active[$this->key($order->created_at, $group)][$key] = true;
            }

            $customers[$key] = $customer;
        }

        return [$customers, $active];
    }

    /** @return array<string, string> bucket key => label, for every period touching the range */
    private function periods(Carbon $from, Carbon $to, string $group): array
    {
        $periods = [];
        $cursor = $this->bucketStart($from, $group);

        while ($cursor->lte($to)) {
            $periods[$this->key($cursor, $group)] = $this->label($cursor, $group);

            $cursor = match ($group) {
                'weekly' => $cursor->copy()->addWeek(),
                'monthly' => $cursor->copy()->addMonthNoOverflow(),
                default => $cursor->copy()->addDay(),
            };
        }

        return $periods;
    }

    private function bucketStart(Carbon $date, string $group): Carbon
    {
        return match ($group) {
            'weekly' => $date->copy()->startOfWeek(),
            'monthly' => $date->copy()->startOfMonth(),
            default => $date->copy()->startOfDay(),
        };
    }

    private function key(Carbon $date, string $group): string
    {
        return match ($group) {
            'weekly' => $date->copy()->startOfWeek()->format('Y-m-d'),
            'monthly' => $date->format('Y-m'),
            default => $date->format('Y-m-d'),
        };
    }

    private function label(Carbon $date, string $group): string
    {
        return $group === 'monthly' ? $date->format('M Y') : $date->format('d M');
    }
}
