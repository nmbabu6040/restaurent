<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReservationRequest;
use App\Mail\ReservationReceivedMail;
use App\Models\Reservation;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ReservationController extends Controller
{
    public function __construct(private ReservationService $service) {}

    public function create()
    {
        $cfg = config('restaurant.reservation');

        return view('reservations.create', [
            'maxParty' => $cfg['max_party'],
            'minDate' => now()->toDateString(),
            'maxDate' => now()->addDays($cfg['advance_days'])->toDateString(),
            'times' => collect($this->service->slotTimes(now()))->map(fn(Carbon $t) => [
                'time' => $t->format('H:i'),
                'label' => $t->format('g:i A'),
            ]),
        ]);
    }

    public function availability(Request $request)
    {
        $cfg = config('restaurant.reservation');

        $data = $request->validate([
            'date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:today',
                'before_or_equal:' . now()->addDays($cfg['advance_days'])->toDateString(),
            ],
            'party_size' => ['required', 'integer', 'between:1,' . $cfg['max_party']],
        ]);

        return response()->json([
            'slots' => $this->service->slots(
                Carbon::createFromFormat('Y-m-d', $data['date'])->startOfDay(),
                (int) $data['party_size'],
            ),
        ]);
    }

    public function store(ReservationRequest $request)
    {
        $reservation = $this->service->book($request->validated(), app()->getLocale());

        Mail::to($reservation->email)
            ->locale($reservation->locale)
            ->queue(new ReservationReceivedMail($reservation));

        $url = route('reservations.show', $reservation->reference);

        return $request->wantsJson()
            ? response()->json(['redirect' => $url], 201)
            : redirect($url);
    }

    public function show(Reservation $reservation)
    {
        return view('reservations.show', compact('reservation'));
    }
}
