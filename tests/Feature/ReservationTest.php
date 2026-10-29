<?php

namespace Tests\Feature;

use App\Mail\ReservationReceivedMail;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        // Monday 2 November 2026, 10:00 (restaurant time).
        $this->travelTo(Carbon::parse('2026-11-02 10:00:00'));
    }

    private function book(array $overrides = [])
    {
        return $this->postJson('/reservations', array_merge([
            'name' => 'Rahim Uddin',
            'email' => 'rahim@example.com',
            'phone' => '01712345678',
            'date' => '2026-11-03',
            'time' => '19:00',
            'party_size' => 4,
            'message' => 'Window seat please',
        ], $overrides));
    }

    public function test_valid_booking_is_saved_as_pending_and_emailed(): void
    {
        $this->book()->assertCreated()->assertJsonStructure(['redirect']);

        $reservation = Reservation::firstOrFail();
        $this->assertSame('pending', $reservation->status->value);
        $this->assertMatchesRegularExpression('/^MC-[A-Z0-9]{8}$/', $reservation->reference);
        $this->assertSame('2026-11-03 19:00:00', $reservation->reserved_at->format('Y-m-d H:i:s'));
        $this->assertSame(90, $reservation->duration_minutes);
        Mail::assertQueued(ReservationReceivedMail::class);

        $this->get(route('reservations.show', $reservation->reference))->assertOk()->assertSee($reservation->reference);
        $this->get('/reservations/MC-NOSUCHREF')->assertNotFound();
    }

    public function test_validation_errors(): void
    {
        $this->postJson('/reservations', [])->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'phone', 'date', 'time', 'party_size']);

        $this->book(['party_size' => 13])->assertJsonValidationErrors('party_size');
        $this->book(['party_size' => 0])->assertJsonValidationErrors('party_size');
        $this->book(['phone' => 'abc'])->assertJsonValidationErrors('phone');
        $this->book(['email' => 'not-an-email'])->assertJsonValidationErrors('email');
        $this->book(['date' => '2026-11-01'])->assertJsonValidationErrors('date');       // yesterday
        $this->book(['date' => '2027-06-01'])->assertJsonValidationErrors('date');       // beyond 60 days
        $this->book(['message' => str_repeat('x', 501)])->assertJsonValidationErrors('message');

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_honeypot_blocks_bots(): void
    {
        $this->book(['website' => 'http://spam.example'])->assertStatus(422)->assertJsonValidationErrors('website');

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_times_outside_the_slot_grid_are_rejected(): void
    {
        $this->book(['time' => '03:00'])->assertStatus(422)->assertJsonValidationErrors('time');
        $this->book(['time' => '19:10'])->assertStatus(422)->assertJsonValidationErrors('time');
        $this->book(['time' => '22:00'])->assertStatus(422)->assertJsonValidationErrors('time'); // after the last seating
    }

    public function test_minimum_notice_is_enforced(): void
    {
        // It is 10:00 now and the notice period is 2 hours.
        $this->book(['date' => '2026-11-02', 'time' => '11:00'])->assertStatus(422)->assertJsonValidationErrors('time');
        $this->book(['date' => '2026-11-02', 'time' => '12:00'])->assertCreated();
    }

    public function test_capacity_blocks_overlapping_bookings_only(): void
    {
        config(['restaurant.reservation.capacity' => 4]);

        $this->book(['party_size' => 4, 'time' => '19:00'])->assertCreated();

        // 19:30 overlaps the first booking (19:00 to 20:30).
        $this->book(['party_size' => 1, 'time' => '19:30'])->assertStatus(422)->assertJsonValidationErrors('time');
        $this->book(['party_size' => 1, 'time' => '19:00'])->assertStatus(422)->assertJsonValidationErrors('time');

        // 21:00 starts after the first booking has ended.
        $this->book(['party_size' => 4, 'time' => '21:00'])->assertCreated();

        $this->assertDatabaseCount('reservations', 2);
    }

    public function test_cancelled_and_rejected_bookings_free_their_seats(): void
    {
        config(['restaurant.reservation.capacity' => 4]);

        $this->book(['party_size' => 4])->assertCreated();
        $this->book(['party_size' => 1, 'name' => 'Other'])->assertStatus(422);

        Reservation::query()->update(['status' => 'cancelled']);
        $this->book(['party_size' => 1, 'name' => 'Other'])->assertCreated();

        Reservation::query()->where('name', 'Other')->update(['status' => 'rejected']);
        $this->book(['party_size' => 3, 'name' => 'Third'])->assertCreated();
    }

    public function test_availability_endpoint_reports_slots(): void
    {
        $slots = collect($this->getJson('/reservations/availability?date=2026-11-02&party_size=2')
            ->assertOk()->json('slots'))->keyBy('time');

        $this->assertFalse($slots['11:00']['available']);   // inside the notice period
        $this->assertTrue($slots['12:00']['available']);
        $this->assertArrayHasKey('21:30', $slots->all());
        $this->assertArrayNotHasKey('22:00', $slots->all());

        config(['restaurant.reservation.capacity' => 4]);
        $this->book(['party_size' => 4])->assertCreated();

        $full = collect($this->getJson('/reservations/availability?date=2026-11-03&party_size=2')->json('slots'))->keyBy('time');
        $this->assertFalse($full['19:00']['available']);
        $this->assertFalse($full['20:00']['available']);
        $this->assertTrue($full['21:00']['available']);
        $this->assertTrue($full['12:00']['available']);
    }

    public function test_availability_validates_its_input(): void
    {
        $this->getJson('/reservations/availability')->assertStatus(422);
        $this->getJson('/reservations/availability?date=2026-11-01&party_size=2')->assertStatus(422);
        $this->getJson('/reservations/availability?date=2026-11-03&party_size=99')->assertStatus(422);
    }

    public function test_booking_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/reservations', []);
        }

        $this->postJson('/reservations', [])->assertStatus(429);
    }
}
