<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubscriberController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->validate([
            'status' => ['nullable', 'in:active,unsubscribed'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $subscribers = NewsletterSubscriber::query()
            ->when(($f['status'] ?? null) === 'active', fn($q) => $q->whereNull('unsubscribed_at'))
            ->when(($f['status'] ?? null) === 'unsubscribed', fn($q) => $q->whereNotNull('unsubscribed_at'))
            ->when($f['q'] ?? null, fn($q, $t) => $q->where('email', 'like', '%' . addcslashes($t, '%_\\') . '%'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.subscribers.index', compact('subscribers'));
    }

    public function destroy(NewsletterSubscriber $subscriber)
    {
        $subscriber->delete();
        ActivityLog::record('subscriber.deleted', "Subscriber #{$subscriber->id} deleted");

        return back()->with('status', 'Subscriber removed.');
    }

    public function export(): StreamedResponse
    {
        ActivityLog::record('subscriber.exported', 'Exported subscriber list');

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['email', 'language', 'subscribed_at'], ',', '"', '');

            NewsletterSubscriber::whereNull('unsubscribed_at')->orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    // Guard against spreadsheet formula injection.
                    $email = preg_match('/^[=+\-@]/', $row->email) ? "'" . $row->email : $row->email;
                    fputcsv($out, [$email, $row->locale, $row->subscribed_at?->toDateTimeString()], ',', '"', '');
                }
            });

            fclose($out);
        }, 'subscribers-' . now()->format('Ymd') . '.csv', ['Content-Type' => 'text/csv']);
    }
}
