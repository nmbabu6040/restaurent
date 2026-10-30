@php
    $editing = $record->exists;
    $img = $cfg['image'] ?? null;
@endphp

<x-admin.layout :title="($editing ? 'Edit ' : 'New ') . $cfg['singular']">
    <form method="POST" enctype="multipart/form-data"
        action="{{ $editing ? route('admin.content.update', [$key, $record->id]) : route('admin.content.store', $key) }}"
        class="card mt-6 space-y-5 p-6 sm:p-8">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="grid gap-5 md:grid-cols-2">
            @foreach ($cfg['fields'] as $f)
                @php
                    $value = $record->{$f['name']} ?? ($f['default'] ?? null);
                    if ($value instanceof \DateTimeInterface) {
                        $value = $value->format($f['type'] === 'date' ? 'Y-m-d' : 'Y-m-d\TH:i');
                    }
                @endphp

                @if ($f['type'] === 'checkbox')
                    <div class="md:col-span-2">
                        <input type="hidden" name="{{ $f['name'] }}" value="0">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="{{ $f['name'] }}" value="1" class="accent-navy"
                                @checked(old($f['name'], $value))> {{ $f['label'] }}
                        </label>
                    </div>
                @elseif ($f['type'] === 'select')
                    <div>
                        <label for="{{ $f['name'] }}" class="block text-sm font-medium text-navy">{{ $f['label'] }}
                            @if ($f['required'])
                                <span aria-hidden="true" class="text-terracotta-dark"> *</span>
                            @endif
                        </label>
                        <select id="{{ $f['name'] }}" name="{{ $f['name'] }}" @required($f['required'])
                            aria-describedby="{{ $f['name'] }}-error"
                            class="mt-1 w-full rounded-lg border border-navy/20 bg-surface px-4 py-2.5 text-sm focus:border-terracotta">
                            @foreach ($f['options'] as $optValue => $optLabel)
                                <option value="{{ $optValue }}" @selected((string) old($f['name'], $value) === (string) $optValue)>{{ $optLabel }}
                                </option>
                            @endforeach
                        </select>
                        <p id="{{ $f['name'] }}-error" class="mt-1 text-sm text-red-700">
                            {{ $errors->first($f['name']) }}</p>
                    </div>
                @elseif ($f['type'] === 'textarea')
                    <x-form.textarea :name="$f['name']" :label="$f['label']" :required="$f['required']" :value="$value"
                        rows="3" />
                @else
                    <x-form.field :name="$f['name']" :type="$f['type']" :label="$f['label']" :required="$f['required']"
                        :value="$value" :min="$f['min'] ?? null" :max="$f['max'] ?? null" :step="$f['step'] ?? null" />
                @endif
            @endforeach
        </div>

        @if ($img)
            <div>
                <label for="image" class="block text-sm font-medium text-navy">{{ $img['label'] }}@if ($img['required'] && !$editing)
                        <span aria-hidden="true" class="text-terracotta-dark"> *</span>
                    @endif
                </label>
                @if ($editing && $record->{$img['accessor']})
                    <img src="{{ $record->{$img['accessor']} }}" alt="Current image" width="160" height="120"
                        class="mt-2 h-24 w-32 rounded-lg object-cover">
                    @unless ($img['required'])
                        <input type="hidden" name="remove_image" value="0">
                        <label class="mt-2 flex items-center gap-2 text-sm">
                            <input type="checkbox" name="remove_image" value="1" class="accent-navy"> Remove current
                            image
                        </label>
                    @endunless
                @endif
                <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp"
                    aria-describedby="image-error"
                    class="mt-2 block w-full text-sm file:mr-3 file:rounded-full file:border-0 file:bg-navy file:px-4 file:py-2 file:text-white">
                <p id="image-error" class="mt-1 text-sm text-red-700">{{ $errors->first('image') }}</p>
            </div>
        @endif

        <div class="flex gap-3">
            <button type="submit" class="btn-primary">Save</button>
            <a href="{{ route('admin.content.index', $key) }}" class="btn border border-navy/20 text-navy">Cancel</a>
        </div>
    </form>
</x-admin.layout>
