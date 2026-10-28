<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireUnpaidOrders extends Command
{
    protected $signature = 'orders:expire-unpaid';

    protected $description = 'Cancel online-payment orders that were never paid';

    public function handle(): int
    {
        $cutoff = now()->subMinutes(config('restaurant.ordering.unpaid_expiry_minutes'));
        $count = 0;

        Order::where('payment_method', 'sslcommerz')
            ->where('payment_status', 'unpaid')
            ->where('status', 'pending')
            ->where('created_at', '<', $cutoff)
            ->chunkById(100, function ($orders) use (&$count) {
                foreach ($orders as $order) {
                    DB::transaction(function () use ($order, &$count) {
                        $locked = Order::whereKey($order->id)->lockForUpdate()->first();

                        if ($locked && $locked->payment_status === 'unpaid' && $locked->status === OrderStatus::Pending) {
                            $locked->markCancelled();
                            $count++;
                        }
                    });
                }
            });

        $this->info("Cancelled {$count} unpaid online order(s).");

        return self::SUCCESS;
    }
}
