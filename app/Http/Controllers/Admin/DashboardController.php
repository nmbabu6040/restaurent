<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\MenuItem;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Reservation;
use App\Services\AnalyticsService;
use App\Support\Chart;
use Carbon\Carbon;
use App\Models\Ingredient;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    private const STATUS_COLORS = [
        'pending' => '#F59E0B',
        'confirmed' => '#6366F1',
        'preparing' => '#06B6D4',
        'ready' => '#84CC16',
        'out_for_delivery' => '#8B5CF6',
        'completed' => '#10B981',
        'cancelled' => '#EF4444',
    ];

    public function __invoke(AnalyticsService $analytics)
    {
        $canReport = auth()->user()->can('view-reports');

        $data = [
            'canReport' => $canReport,
            'stats' => [
                [
                    'label' => 'Pending orders',
                    'value' => Order::needsAttention()->count(),
                    'icon' => 'bag',
                    'tone' => 'rose',
                    'href' => route('admin.orders.index', ['status' => 'pending'])
                ],
                ['label' => 'Need a rider', 'value' => Order::needsRider()->count(), 'icon' => 'truck', 'tone' => 'indigo', 'href' => route('admin.orders.index', ['needs_rider' => 1])],
                ['label' => 'Pending reservations', 'value' => Reservation::where('status', ReservationStatus::Pending->value)->count(), 'icon' => 'clock', 'tone' => 'amber', 'href' => route('admin.reservations.index', ['status' => 'pending'])],
                ['label' => "Today's reservations", 'value' => Reservation::blocking()->whereDate('reserved_at', now()->toDateString())->count(), 'icon' => 'calendar', 'tone' => 'cyan', 'href' => route('admin.reservations.index', ['date' => now()->toDateString()])],
                ['label' => 'Unread messages', 'value' => ContactMessage::whereNull('read_at')->count(), 'icon' => 'mail', 'tone' => 'violet', 'href' => route('admin.messages.index')],
                ['label' => 'Menu items', 'value' => MenuItem::count(), 'icon' => 'utensils', 'tone' => 'teal', 'href' => null],
                ['label' => 'Newsletter subscribers', 'value' => NewsletterSubscriber::whereNull('unsubscribed_at')->count(), 'icon' => 'users', 'tone' => 'pink', 'href' => null],
            ],
            'upcoming' => Reservation::blocking()->where('reserved_at', '>=', now())->orderBy('reserved_at')->limit(5)->get(),
            'messages' => ContactMessage::latest()->limit(5)->get(),
            'recentOrders' => Order::latest('id')->limit(5)->get(),
        ];

        if ($canReport) {
            $data += $this->analytics($analytics);
        }

        if (auth()->user()->can('manage-inventory')) {
            $data['lowIngredients'] = Ingredient::low()->orderBy('name')->limit(8)->get();
            $data['lowDishes'] = MenuItem::where('stock_mode', 'quantity')
                ->where('stock_qty', '<=', config('restaurant.inventory.low_dish_stock'))
                ->orderBy('stock_qty')->limit(8)->get();
        }

        return view('admin.dashboard', $data);
    }

    private function analytics(AnalyticsService $a): array
    {
        $today = now()->startOfDay();
        $endToday = now()->endOfDay();
        $yesterday = $today->copy()->subDay();
        $from30 = $today->copy()->subDays(29);
        $prevFrom30 = $from30->copy()->subDays(30);
        $prevTo30 = $from30->copy()->subDay()->endOfDay();

        $todayNow = $a->summary($today, $endToday);
        $todayPrev = $a->summary($yesterday, $yesterday->copy()->endOfDay());
        $now30 = $a->summary($from30, $endToday);
        $prev30 = $a->summary($prevFrom30, $prevTo30);

        $customers = Cache::remember(
            'dashboard.customers.' . $today->toDateString(),
            300,
            fn() => $a->customerReport($from30, $endToday, 'monthly')['summary']
        );

        $profit = $a->profitLoss($from30, $endToday, 'daily', false)['summary'];
        $profitPrev = $a->profitLoss($prevFrom30, $prevTo30, 'daily', false)['summary'];

        $series = $a->series($from30, $endToday, 'daily');
        $breakdown = $a->breakdown($from30, $endToday);
        $statuses = $a->statusCounts($from30, $endToday);
        $top = $a->topItems($from30, $endToday, 6);
        $hours = $a->hourly($from30, $endToday);

        return [
            'kpis' => [
                [
                    'label' => "Today's sales",
                    'value' => money($todayNow['revenue']),
                    'icon' => 'cash',
                    'tone' => 'indigo',
                    'change' => AnalyticsService::change($todayNow['revenue'], $todayPrev['revenue']),
                    'sub' => 'vs yesterday'
                ],
                [
                    'label' => "Today's orders",
                    'value' => number_format($todayNow['orders']),
                    'icon' => 'receipt',
                    'tone' => 'emerald',
                    'change' => AnalyticsService::change($todayNow['orders'], $todayPrev['orders']),
                    'sub' => 'vs yesterday'
                ],
                [
                    'label' => 'Revenue, last 30 days',
                    'value' => money($now30['revenue']),
                    'icon' => 'trend',
                    'tone' => 'amber',
                    'change' => AnalyticsService::change($now30['revenue'], $prev30['revenue']),
                    'sub' => 'vs previous 30 days'
                ],
                [
                    'label' => 'Average order value',
                    'value' => money($now30['aov']),
                    'icon' => 'chart',
                    'tone' => 'cyan',
                    'change' => AnalyticsService::change($now30['aov'], $prev30['aov']),
                    'sub' => 'last 30 days'
                ],
                [
                    'label' => 'New customers, 30 days',
                    'value' => number_format($customers['new']),
                    'icon' => 'users',
                    'tone' => 'pink',
                    'change' => null,
                    'sub' => number_format($customers['returning']) . ' returning'
                ],
                [
                    'label' => 'Net profit, last 30 days',
                    'value' => money($profit['net']),
                    'icon' => 'trend',
                    'tone' => $profit['net'] >= 0 ? 'emerald' : 'rose',
                    'change' => AnalyticsService::change($profit['net'], $profitPrev['net']),
                    'sub' => $profit['net_margin'] . '% margin'
                ],
            ],
            'revenueChart' => Chart::line(
                array_column($series, 'label'),
                [['label' => 'Revenue', 'data' => Chart::round(array_column($series, 'revenue')), 'color' => '#6366F1']],
                true,
            ),
            'typeChart' => Chart::doughnut(
                array_map('ucfirst', array_keys($breakdown['type'])),
                array_column($breakdown['type'], 'orders'),
                'Orders',
                ['#F59E0B', '#06B6D4', '#8B5CF6'],
            ),
            'statusChart' => Chart::doughnut(
                array_map(fn($s) => ucfirst(str_replace('_', ' ', $s)), array_keys($statuses)),
                array_values($statuses),
                'Orders',
                array_map(fn($s) => self::STATUS_COLORS[$s] ?? '#64748B', array_keys($statuses)),
            ),
            'topChart' => Chart::bar(
                array_column($top, 'name'),
                [['label' => 'Portions sold', 'data' => array_column($top, 'qty'), 'color' => '#10B981']],
                false,
                true,
            ),
            'hoursChart' => Chart::bar(
                array_map(fn($h) => Carbon::createFromTime($h)->format('g A'), array_keys($hours)),
                [['label' => 'Orders', 'data' => $hours, 'color' => '#EC4899']],
            ),
            'reservationChart' => $this->reservationChart($today),
        ];
    }

    private function reservationChart(Carbon $today): array
    {
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $today->copy()->addDays($i);
            $days[$day->toDateString()] = ['label' => $day->format('D j'), 'bookings' => 0, 'guests' => 0];
        }

        $rows = Reservation::blocking()
            ->whereBetween('reserved_at', [$today, $today->copy()->addDays(6)->endOfDay()])
            ->get(['reserved_at', 'party_size']);

        foreach ($rows as $row) {
            $key = $row->reserved_at->toDateString();
            $days[$key]['bookings']++;
            $days[$key]['guests'] += $row->party_size;
        }

        return Chart::bar(array_column($days, 'label'), [
            ['label' => 'Bookings', 'data' => array_column($days, 'bookings'), 'color' => '#06B6D4'],
            ['label' => 'Guests', 'data' => array_column($days, 'guests'), 'color' => '#8B5CF6'],
        ]);
    }
}
