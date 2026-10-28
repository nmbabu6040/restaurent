<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Mail\ReservationStatusMail;
use App\Models\ActivityLog;
use App\Models\Reservation;
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
        return view('admin.reservations.show', compact('reservation'));
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
}
