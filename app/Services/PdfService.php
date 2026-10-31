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
        return Pdf::loadView($view, $data)->setPaper('a4', $orientation)->setOption(['defaultFont' => 'DejaVu Sans']);
    }
}
