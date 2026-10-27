<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    /** @return array<int, Carbon> */
    public function slotTimes(Carbon $day): array
    {
        $cfg = config('restaurant.reservation');
        $day = $day->copy()->startOfDay();

        $t = $day->copy()->setTimeFromTimeString($cfg['open']);
        $last = $day->copy()->setTimeFromTimeString($cfg['last_seating']);

        $times = [];
        while ($t->lte($last)) {
            $times[] = $t->copy();
            $t->addMinutes($cfg['step_minutes']);
        }

        return $times;
    }

    public function slots(Carbon $day, int $partySize): array
    {
        $earliest = now()->addMinutes(config('restaurant.reservation.min_notice_minutes'));
        $existing = $this->blockingAround($day);

        return collect($this->slotTimes($day))->map(fn(Carbon $t) => [
            'time' => $t->format('H:i'),
            'label' => $t->format('g:i A'),
            'available' => $t->gte($earliest) && $this->fits($existing, $t, $partySize),
        ])->all();
    }

    public function book(array $data, string $locale): Reservation
    {
        $cfg = config('restaurant.reservation');
        $start = Carbon::createFromFormat('Y-m-d H:i', $data['date'] . ' ' . $data['time']);

        $validTime = collect($this->slotTimes($start))->contains(fn(Carbon $t) => $t->format('H:i') === $start->format('H:i'));

        if (! $validTime || $start->lt(now()->addMinutes($cfg['min_notice_minutes']))) {
            throw ValidationException::withMessages(['time' => __('forms.reservation.errors.invalid_time')]);
        }

        try {
            return Cache::lock('reservation-booking', 10)->block(5, function () use ($data, $start, $locale, $cfg) {
                return DB::transaction(function () use ($data, $start, $locale, $cfg) {
                    $party = (int) $data['party_size'];

                    if (! $this->fits($this->blockingAround($start), $start, $party)) {
                        throw ValidationException::withMessages(['time' => __('forms.reservation.errors.slot_full')]);
                    }

                    do {
                        $reference = 'MC-' . Str::upper(Str::random(8));
                    } while (Reservation::where('reference', $reference)->exists());

                    return Reservation::create([
                        'reference' => $reference,
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'phone' => $data['phone'],
                        'reserved_at' => $start,
                        'duration_minutes' => $cfg['duration_minutes'],
                        'party_size' => $party,
                        'message' => $data['message'] ?? null,
                        'status' => ReservationStatus::Pending,
                        'status_changed_at' => now(),
                        'locale' => $locale,
                    ]);
                });
            });
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['time' => __('forms.reservation.errors.busy')]);
        }
    }

    private function blockingAround(Carbon $day): Collection
    {
        $duration = config('restaurant.reservation.duration_minutes');

        return Reservation::query()
            ->blocking()
            ->whereBetween('reserved_at', [
                $day->copy()->startOfDay()->subMinutes($duration),
                $day->copy()->endOfDay()->addMinutes($duration),
            ])
            ->get();
    }

    private function fits(Collection $existing, Carbon $start, int $party): bool
    {
        $end = $start->copy()->addMinutes(config('restaurant.reservation.duration_minutes'));

        $taken = $existing
            ->filter(fn(Reservation $r) => $r->reserved_at->lt($end) && $r->ends_at->gt($start))
            ->sum('party_size');

        return $taken + $party <= config('restaurant.reservation.capacity');
    }
}
