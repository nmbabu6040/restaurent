<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function __construct(private CartService $cart) {}

    public function show()
    {
        return view('cart.show', $this->cart->summary());
    }

    public function add(Request $request)
    {
        $max = config('restaurant.ordering.max_qty');

        $data = $request->validate([
            'item_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:' . $max],
        ]);

        $item = MenuItem::query()
            ->whereKey($data['item_id'])
            ->where('is_available', true)
            ->whereHas('category', fn($q) => $q->where('is_active', true))
            ->first();

        if (! $item) {
            throw ValidationException::withMessages(['item_id' => __('orders.unavailable')]);
        }

        $this->cart->add($item->id, $data['quantity'] ?? 1);
        $message = __('orders.added', ['name' => $item->name]);

        return $request->wantsJson()
            ? response()->json(['count' => $this->cart->count(), 'message' => $message])
            : back()->with('status', $message);
    }

    public function update(Request $request, int $itemId)
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:' . config('restaurant.ordering.max_qty')],
        ]);

        $this->cart->set($itemId, $data['quantity']);

        return $this->respond($request);
    }

    public function destroy(Request $request, int $itemId)
    {
        $this->cart->remove($itemId);

        return $this->respond($request);
    }

    public function applyCoupon(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:30']]);

        $this->cart->applyCoupon($data['code']);

        return $this->respond($request, __('orders.coupon.applied'));
    }

    public function removeCoupon(Request $request)
    {
        $this->cart->removeCoupon();

        return $this->respond($request, __('orders.coupon.removed'));
    }

    private function respond(Request $request, ?string $message = null)
    {
        $summary = $this->cart->summary();

        if ($request->wantsJson()) {
            return response()->json([
                'count' => $this->cart->count(),
                'html' => view('cart._content', $summary)->render(),
                'message' => $message,
            ]);
        }

        return redirect()->route('cart.show')->with('status', $message);
    }
}
