<?php

namespace App\Services;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfService
{
    public function invoice(Order $order)
    {
        return $this->make('pdf.invoice', ['order' => $order->loadMissing('items', 'delivery.rider')]);
    }

    public function challan(Order $order)
    {
        return $this->make('pdf.challan', ['order' => $order->loadMissing('items', 'delivery.rider')]);
    }

    public function table(array $data, bool $landscape)
    {
        return $this->make('pdf.table', $data, $landscape ? 'landscape' : 'portrait');
    }

    private function make(string $view, array $data, string $orientation = 'portrait')
    {
        $this->allowMoreMemory();

        return Pdf::loadView($view, $data)->setPaper('a4', $orientation)->setOption(['defaultFont' => 'DejaVu Sans']);
    }

    /** Building a PDF needs far more memory than a normal page. Raise a low limit (128M is the usual default). */
    private function allowMoreMemory(): void
    {
        $limit = (string) ini_get('memory_limit');

        if ($limit === '-1') {
            return;
        }

        $bytes = (int) $limit * match (strtoupper(substr($limit, -1))) {
            'G' => 1073741824,
            'M' => 1048576,
            'K' => 1024,
            default => 1,
        };

        if ($bytes < 268435456) {
            @ini_set('memory_limit', '256M');
        }
    }
}
