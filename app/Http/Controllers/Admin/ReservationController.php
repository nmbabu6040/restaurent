<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderType;
use App\Enums\ReservationStatus;
use App\Exceptions\PosException;
use App\Http\Controllers\Controller;
use App\Mail\ReservationStatusMail;
use App\Models\ActivityLog;
use App\Models\DiningTable;
use App\Models\Reservation;
use App\Services\PosService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::enum(ReservationStatus::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $reservations = Reservation::query()
            ->when($f['date'] ?? null, fn($q, $d) => $q->whereDate('reserved_at', $d))
            ->when($f['status'] ?? null, fn($q, $s) => $q->where('status', $s))
            ->when($f['q'] ?? null, function ($q, $term) {
                $like = '%' . addcslashes($term, '%_\\') . '%';
                $q->where(fn($w) => $w->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('reference', 'like', $like));
            })
            ->orderByDesc('reserved_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.reservations.index', ['reservations' => $reservations]);
    }

    public function show(Reservation $reservation)
    {
        return view('admin.reservations.show', [
            'reservation' => $reservation->load('seatedOrder'),
            'tables' => DiningTable::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function updateStatus(Request $request, Reservation $reservation)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['confirmed', 'rejected', 'cancelled'])],
        ]);

        $new = ReservationStatus::from($data['status']);

        if (! in_array($new, $reservation->status->transitions(), true)) {
            return back()->with('error', 'That status change is not allowed.');
        }

        $reservation->update(['status' => $new, 'status_changed_at' => now()]);

        ActivityLog::record(
            'reservation.' . $new->value,
            "Reservation {$reservation->reference} marked {$new->value}",
            $reservation,
        );

        Mail::to($reservation->email)
            ->locale($reservation->locale)
            ->queue(new ReservationStatusMail($reservation));

        return back()->with('status', "Reservation {$reservation->reference} marked {$new->value}. The guest has been emailed.");
    }

    public function setTable(Request $request, Reservation $reservation)
    {
        $data = $request->validate([
            'dining_table_id' => ['nullable', Rule::exists('dining_tables', 'id')->where('is_active', true)],
        ]);

        $reservation->update(['dining_table_id' => $data['dining_table_id'] ?? null]);

        return back()->with('status', $reservation->dining_table_id ? 'Table assigned.' : 'Table removed.');
    }

    /** The guests have arrived: open a bill on their table with their details already filled in. */
    public function seat(Request $request, Reservation $reservation, PosService $pos)
    {
        if ($reservation->status !== ReservationStatus::Confirmed) {
            return back()->with('error', 'Only a confirmed reservation can be seated.');
        }

        if ($reservation->seatedOrder && $reservation->seatedOrder->payment_status !== 'paid' && $reservation->seatedOrder->status->value !== 'cancelled') {
            return redirect()->route('admin.pos.show', $reservation->seatedOrder);
        }

        $tableId = $reservation->dining_table_id ?? $request->input('dining_table_id');

        try {
            $order = $pos->open(OrderType::DineIn, [
                'dining_table_id' => $tableId,
                'guests' => min(50, max(1, $reservation->party_size)),
                'name' => $reservation->name,
                'phone' => $reservation->phone,
            ], $request->user());
        } catch (PosException $e) {
            return back()->with('error', $e->getMessage());
        }

        $order->update(['notes' => $reservation->message]);
        $reservation->update(['dining_table_id' => $order->dining_table_id, 'seated_order_id' => $order->id]);

        ActivityLog::record('reservation.seated', "Reservation {$reservation->reference} seated at {$order->table_name}", $reservation);

        return redirect()->route('admin.pos.show', $order);
    }
}
