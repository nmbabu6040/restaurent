<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Delivery;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RiderController extends Controller
{
    public function index()
    {
        $unsettled = fn($q) => $q->where('status', 'delivered')->whereNull('settled_at');

        return view('admin.riders.index', [
            'riders' => Rider::query()
                ->withCount([
                    'deliveries as open_count' => fn($q) => $q->whereIn('status', ['assigned', 'picked_up']),
                    'deliveries as failed_count' => fn($q) => $q->where('status', 'failed'),
                ])
                ->withSum(['deliveries as unsettled_cash' => $unsettled], 'cash_collected')
                ->withSum(['deliveries as unsettled_fees' => $unsettled], 'rider_fee')
                ->orderByDesc('is_active')->orderBy('name')
                ->paginate(20),
            'online' => Rider::active()->where('is_online', true)->count(),
            'today' => Delivery::where('status', 'delivered')->whereDate('delivered_at', now()->toDateString())->count(),
            'cashOut' => (float) Delivery::where('status', 'delivered')->whereNull('settled_at')->sum('cash_collected'),
            'failed' => Delivery::where('status', 'failed')->count(),
        ]);
    }

    public function create()
    {
        return view('admin.riders.form', ['rider' => new Rider(['is_active' => true, 'vehicle' => 'bike', 'fee_per_delivery' => 0])]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $rider = DB::transaction(function () use ($data) {
            $user = filled($data['login_email'] ?? null) ? $this->makeLogin($data['name'], $data['login_email'], $data['login_password']) : null;

            return Rider::create($this->attributes($data) + ['user_id' => $user?->id]);
        });

        ActivityLog::record('rider.created', "Rider {$rider->name} created", $rider);

        return redirect()->route('admin.riders.show', $rider)->with('status', 'Rider created.');
    }

    public function show(Rider $rider)
    {
        $unsettled = $rider->deliveries()->where('status', 'delivered')->whereNull('settled_at')
            ->with('order:id,reference,total,payment_method')->orderBy('delivered_at')->get();

        return view('admin.riders.show', [
            'rider' => $rider->load('user:id,email'),
            'unsettled' => $unsettled,
            'cash' => (float) $unsettled->sum('cash_collected'),
            'fees' => (float) $unsettled->sum('rider_fee'),
            'history' => $rider->deliveries()->with('order:id,reference,name,total')->latest('id')->paginate(15),
        ]);
    }

    public function edit(Rider $rider)
    {
        return view('admin.riders.form', ['rider' => $rider->load('user:id,email')]);
    }

    public function update(Request $request, Rider $rider)
    {
        $data = $request->validate($this->rules($rider));

        DB::transaction(function () use ($data, $rider) {
            $rider->update($this->attributes($data));

            if ($rider->user) {
                if (filled($data['login_email'] ?? null)) {
                    $rider->user->email = $data['login_email'];
                }
                if (filled($data['login_password'] ?? null)) {
                    $rider->user->password = $data['login_password'];
                }
                $rider->user->name = $rider->name;
                $rider->user->save();
            } elseif (filled($data['login_email'] ?? null)) {
                $rider->update(['user_id' => $this->makeLogin($rider->name, $data['login_email'], $data['login_password'])->id]);
            }
        });

        ActivityLog::record('rider.updated', "Rider {$rider->name} updated", $rider);

        return redirect()->route('admin.riders.show', $rider)->with('status', 'Rider updated.');
    }

    public function destroy(Rider $rider)
    {
        if ($rider->deliveries()->whereIn('status', ['assigned', 'picked_up', 'failed'])->exists()) {
            return back()->with('error', 'This rider still has open deliveries. Reassign or finish them first.');
        }

        if ($rider->deliveries()->where('status', 'delivered')->whereNull('settled_at')->exists()) {
            return back()->with('error', 'Settle this rider first: there are unsettled deliveries.');
        }

        $rider->user?->forceFill(['is_active' => false])->save();
        $rider->update(['is_online' => false]);
        $rider->delete();

        ActivityLog::record('rider.deleted', "Rider {$rider->name} removed");

        return redirect()->route('admin.riders.index')->with('status', 'Rider removed. Their history is kept.');
    }

    /** Closes the books with a rider: every delivered, unsettled delivery is marked settled. */
    public function settle(Request $request, Rider $rider)
    {
        $count = 0;
        $cash = 0.0;
        $fees = 0.0;

        DB::transaction(function () use ($rider, $request, &$count, &$cash, &$fees) {
            $rows = Delivery::where('rider_id', $rider->id)->where('status', 'delivered')->whereNull('settled_at')->lockForUpdate()->get();
            $count = $rows->count();

            if ($count === 0) {
                return;
            }

            $cash = (float) $rows->sum('cash_collected');
            $fees = (float) $rows->sum('rider_fee');

            Delivery::whereIn('id', $rows->pluck('id'))->update(['settled_at' => now(), 'settled_by' => $request->user()->id]);
        });

        if ($count === 0) {
            return back()->with('error', 'Nothing to settle.');
        }

        ActivityLog::record('rider.settled', "Settled {$count} deliveries with {$rider->name}: cash " . money($cash) . ', fees ' . money($fees), $rider);

        return back()->with('status', "Settled {$count} deliveries.");
    }

    private function rules(?Rider $rider = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'vehicle' => ['required', Rule::in(array_keys(Rider::VEHICLES))],
            'vehicle_number' => ['nullable', 'string', 'max:30'],
            'fee_per_delivery' => ['required', 'numeric', 'min:0', 'max:100000', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
            'login_email' => ['nullable', 'email:rfc', 'max:150', Rule::unique('users', 'email')->ignore($rider?->user_id)],
            'login_password' => [$rider?->user_id ? 'nullable' : 'required_with:login_email', Password::defaults()],
        ];
    }

    private function attributes(array $data): array
    {
        return array_intersect_key($data, array_flip(['name', 'phone', 'vehicle', 'vehicle_number', 'fee_per_delivery', 'notes', 'is_active']));
    }

    private function makeLogin(string $name, string $email, string $password): User
    {
        $user = new User(['name' => $name, 'email' => $email]);
        $user->password = $password;
        $user->forceFill(['role' => UserRole::Rider, 'is_active' => true])->save();

        return $user;
    }
}
