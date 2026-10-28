<x-admin.layout title="Message">
    <div class="card mt-6 p-6 sm:p-8">
        <dl class="grid gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="font-semibold text-navy">From</dt>
                <dd>{{ $message->name }}</dd>
            </div>
            <div>
                <dt class="font-semibold text-navy">Email</dt>
                <dd><a href="mailto:{{ $message->email }}" class="hover:text-terracotta-dark">{{ $message->email }}</a>
                </dd>
            </div>
            <div>
                <dt class="font-semibold text-navy">Phone</dt>
                <dd>{{ $message->phone ?: '—' }}</dd>
            </div>
            <div>
                <dt class="font-semibold text-navy">Received</dt>
                <dd>{{ $message->created_at->format('d M Y, g:i A') }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="font-semibold text-navy">Subject</dt>
                <dd>{{ $message->subject ?: '—' }}</dd>
            </div>
        </dl>
        <p class="mt-6 whitespace-pre-line rounded-lg bg-warm p-4 text-sm">{{ $message->message }}</p>

        <div class="mt-6 flex flex-wrap gap-3">
            <a href="mailto:{{ $message->email }}?subject={{ rawurlencode('Re: ' . ($message->subject ?: 'Your message')) }}"
                class="btn-primary">Reply by email</a>
            <a href="{{ route('admin.messages.index') }}" class="btn border border-navy/20 text-navy">Back</a>
            <form method="POST" action="{{ route('admin.messages.destroy', $message) }}"
                data-confirm="Delete this message permanently?">
                @csrf @method('DELETE')
                <button type="submit" class="btn border border-red-700 text-red-700 hover:bg-red-50">Delete</button>
            </form>
        </div>
    </div>
</x-admin.layout>
