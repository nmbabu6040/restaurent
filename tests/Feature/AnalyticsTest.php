<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private AnalyticsService $analytics;

    protected function setUp(): void
    {
        parent::setUp();

        // Sunday 15 November 2026, noon. (2 November 2026 is a Monday.)
        $this->travelTo(Carbon::parse('2026-11-15 12:00:00'));
        $this->analytics = app(AnalyticsService::class);
    }

    private function order(string $when, array $attributes = [], array $items = []): Order
    {
        $order = Order::factory()->create(array_merge([
            'created_at' => Carbon::parse($when),
            'subtotal' => '100.00',
            'delivery_fee' => '0.00',
            'discount' => '0.00',
            'total' => '100.00',
        ], $attributes));

        foreach ($items as [$name, $quantity, $unit]) {
            $order->items()->create([
                'name_en' => $name,
                'quantity' => $quantity,
                'unit_price' => number_format($unit, 2, '.', ''),
                'line_total' => number_format($unit * $quantity, 2, '.', ''),
            ]);
        }

        return $order;
    }

    private function day(string $date): array
    {
        return [Carbon::parse($date)->startOfDay(), Carbon::parse($date)->endOfDay()];
    }

    // What counts as a sale

    public function test_only_real_sales_are_counted(): void
    {
        $this->order('2026-11-15 10:00', ['total' => '100.00']);                                              // cash, pending: counts
        $this->order('2026-11-15 10:05', ['total' => '50.00', 'status' => 'completed']);                     // completed: counts
        $this->order('2026-11-15 10:10', ['total' => '300.00', 'payment_method' => 'sslcommerz', 'payment_status' => 'paid']); // paid online: counts
        $this->order('2026-11-15 10:15', ['total' => '200.00', 'payment_method' => 'sslcommerz']);           // unpaid online: no
        $this->order('2026-11-15 10:20', ['total' => '400.00', 'status' => 'cancelled']);                    // cancelled: no

        $summary = $this->analytics->summary(...$this->day('2026-11-15'));

        $this->assertSame(3, $summary['orders']);
        $this->assertSame(450.0, $summary['revenue']);
        $this->assertSame(150.0, $summary['aov']);
    }

    public function test_summary_totals(): void
    {
        $this->order('2026-11-15 11:00', ['subtotal' => '380.00', 'delivery_fee' => '60.00', 'total' => '440.00'], [['Biryani', 1, 380]]);
        $this->order('2026-11-15 12:00', [
            'type' => 'pickup',
            'subtotal' => '200.00',
            'discount' => '20.00',
            'coupon_code' => 'WELCOME10',
            'total' => '180.00',
        ], [['Dal', 2, 100]]);

        $summary = $this->analytics->summary(...$this->day('2026-11-15'));

        $this->assertSame(2, $summary['orders']);
        $this->assertSame(620.0, $summary['revenue']);
        $this->assertSame(580.0, $summary['subtotal']);
        $this->assertSame(60.0, $summary['delivery']);
        $this->assertSame(20.0, $summary['discount']);
        $this->assertSame(1, $summary['coupons']);
        $this->assertSame(3, $summary['items']);
        $this->assertSame(310.0, $summary['aov']);
    }

    public function test_an_empty_period_has_no_division_by_zero(): void
    {
        $summary = $this->analytics->summary(...$this->day('2026-11-15'));

        $this->assertSame(0, $summary['orders']);
        $this->assertSame(0.0, $summary['aov']);
        $this->assertNull(AnalyticsService::change(5, 0));
        $this->assertSame(20.0, AnalyticsService::change(120, 100));
        $this->assertSame(-50.0, AnalyticsService::change(50, 100));
    }

    public function test_orders_outside_the_period_are_excluded(): void
    {
        $this->order('2026-11-14 23:59');
        $this->order('2026-11-15 00:00');
        $this->order('2026-11-16 00:00');

        $this->assertSame(1, $this->analytics->summary(...$this->day('2026-11-15'))['orders']);
    }

    // Time series

    public function test_daily_series_includes_empty_days(): void
    {
        $this->order('2026-11-10 12:00', ['total' => '100.00']);
        $this->order('2026-11-12 12:00', ['total' => '300.00']);

        $series = $this->analytics->series(Carbon::parse('2026-11-10')->startOfDay(), Carbon::parse('2026-11-12')->endOfDay(), 'daily');

        $this->assertSame(['10 Nov', '11 Nov', '12 Nov'], array_column($series, 'label'));
        $this->assertSame([100.0, 0.0, 300.0], array_column($series, 'revenue'));
        $this->assertSame([1, 0, 1], array_column($series, 'orders'));
    }

    public function test_weekly_series_starts_weeks_on_monday(): void
    {
        $this->order('2026-11-08 12:00', ['total' => '100.00']); // Sunday: belongs to the week of 2 Nov
        $this->order('2026-11-09 12:00', ['total' => '200.00']); // Monday: starts the week of 9 Nov

        $series = $this->analytics->series(Carbon::parse('2026-11-01')->startOfDay(), Carbon::parse('2026-11-15')->endOfDay(), 'weekly');

        $this->assertSame(['26 Oct', '02 Nov', '09 Nov'], array_column($series, 'label'));
        $this->assertSame([0.0, 100.0, 200.0], array_column($series, 'revenue'));
    }

    public function test_monthly_series(): void
    {
        $this->order('2026-10-25 12:00', ['total' => '50.00']);
        $this->order('2026-11-03 12:00', ['total' => '70.00']);

        $series = $this->analytics->series(Carbon::parse('2026-10-20')->startOfDay(), Carbon::parse('2026-11-15')->endOfDay(), 'monthly');

        $this->assertSame(['Oct 2026', 'Nov 2026'], array_column($series, 'label'));
        $this->assertSame([50.0, 70.0], array_column($series, 'revenue'));
    }

    // Breakdowns

    public function test_breakdown_by_type_and_payment_method(): void
    {
        $this->order('2026-11-15 10:00', ['type' => 'delivery', 'total' => '100.00']);
        $this->order('2026-11-15 11:00', ['type' => 'pickup', 'total' => '60.00']);
        $this->order('2026-11-15 12:00', ['type' => 'pickup', 'total' => '40.00', 'payment_method' => 'sslcommerz', 'payment_status' => 'paid']);

        $breakdown = $this->analytics->breakdown(...$this->day('2026-11-15'));

        $this->assertSame(['orders' => 1, 'revenue' => 100.0], $breakdown['type']['delivery']);
        $this->assertSame(['orders' => 2, 'revenue' => 100.0], $breakdown['type']['pickup']);
        $this->assertSame(['orders' => 2, 'revenue' => 160.0], $breakdown['payment']['cod']);
        $this->assertSame(['orders' => 1, 'revenue' => 40.0], $breakdown['payment']['sslcommerz']);
    }

    public function test_status_counts_include_cancelled_orders(): void
    {
        $this->order('2026-11-15 10:00', ['status' => 'pending']);
        $this->order('2026-11-15 10:00', ['status' => 'cancelled']);
        $this->order('2026-11-15 10:00', ['status' => 'cancelled']);

        $this->assertSame(['pending' => 1, 'cancelled' => 2], $this->analytics->statusCounts(...$this->day('2026-11-15')));
    }

    public function test_top_items_are_ranked_by_quantity_and_ignore_cancelled_orders(): void
    {
        $this->order('2026-11-15 10:00', [], [['Biryani', 1, 380], ['Dal', 3, 100]]);
        $this->order('2026-11-15 11:00', [], [['Biryani', 2, 380], ['Dal', 2, 100], ['Firni', 1, 110]]);
        $this->order('2026-11-15 12:00', ['status' => 'cancelled'], [['Firni', 50, 110]]);

        $top = $this->analytics->topItems(...$this->day('2026-11-15'));

        $this->assertSame(['Dal', 'Biryani', 'Firni'], array_column($top, 'name'));
        $this->assertSame([5, 3, 1], array_column($top, 'qty'));
        $this->assertSame(500.0, $top[0]['revenue']);
        $this->assertCount(2, $this->analytics->topItems(...[...$this->day('2026-11-15'), 2]));
    }

    public function test_hourly_counts(): void
    {
        $this->order('2026-11-15 19:30');
        $this->order('2026-11-15 19:45');
        $this->order('2026-11-15 08:05');

        $hours = $this->analytics->hourly(...$this->day('2026-11-15'));

        $this->assertCount(24, $hours);
        $this->assertSame(2, $hours[19]);
        $this->assertSame(1, $hours[8]);
        $this->assertSame(0, $hours[12]);
    }

    // Periods

    public function test_period_presets(): void
    {
        [$from, $to] = $this->analytics->resolveRange('7d');
        $this->assertSame('2026-11-09', $from->toDateString());
        $this->assertSame('2026-11-15', $to->toDateString());

        [$from, $to] = $this->analytics->resolveRange('this_month');
        $this->assertSame('2026-11-01', $from->toDateString());

        [$from, $to] = $this->analytics->resolveRange('last_month');
        $this->assertSame('2026-10-01', $from->toDateString());
        $this->assertSame('2026-10-31', $to->toDateString());

        [$from, $to] = $this->analytics->resolveRange('custom', '2026-11-03', '2026-11-05');
        $this->assertSame('2026-11-03 00:00:00', $from->toDateTimeString());
        $this->assertSame('2026-11-05 23:59:59', $to->toDateTimeString());
    }

    // Customers

    public function test_phone_formats_count_as_one_customer(): void
    {
        $this->order('2026-11-10 12:00', ['phone' => '01711111111', 'name' => 'Rahim']);
        $this->order('2026-11-11 12:00', ['phone' => '+8801711111111', 'name' => 'Rahim U.']);
        $this->order('2026-11-12 12:00', ['phone' => '017 1111 1111', 'name' => 'Rahim Uddin']);
        $this->order('2026-11-12 13:00', ['phone' => '01722222222', 'name' => 'Karim']);

        $this->assertSame(711111111, (int) AnalyticsService::customerKey('+8801711111111'));
        $this->assertSame(AnalyticsService::customerKey('01711111111'), AnalyticsService::customerKey('+8801711111111'));

        $report = $this->analytics->customerReport(Carbon::parse('2026-11-01')->startOfDay(), Carbon::parse('2026-11-15')->endOfDay(), 'daily');

        $this->assertSame(2, $report['summary']['total']);
        $this->assertSame('Rahim Uddin', $report['top'][0]['name']); // the most recent name wins
        $this->assertSame(3, $report['top'][0]['range_orders']);
    }

    public function test_new_versus_returning_customers(): void
    {
        $this->order('2026-10-01 12:00', ['phone' => '01711111111']); // A: first order, before the period
        $this->order('2026-11-10 12:00', ['phone' => '01711111111']); // A again: returning
        $this->order('2026-11-11 12:00', ['phone' => '01722222222']); // B: brand new

        $report = $this->analytics->customerReport(Carbon::parse('2026-11-01')->startOfDay(), Carbon::parse('2026-11-15')->endOfDay(), 'daily');

        $this->assertSame(2, $report['summary']['total']);
        $this->assertSame(2, $report['summary']['active']);
        $this->assertSame(1, $report['summary']['new']);
        $this->assertSame(1, $report['summary']['returning']);
        $this->assertSame(50.0, $report['summary']['repeat_rate']);

        $byLabel = collect($report['series'])->keyBy('label');
        $this->assertSame(['new' => 0, 'returning' => 1], ['new' => $byLabel['10 Nov']['new'], 'returning' => $byLabel['10 Nov']['returning']]);
        $this->assertSame(['new' => 1, 'returning' => 0], ['new' => $byLabel['11 Nov']['new'], 'returning' => $byLabel['11 Nov']['returning']]);
    }

    public function test_customers_with_only_cancelled_orders_are_not_customers(): void
    {
        $this->order('2026-11-10 12:00', ['phone' => '01733333333', 'status' => 'cancelled']);

        $report = $this->analytics->customerReport(Carbon::parse('2026-11-01')->startOfDay(), Carbon::parse('2026-11-15')->endOfDay(), 'daily');

        $this->assertSame(0, $report['summary']['total']);
    }

    // Pages and access

    public function test_report_pages_are_for_admins_and_managers_only(): void
    {
        $staff = User::factory()->staff()->create();
        $manager = User::factory()->manager()->create();

        foreach (['/admin/sales', '/admin/customers', '/admin/sales/export', '/admin/customers/export'] as $url) {
            $this->get($url)->assertRedirect('/admin/login');
            $this->actingAs($staff)->get($url)->assertForbidden();
        }

        $this->actingAs($manager)->get('/admin/sales')->assertOk()->assertSee('Revenue');
        $this->actingAs($manager)->get('/admin/customers')->assertOk()->assertSee('Repeat rate');
    }

    public function test_pages_render_with_data_and_chart_configs(): void
    {
        $this->order('2026-11-14 19:00', [], [['Biryani', 2, 380]]);
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->get('/admin/sales?range=7d')
            ->assertOk()->assertSee('data-chart', false)->assertSee('Biryani')->assertViewHas('summary', fn($s) => $s['orders'] === 1);

        $this->actingAs($manager)->get('/admin/customers?range=7d&group=weekly')->assertOk()->assertSee('data-chart', false);
    }

    public function test_period_validation(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->get('/admin/sales')->assertOk();
        $this->actingAs($manager)->get('/admin/sales?range=custom&from=2026-11-03&to=2026-11-05')->assertOk();
        $this->actingAs($manager)->get('/admin/sales?from=2026-11-03&to=2026-11-05')->assertOk(); // dates alone mean "custom"

        $this->actingAs($manager)->get('/admin/sales?range=custom&from=2026-11-10&to=2026-11-01')->assertSessionHasErrors('to');
        $this->actingAs($manager)->get('/admin/sales?range=custom&from=2025-01-01&to=2026-11-15')->assertSessionHasErrors('to');
        $this->actingAs($manager)->get('/admin/sales?range=custom')->assertSessionHasErrors(['from', 'to']);
        $this->actingAs($manager)->get('/admin/sales?range=bogus')->assertSessionHasErrors('range');
        $this->actingAs($manager)->get('/admin/sales?group=yearly')->assertSessionHasErrors('group');
    }

    public function test_dashboard_hides_money_from_staff_and_shows_charts_to_managers(): void
    {
        $this->order('2026-11-15 10:00');

        $this->actingAs(User::factory()->staff()->create())->get('/admin')
            ->assertOk()->assertSee('Pending orders')->assertDontSee("Today's sales")->assertDontSee('data-chart', false);

        $this->actingAs(User::factory()->manager()->create())->get('/admin')
            ->assertOk()->assertSee("Today's sales")->assertSee('data-chart', false)->assertSee('Revenue, last 30 days');
    }

    public function test_dashboard_loads_on_an_empty_database(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk()->assertSee('No data for this period yet.');
    }

    // Exports

    public function test_sales_export_is_a_safe_csv_and_is_logged(): void
    {
        $manager = User::factory()->manager()->create();
        $good = $this->order('2026-11-14 12:00', ['name' => '=HYPERLINK("http://evil.example")', 'phone' => '01712345678']);
        $cancelled = $this->order('2026-11-14 13:00', ['status' => 'cancelled']);

        $response = $this->actingAs($manager)->get('/admin/sales/export?range=7d')->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBFReference,Date,Type", $csv);
        $this->assertStringContainsString($good->reference, $csv);
        $this->assertStringNotContainsString($cancelled->reference, $csv);
        $this->assertStringContainsString("'=HYPERLINK(", $csv); // formula neutralised
        $this->assertDatabaseHas('activity_logs', ['action' => 'report.exported', 'user_id' => $manager->id]);
    }

    public function test_customer_export_lists_everyone_with_lifetime_totals(): void
    {
        $manager = User::factory()->manager()->create();
        $this->order('2026-09-01 12:00', ['phone' => '01711111111', 'name' => 'Rahim', 'total' => '100.00']);
        $this->order('2026-11-10 12:00', ['phone' => '+8801711111111', 'name' => 'Rahim', 'total' => '250.00']);
        $this->order('2026-11-11 12:00', ['phone' => '01722222222', 'name' => '+cmd|calc', 'total' => '80.00']);

        $csv = $this->actingAs($manager)->get('/admin/customers/export')->assertOk()->streamedContent();

        $this->assertStringContainsString('Name,Phone,Orders,"Total spent","First order","Last order"', $csv);
        $this->assertStringContainsString('Rahim', $csv);
        $this->assertStringContainsString('350.00', $csv);   // 100 + 250, one customer
        $this->assertStringContainsString("'+cmd|calc", $csv);
        $this->assertSame(3, count(array_filter(explode("\n", trim($csv)))));  // header + 2 customers
        $this->assertSame(1, ActivityLog::where('action', 'report.exported')->count());
    }
}
