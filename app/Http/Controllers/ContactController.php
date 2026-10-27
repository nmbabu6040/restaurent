<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Models\ContactMessage;

class ContactController extends Controller
{
    public function create()
    {
        return view('contact.create');
    }

    public function store(ContactRequest $request)
    {
        ContactMessage::create(array_merge(
            $request->safe()->only(['name', 'email', 'phone', 'subject', 'message']),
            ['ip_address' => $request->ip(), 'locale' => app()->getLocale()],
        ));

        $message = __('forms.contact.sent');

        return $request->wantsJson()
            ? response()->json(['message' => $message], 201)
            : redirect()->route('contact.create')->with('status', $message);
    }
}
