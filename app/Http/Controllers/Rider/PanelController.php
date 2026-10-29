<?php

namespace App\Http\Controllers\Rider;

use App\Exceptions\DeliveryException;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Rider;
use App\Services\DeliveryService;
use Illuminate\Http\Request;

class PanelController extends Controller
{
    public function __construct(private DeliveryService $deliveries) {}

    public function index()
    {
        $rider = $this->rider();
        $unsettled = fn() => $rider->deliveries()->where('status', 'delivered')->whereNull('settled_at');

        return view('rider.dashboard', [
            'rider' => $rider,
            'open' => $rider->deliveries()->whereIn('status', ['assigned', 'picked_up'])->with('order')->orderBy('assigned_at')->get(),
            'problems' => $rider->deliveries()->where('status', 'failed')->with('order')->latest('id')->limit(5)->get(),
            'deliveredToday' => $rider->deliveries()->where('status', 'delivered')->whereDate('delivered_at', now()->toDateString())->count(),
            'cashToHandIn' => (float) $unsettled()->sum('cash_collected'),
            'feesEarned' => (float) $unsettled()->sum('rider_fee'),
            'signature' => $this->signature($rider),
        ]);
    }

    public function show(Delivery $delivery)
    {
        $delivery = $this->owned($delivery);

        return view('rider.show', ['rider' => $this->rider(), 'delivery' => $delivery->load('order.items')]);
    }

    public function pickup(Delivery $delivery)
    {
        return $this->act($delivery, fn($d) => $this->deliveries->pickUp($d), 'Marked as picked up. Drive safe!', false);
    }

    public function deliver(Delivery $delivery)
    {
        return $this->act($delivery, fn($d) => $this->deliveries->deliver($d), 'Delivered. Well done!', true);
    }

    public function problem(Request $request, Delivery $delivery)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);

        return $this->act($delivery, fn($d) => $this->deliveries->fail($d, $data['reason']), 'The office has been told about the problem.', true);
    }

    public function toggleOnline()
    {
        $rider = $this->rider();
        $rider->update(['is_online' => ! $rider->is_online]);

        return back()->with('status', $rider->is_online ? 'You are online.' : 'You are offline.');
    }

    /** The page reloads itself when this changes (a new delivery, or the office moved one). */
    public function poll()
    {
        return response()->json(['signature' => $this->signature($this->rider())]);
    }

    private function act(Delivery $delivery, \Closure $action, string $message, bool $backToList)
    {
        $delivery = $this->owned($delivery);

        try {
            $action($delivery);
        } catch (DeliveryException $e) {
            return back()->with('error', $e->getMessage());
        }

        return ($backToList ? redirect()->route('rider.dashboard') : back())->with('status', $message);
    }

    /** A rider can only ever see and change their own deliveries. */
    private function owned(Delivery $delivery): Delivery
    {
        abort_unless($delivery->rider_id === $this->rider()->id, 404);

        return $delivery;
    }

    private function rider(): Rider
    {
        return request()->user()->rider;
    }

    private function signature(Rider $rider): string
    {
        return $rider->deliveries()->whereIn('status', ['assigned', 'picked_up'])->orderBy('id')->get(['id', 'status'])
            ->map(fn($d) => $d->id . ':' . $d->status->value)->implode(',');
    }
}
