<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProfitLossTest extends TestCase
{
    use RefreshDatabase;

    private AnalyticsService $analytics;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-15 12:00:00'));
        $this->analytics = app(AnalyticsService::class);
    }

    private function sale(string $when, string $total, array $items, array $attributes = []): Order
    {
        $order = Order::factory()->create(array_merge([
            'created_at' => Carbon::parse($when),
            'subtotal' => $total,
            'delivery_fee' => '0.00',
            'discount' => '0.00',
            'total' => $total,
        ], $attributes));

        foreach ($items as [$name, $quantity, $unit, $cost]) {
            $order->items()->create([
                'name_en' => $name,
                'quantity' => $quantity,
                'unit_price' => $unit,
                'unit_cost' => $cost,
                'line_total' => $unit * $quantity,
            ]);
        }

        return $order;
    }

    private function wastage(string $quantity, string $unitCost, string $when): void
    {
        $ingredient = Ingredient::create(['name' => 'Rice ' . uniqid(), 'unit' => 'g', 'stock' => '0', 'reorder_level' => '0', 'unit_cost' => $unitCost]);

        (new StockMovement)->forceFill([
            'ingredient_id' => $ingredient->id,
            'type' => 'waste',
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'balance_after' => '0',
            'created_at' => Carbon::parse($when),
        ])->save();
    }

    private function scenario(): void
    {
        $this->sale('2026-11-15 10:00', '600.00', [['Biryani', 2, 300, 100]]);   // cost 200
        $this->sale('2026-11-15 11:00', '400.00', [['Dal', 4, 100, 0]]);         // no cost recorded
        $this->sale('2026-11-15 12:00', '999.00', [['Biryani', 3, 333, 100]], ['status' => 'cancelled']); // ignored
        $this->wastage('-10', '2', '2026-11-15 09:00');                           // 20
        Expense::create(['expense_date' => '2026-11-15', 'category' => 'rent', 'amount' => '100.00']);
        Expense::create(['expense_date' => '2026-11-15', 'category' => 'utilities', 'amount' => '50.00']);
        Expense::create(['expense_date' => '2026-10-01', 'category' => 'rent', 'amount' => '777.00']); // another month
    }

    public function test_profit_and_loss_figures(): void
    {
        $this->scenario();

        $report = $this->analytics->profitLoss(Carbon::parse('2026-11-15')->startOfDay(), Carbon::parse('2026-11-15')->endOfDay(), 'daily');
        $s = $report['summary'];

        $this->assertSame(1000.0, $s['revenue']);
        $this->assertSame(200.0, $s['cogs']);
        $this->assertSame(800.0, $s['gross']);
        $this->assertSame(80.0, $s['gross_margin']);
        $this->assertSame(20.0, $s['wastage']);
        $this->assertSame(150.0, $s['expenses']);
        $this->assertSame(630.0, $s['net']);
        $this->assertSame(63.0, $s['net_margin']);
        $this->assertSame(1, $s['uncosted']);

        $this->assertSame(['rent' => 100.0, 'utilities' => 50.0], $report['expense_categories']);
        $this->assertSame([['label' => '15 Nov', 'revenue' => 1000.0, 'costs' => 370.0, 'profit' => 630.0]], $report['series']);
    }

    public function test_dish_margins(): void
    {
        $this->scenario();

        $dishes = collect($this->analytics->profitLoss(Carbon::parse('2026-11-15')->startOfDay(), Carbon::parse('2026-11-15')->endOfDay(), 'daily')['dishes'])->keyBy('name');

        $this->assertSame(
            ['qty' => 2, 'revenue' => 600.0, 'cost' => 200.0, 'profit' => 400.0, 'margin' => 66.7, 'costed' => true],
            \Illuminate\Support\Arr::only($dishes['Biryani'], ['qty', 'revenue', 'cost', 'profit', 'margin', 'costed'])
        );
        $this->assertFalse($dishes['Dal']['costed']);
        $this->assertArrayNotHasKey('999', $dishes->all());
    }

    public function test_the_summary_only_mode_skips_the_detail(): void
    {
        $this->scenario();

        $report = $this->analytics->profitLoss(Carbon::parse('2026-11-15')->startOfDay(), Carbon::parse('2026-11-15')->endOfDay(), 'daily', false);

        $this->assertSame(630.0, $report['summary']['net']);
        $this->assertArrayNotHasKey('series', $report);
    }

    public function test_a_loss_is_negative(): void
    {
        $this->sale('2026-11-15 10:00', '100.00', [['Dal', 1, 100, 60]]);
        Expense::create(['expense_date' => '2026-11-15', 'category' => 'rent', 'amount' => '300.00']);

        $s = $this->analytics->profitLoss(Carbon::parse('2026-11-15')->startOfDay(), Carbon::parse('2026-11-15')->endOfDay(), 'daily', false)['summary'];

        $this->assertSame(-260.0, $s['net']);
    }

    public function test_an_empty_period_is_all_zero(): void
    {
        $s = $this->analytics->profitLoss(Carbon::parse('2026-11-15')->startOfDay(), Carbon::parse('2026-11-15')->endOfDay(), 'daily', false)['summary'];

        $this->assertSame(0.0, $s['net']);
        $this->assertSame(0.0, $s['net_margin']);
        $this->assertSame(0, $s['uncosted']);
    }

    public function test_costs_land_in_the_right_week(): void
    {
        $this->sale('2026-11-09 12:00', '300.00', [['Dal', 3, 100, 40]]);   // week of 9 Nov, cost 120
        Expense::create(['expense_date' => '2026-11-03', 'category' => 'rent', 'amount' => '500.00']); // week of 2 Nov

        $series = $this->analytics->profitLoss(Carbon::parse('2026-11-01')->startOfDay(), Carbon::parse('2026-11-15')->endOfDay(), 'weekly')['series'];

        $this->assertSame(['26 Oct', '02 Nov', '09 Nov'], array_column($series, 'label'));
        $this->assertSame([0.0, 500.0, 120.0], array_column($series, 'costs'));
        $this->assertSame([0.0, -500.0, 180.0], array_column($series, 'profit'));
    }

    public function test_the_report_page_and_export(): void
    {
        $this->scenario();
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->get('/admin/profit-loss?range=today')
            ->assertOk()->assertSee('Net profit')->assertSee('Food cost')->assertSee('no cost recorded')->assertSee('data-chart', false);

        $csv = $this->actingAs($manager)->get('/admin/profit-loss/export?range=today')->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBFPeriod,Revenue,Costs,Profit", $csv);
        $this->assertStringContainsString('Net profit,630', $csv);
        $this->assertDatabaseHas('activity_logs', ['action' => 'report.exported', 'user_id' => $manager->id]);

        $this->actingAs(User::factory()->staff()->create())->get('/admin/profit-loss')->assertForbidden();
        $this->actingAs(User::factory()->staff()->create())->get('/admin/profit-loss/export')->assertForbidden();
    }

    public function test_dashboard_shows_the_profit_card(): void
    {
        $this->scenario();

        $this->actingAs(User::factory()->manager()->create())->get('/admin')->assertOk()->assertSee('Net profit, last 30 days');
    }

    // Expenses

    public function test_expenses_can_be_added_edited_and_deleted(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->post(route('admin.expenses.store'), [
            'expense_date' => '2026-11-10',
            'category' => 'rent',
            'amount' => '25000',
            'description' => 'November rent',
        ])->assertRedirect(route('admin.expenses.index'));

        $expense = Expense::firstOrFail();
        $this->assertSame($manager->id, $expense->user_id);
        $this->actingAs($manager)->get('/admin/expenses?month=2026-11')->assertOk()->assertSee('November rent');
        $this->actingAs($manager)->get('/admin/expenses?month=2026-10')->assertOk()->assertDontSee('November rent');

        $this->actingAs($manager)->put(route('admin.expenses.update', $expense), [
            'expense_date' => '2026-11-10',
            'category' => 'utilities',
            'amount' => '1200',
        ])->assertRedirect();
        $this->assertSame('utilities', $expense->fresh()->category);

        $this->actingAs($manager)->delete(route('admin.expenses.destroy', $expense))->assertRedirect();
        $this->assertModelMissing($expense);
    }

    public function test_expense_validation(): void
    {
        $manager = User::factory()->manager()->create();
        $valid = ['expense_date' => '2026-11-10', 'category' => 'rent', 'amount' => '100'];

        $this->actingAs($manager)->post(route('admin.expenses.store'), array_merge($valid, ['expense_date' => '2026-12-25']))->assertSessionHasErrors('expense_date');
        $this->actingAs($manager)->post(route('admin.expenses.store'), array_merge($valid, ['amount' => '-5']))->assertSessionHasErrors('amount');
        $this->actingAs($manager)->post(route('admin.expenses.store'), array_merge($valid, ['amount' => '0']))->assertSessionHasErrors('amount');
        $this->actingAs($manager)->post(route('admin.expenses.store'), array_merge($valid, ['category' => 'ingredients']))->assertSessionHasErrors('category');
        $this->actingAs($manager)->post(route('admin.expenses.store'), [])->assertSessionHasErrors(['expense_date', 'category', 'amount']);

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_staff_cannot_use_expenses(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get('/admin/expenses')->assertForbidden();
        $this->actingAs($staff)->post(route('admin.expenses.store'), ['expense_date' => '2026-11-10', 'category' => 'rent', 'amount' => '1'])->assertForbidden();
    }
}
