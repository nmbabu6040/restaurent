<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\PosException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\KitchenService;

class KitchenController extends Controller
{
    public function __construct(private KitchenService $kitchen) {}

    public function index()
    {
        return view('admin.kitchen.index', ['tickets' => $this->kitchen->tickets()]);
    }

    public function tickets()
    {
        return $this->board();
    }

    public function ready(Order $order)
    {
        return $this->act(fn() => $this->kitchen->markReady($order));
    }

    public function readyLine(Order $order, int $line)
    {
        return $this->act(fn() => $this->kitchen->markReady($order, $order->items()->findOrFail($line)));
    }

    private function act(\Closure $action)
    {
        try {
            $action();
        } catch (PosException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->board();
    }

    private function board()
    {
        $tickets = $this->kitchen->tickets();

        return response()->json([
            'html' => view('admin.kitchen._tickets', ['tickets' => $tickets])->render(),
            'ids' => $tickets->map(fn($t) => $t['order']->id)->all(),
        ])->header('Cache-Control', 'no-store');
    }
}
