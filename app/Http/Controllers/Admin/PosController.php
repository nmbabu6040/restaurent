<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderType;
use App\Exceptions\PosException;
use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Reservation;
use App\Services\CashDrawerService;
use App\Services\PosService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PosController extends Controller
{
    public function __construct(private PosService $pos, private CashDrawerService $drawer) {}

    public function index()
    {
        $open = Order::posOpen()->with('items')->orderBy('id')->get();

        return view('admin.pos.index', [
            'tables' => DiningTable::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'byTable' => $open->whereNotNull('dining_table_id')->keyBy('dining_table_id'),
            'takeaways' => $open->filter(fn($o) => $o->type === OrderType::Pickup)->values(),
            // 'reservedSoon' => Reservation::where('status', 'confirmed')
            //     ->whereNotNull('dining_table_id')->whereNull('seated_order_id')
            //     ->whereBetween('reserved_at', [now()->subMinutes(30), now()->addHours(2)])
            //     ->orderBy('reserved_at')->get()->groupBy('dining_table_id'),

            'reservedSoon' => Reservation::where('status', 'confirmed')
                ->whereNotNull('dining_table_id')
                ->whereNull('seated_order_id')
                ->whereBetween('reserved_at', [
                    now()->subMinutes(30),
                    now()->addHours(2),
                ])
                ->orderBy('reserved_at')
                ->get()
                ->groupBy('dining_table_id'),
            'shift' => $this->drawer->current(),
            'signature' => $this->signature(),
        ]);
    }

    /** Other devices at the counter poll this to refresh the table list. */
    public function state()
    {
        return response()->json(['signature' => $this->signature()])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['dine_in', 'pickup'])],
            'dining_table_id' => ['nullable', 'integer'],
            'guests' => ['nullable', 'integer', 'between:1,50'],
            'name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+\-\s()]{7,20}$/'],
        ]);

        try {
            $order = $this->pos->open(OrderType::from($data['type']), $data, $request->user());
        } catch (PosException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.pos.show', $order);
    }

    public function show(Order $order)
    {
        $this->posOrFail($order);

        return view('admin.pos.order', [
            'order' => $order->load('items', 'posPayments', 'cashier'),
            'categories' => MenuCategory::active()->ordered()
                ->with(['items' => fn($q) => $q->where('is_available', true)->with(['activeVariations', 'activeAddons'])->orderBy('sort_order')])
                ->get()->filter(fn($c) => $c->items->isNotEmpty())->values(),
            'tables' => DiningTable::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Order $order)
    {
        $this->posOrFail($order);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:300'],
            'guests' => ['nullable', 'integer', 'between:1,50'],
            'name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'dining_table_id' => ['nullable', 'integer'],
        ]);

        try {
            $this->pos->updateInfo($order, $data);
        } catch (PosException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Saved.');
    }

    public function addLine(Request $request, Order $order)
    {
        $this->posOrFail($order);

        $data = $request->validate([
            'item_id' => ['required', 'integer'],
            'variation_id' => ['nullable', 'integer'],
            'addons' => ['nullable', 'array', 'max:20'],
            'addons.*' => ['integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return $this->run($request, $order, function () use ($order, $data) {
            $item = MenuItem::find($data['item_id']);

            if (! $item) {
                throw new PosException('That dish was not found.');
            }

            $this->pos->addLine($order, $item, $data['variation_id'] ?? null, $data['addons'] ?? [], $data['quantity'] ?? 1);
        });
    }

    public function setQuantity(Request $request, Order $order, int $line)
    {
        $this->posOrFail($order);
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:99']]);

        return $this->run($request, $order, fn() => $this->pos->setQuantity($order, $order->items()->findOrFail($line), (int) $data['quantity'], $request->user()));
    }

    public function removeLine(Request $request, Order $order, int $line)
    {
        $this->posOrFail($order);

        return $this->run($request, $order, fn() => $this->pos->removeLine($order, $order->items()->findOrFail($line), $request->user()));
    }

    public function discount(Request $request, Order $order)
    {
        $this->posOrFail($order);

        $data = $request->validate([
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'numeric', 'min:0', 'max:99999999', 'decimal:0,2'],
            'reason' => ['nullable', 'string', 'max:120'],
        ]);

        return $this->run($request, $order, fn() => $this->pos->setDiscount($order, $data['type'], (float) $data['value'], $data['reason'] ?? null, $request->user()));
    }

    /** Marks the new items as sent and answers with the kitchen ticket, which opens in a new tab and prints. */
    public function send(Request $request, Order $order)
    {
        $this->posOrFail($order);

        try {
            $rows = $this->pos->send($order);
        } catch (PosException $e) {
            return back()->with('error', $e->getMessage());
        }

        return view('admin.pos.kot', ['order' => $order->fresh(), 'rows' => $rows, 'reprint' => false]);
    }

    public function kot(Order $order)
    {
        $this->posOrFail($order);

        return view('admin.pos.kot', [
            'order' => $order,
            'rows' => $order->items->map(fn($l) => ['line' => $l, 'qty' => $l->quantity]),
            'reprint' => true,
        ]);
    }

    public function pay(Order $order)
    {
        $this->posOrFail($order);

        if ($order->payment_status === 'paid' || in_array($order->status->value, ['completed', 'cancelled'], true)) {
            return redirect()->route('admin.pos.show', $order);
        }

        return view('admin.pos.pay', ['order' => $order->load('items'), 'due' => (float) $order->total]);
    }

    public function payStore(Request $request, Order $order)
    {
        $this->posOrFail($order);

        $data = $request->validate([
            'payments' => ['required', 'array', 'min:1', 'max:6'],
            'payments.*.method' => ['required', Rule::in(array_keys(OrderPayment::METHODS))],
            'payments.*.amount' => ['required', 'numeric', 'gt:0', 'max:99999999', 'decimal:0,2'],
            'payments.*.tendered' => ['nullable', 'numeric', 'min:0', 'max:99999999', 'decimal:0,2'],
            'payments.*.reference' => ['nullable', 'string', 'max:60'],
        ]);

        foreach ($data['payments'] as $p) {
            if ($p['method'] === 'cash' && filled($p['tendered'] ?? null) && (float) $p['tendered'] < (float) $p['amount']) {
                return back()->withInput()->with('error', 'The cash given is less than the cash amount.');
            }
        }

        try {
            $this->pos->pay($order, $data['payments'], $request->user());
        } catch (PosException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (ValidationException $e) {
            return back()->withInput()->with('error', $e->validator->errors()->first());
        }

        return redirect()->route('admin.orders.print', ['order' => $order, 'type' => 'receipt', 'autoprint' => 1]);
    }

    public function cancel(Request $request, Order $order)
    {
        $this->posOrFail($order);

        try {
            $this->pos->cancel($order, $request->user());
        } catch (PosException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.pos.index')->with('status', 'Bill cancelled.');
    }

    public function void(Request $request, Order $order)
    {
        $this->posOrFail($order);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:120']]);

        try {
            $this->pos->void($order, $data['reason'], $request->user());
        } catch (PosException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.pos.index')->with('status', 'Bill voided. The payment was taken out of the drawer and the stock is back.');
    }

    private function run(Request $request, Order $order, \Closure $action)
    {
        try {
            $action();
        } catch (PosException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['html' => view('admin.pos._ticket', ['order' => $order->fresh('items')])->render()]);
    }

    private function posOrFail(Order $order): void
    {
        abort_unless($order->source === 'pos', 404);
    }

    /** Changes whenever a table is opened, paid, changed, or the kitchen marks food ready. */
    private function signature(): string
    {
        return Order::posOpen()->withCount('items')->withSum('items as ready_total', 'ready_qty')
            ->orderBy('id')->get(['id', 'total', 'status'])
            ->map(fn($o) => $o->id . ':' . $o->total . ':' . $o->items_count . ':' . $o->status->value . ':' . (int) $o->ready_total)
            ->implode('|');
    }
}
