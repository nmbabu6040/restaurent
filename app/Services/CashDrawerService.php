<?php

namespace App\Services;

use App\Exceptions\PosException;
use App\Models\ActivityLog;
use App\Models\CashSession;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CashDrawerService
{
    public function current(): ?CashSession
    {
        return CashSession::open()->latest('id')->first();
    }

    public function open(float $opening, User $by): CashSession
    {
        return DB::transaction(function () use ($opening, $by) {
            if (CashSession::open()->lockForUpdate()->exists()) {
                throw new PosException('A shift is already open. Close it first.');
            }

            $session = CashSession::create(['opened_by' => $by->id, 'opened_at' => now(), 'opening_cash' => $opening]);
            ActivityLog::record('shift.opened', 'Opened the cash drawer with ' . money($opening), $session);

            return $session;
        });
    }

    /** Cash taken at the counter during this shift (voided bills already count as minus). */
    public function cashSales(CashSession $session): float
    {
        return round((float) OrderPayment::where('cash_session_id', $session->id)->where('method', 'cash')->sum('amount'), 2);
    }

    public function expected(CashSession $session): float
    {
        return round((float) $session->opening_cash + $this->cashSales($session), 2);
    }

    public function close(CashSession $session, float $counted, ?string $note, User $by): CashSession
    {
        return DB::transaction(function () use ($session, $counted, $note, $by) {
            $locked = CashSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ($locked->closed_at) {
                throw new PosException('This shift is already closed.');
            }

            $expected = $this->expected($locked);

            $locked->update([
                'closed_by' => $by->id,
                'closed_at' => now(),
                'expected_cash' => $expected,
                'counted_cash' => $counted,
                'note' => $note,
            ]);

            ActivityLog::record('shift.closed', 'Closed the cash drawer: expected ' . money($expected) . ', counted ' . money($counted), $locked);

            return $locked;
        });
    }
}
