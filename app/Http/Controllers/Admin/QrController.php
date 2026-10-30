<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\QrService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QrController extends Controller
{
    private const TARGETS = [
        'menu' => ['Menu', 'menu.index', 'Scan to see our menu'],
        'home' => ['Home page', 'home', 'Scan to visit our website'],
        'reserve' => ['Reservations', 'reservations.create', 'Scan to book a table'],
        'contact' => ['Contact', 'contact.create', 'Scan to contact us'],
        'track' => ['Order tracking', 'orders.track', 'Scan to track your order'],
    ];

    public function __construct(private QrService $qr) {}

    public function index(Request $request)
    {
        return view('admin.qr.index', $this->prepare($request) + ['targets' => self::TARGETS]);
    }

    public function download(Request $request)
    {
        $data = $this->prepare($request, 600);

        return response($data['svg'], 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="qr-' . $data['target'] . '.svg"',
        ]);
    }

    public function print(Request $request)
    {
        return view('admin.qr.print', $this->prepare($request, 700) + ['autoprint' => $request->boolean('autoprint')]);
    }

    private function prepare(Request $request, int $size = 320): array
    {
        $data = $request->validate([
            'target' => ['nullable', Rule::in([...array_keys(self::TARGETS), 'custom'])],
            'path' => ['nullable', 'required_if:target,custom', 'regex:#^/(?!/)[A-Za-z0-9/_\-.]{0,100}$#'],
            'label' => ['nullable', 'string', 'max:80'],
        ]);

        $target = $data['target'] ?? 'menu';
        $url = $target === 'custom' ? url($data['path']) : route(self::TARGETS[$target][1]);

        return [
            'target' => $target,
            'url' => $url,
            'label' => $data['label'] ?? ($target === 'custom' ? 'Scan me' : self::TARGETS[$target][2]),
            'path' => $data['path'] ?? '',
            'svg' => $this->qr->svg($url, $size),
        ];
    }
}
