<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\PosException;
use App\Http\Controllers\Controller;
use App\Models\CashSession;
use App\Services\CashDrawerService;
use Illuminate\Http\Request;

class CashDrawerController extends Controller
{
    public function __construct(private CashDrawerService $drawer) {}

    public function index()
    {
        $shift = $this->drawer->current();

        return view('admin.pos.shift', [
            'shift' => $shift,
            'cashSales' => $shift ? $this->drawer->cashSales($shift) : 0,
            'expected' => $shift ? $this->drawer->expected($shift) : 0,
            'history' => CashSession::with('opener', 'closer')->whereNotNull('closed_at')->latest('id')->limit(15)->get(),
        ]);
    }

    public function open(Request $request)
    {
        $data = $request->validate(['opening_cash' => ['required', 'numeric', 'min:0', 'max:99999999', 'decimal:0,2']]);

        try {
            $this->drawer->open((float) $data['opening_cash'], $request->user());
        } catch (PosException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.pos.index')->with('status', 'Shift opened.');
    }

    public function close(Request $request)
    {
        $data = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0', 'max:99999999', 'decimal:0,2'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $shift = $this->drawer->current();

        if (! $shift) {
            return back()->with('error', 'There is no open shift.');
        }

        try {
            $closed = $this->drawer->close($shift, (float) $data['counted_cash'], $data['note'] ?? null, $request->user());
        } catch (PosException $e) {
            return back()->with('error', $e->getMessage());
        }

        $difference = round((float) $closed->counted_cash - (float) $closed->expected_cash, 2);

        return redirect()->route('admin.pos.shift')->with('status', 'Shift closed. ' . ($difference == 0.0 ? 'The cash matches.' : 'Difference: ' . money($difference) . '.'));
    }
}
