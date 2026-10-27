<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NewsletterController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:150'],
            'website' => ['nullable', 'max:0'],
        ]);

        $subscriber = NewsletterSubscriber::firstOrNew(['email' => Str::lower($data['email'])]);

        if (! $subscriber->exists || $subscriber->unsubscribed_at) {
            $subscriber->fill([
                'locale' => app()->getLocale(),
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
            ]);
            $subscriber->token = $subscriber->token ?: Str::random(40);
            $subscriber->save();
        }

        // Same answer whether or not the email already existed, so addresses cannot be probed.
        $message = __('forms.newsletter.thanks');

        return $request->wantsJson()
            ? response()->json(['message' => $message], 201)
            : back()->with('status', $message);
    }

    public function unsubscribe(string $token)
    {
        NewsletterSubscriber::where('token', $token)
            ->whereNull('unsubscribed_at')
            ->update(['unsubscribed_at' => now()]);

        return redirect()->route('home')->with('status', __('forms.newsletter.unsubscribed'));
    }
}
