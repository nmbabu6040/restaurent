<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ContactMessage;

class ContactMessageController extends Controller
{
    public function index()
    {
        return view('admin.messages.index', [
            'messages' => ContactMessage::latest()->paginate(15),
        ]);
    }

    public function show(ContactMessage $message)
    {
        if (! $message->read_at) {
            $message->update(['read_at' => now()]);
        }

        return view('admin.messages.show', compact('message'));
    }

    public function destroy(ContactMessage $message)
    {
        $message->delete();
        ActivityLog::record('message.deleted', "Contact message #{$message->id} deleted");

        return redirect()->route('admin.messages.index')->with('status', 'Message deleted.');
    }
}
