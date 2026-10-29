@php
    $v = fn(string $key, string $lang = 'en') => $values[$key][$lang] ?? '';
    $flag = fn(string $key, bool $default = false) => old($key, $values[$key]['en'] ?? ($default ? '1' : '0')) == '1';
@endphp

<x-admin.layout title="Appearance & site">
    <form method="POST" action="{{ route('admin.appearance.update') }}" enctype="multipart/form-data"
        class="mt-6 space-y-6">
        @csrf
        @method('PUT')

        <fieldset class="card p-6">
            <legend class="px-1 font-heading text-lg text-navy">Logos and icon</legend>
            <div class="mt-3 grid gap-6 md:grid-cols-2">
                @foreach ($images as $key => $cfg)
                    @php $url = setting_image($key); @endphp
                    <div>
                        <label for="{{ $key }}"
                            class="block text-sm font-medium text-navy">{{ $cfg['label'] }}</label>
                        @if ($url)
                            <img src="{{ $url }}" alt="Current {{ $cfg['label'] }}"
                                class="mt-2 h-16 w-auto max-w-full rounded bg-[repeating-conic-gradient(#ddd_0%_25%,#fff_0%_50%)] bg-[length:16px_16px] object-contain p-1">
                            <input type="hidden" name="remove_{{ $key }}" value="0">
                            <label class="mt-2 flex items-center gap-2 text-sm">
                                <input type="checkbox" name="remove_{{ $key }}" value="1"
                                    class="accent-navy"> Remove
                            </label>
                        @endif
                        <input id="{{ $key }}" name="{{ $key }}" type="file"
                            accept="image/jpeg,image/png,image/webp" aria-describedby="{{ $key }}-error"
                            class="mt-2 block w-full text-sm file:mr-3 file:rounded-full file:border-0 file:bg-navy file:px-4 file:py-2 file:text-white">
                        <p id="{{ $key }}-error" class="mt-1 text-sm text-red-700">{{ $errors->first($key) }}
                        </p>
                    </div>
                @endforeach
            </div>
            <p class="mt-3 text-xs">JPG, PNG or WebP, up to 2 MB. Logos keep their transparency.</p>
        </fieldset>

        <fieldset class="card p-6">
            <legend class="px-1 font-heading text-lg text-navy">Author and owner</legend>
            <div class="mt-3 grid gap-5 md:grid-cols-2">
                <x-form.field name="author_name" label="Website author / developer (footer credit and meta tag)"
                    :value="$v('author_name')" maxlength="100" />
                <x-form.field name="author_url" type="url" label="Author link (optional, https://...)"
                    :value="$v('author_url')" maxlength="255" />
                <x-form.field name="owner_name" label="Owner / chef name (About page)" :value="$v('owner_name')"
                    maxlength="100" />
                <div></div>
                <x-form.field name="owner_title_en" label="Owner title (English)" :value="$v('owner_title')" maxlength="100" />
                <x-form.field name="owner_title_bn" label="Owner title (বাংলা)" :value="$v('owner_title', 'bn')" maxlength="100" />
                <x-form.textarea name="owner_bio_en" label="Owner bio (English)" :value="$v('owner_bio')" rows="3"
                    maxlength="800" />
                <x-form.textarea name="owner_bio_bn" label="Owner bio (বাংলা)" :value="$v('owner_bio', 'bn')" rows="3"
                    maxlength="800" />
            </div>
        </fieldset>

        <fieldset class="card p-6">
            <legend class="px-1 font-heading text-lg text-navy">Floating buttons</legend>
            <div class="mt-3 space-y-4">
                <input type="hidden" name="back_to_top_enabled" value="0">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="back_to_top_enabled" value="1" class="accent-navy"
                        @checked($flag('back_to_top_enabled', true))> Show "Back to top" button
                </label>

                <input type="hidden" name="whatsapp_enabled" value="0">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="whatsapp_enabled" value="1" class="accent-navy"
                        @checked($flag('whatsapp_enabled'))> Show WhatsApp chat button
                </label>
                <div class="grid gap-5 md:grid-cols-3">
                    <x-form.field name="whatsapp_number" label="WhatsApp number (01XXXXXXXXX or with country code)"
                        :value="$v('whatsapp_number')" maxlength="20" />
                    <x-form.field name="whatsapp_message_en" label="Greeting message (English)" :value="$v('whatsapp_message')"
                        maxlength="200" />
                    <x-form.field name="whatsapp_message_bn" label="Greeting message (বাংলা)" :value="$v('whatsapp_message', 'bn')"
                        maxlength="200" />
                </div>
            </div>
        </fieldset>

        <fieldset class="card p-6">
            <legend class="px-1 font-heading text-lg text-navy">Section background colors</legend>
            <p class="text-sm">Text color is chosen automatically so it stays readable. Colors that would make text hard
                to read are not accepted.</p>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                @foreach ($sections as $key => $section)
                    @php
                        $custom = $v('color_' . $key);
                        $current = old('color_' . $key, $custom ?: $section['default']);
                    @endphp
                    <div class="flex flex-wrap items-center gap-3 rounded-lg border border-navy/10 p-3">
                        <input type="color" id="color_{{ $key }}" name="color_{{ $key }}"
                            value="{{ $current }}" data-reset="reset_{{ $key }}"
                            class="h-10 w-14 cursor-pointer rounded border border-navy/20 bg-transparent p-0.5">
                        <label for="color_{{ $key }}"
                            class="min-w-0 flex-1 text-sm font-medium text-navy">{{ $section['label'] }}</label>
                        <label class="flex items-center gap-1 text-xs">
                            <input type="checkbox" id="reset_{{ $key }}" name="reset_{{ $key }}"
                                value="1" class="accent-navy" @checked(!$custom)> Default
                            ({{ $section['default'] }})
                        </label>
                        <p class="w-full text-sm text-red-700">{{ $errors->first('color_' . $key) }}</p>
                    </div>
                @endforeach
            </div>
        </fieldset>

        <fieldset class="card border-2 border-red-700/30 p-6">
            <legend class="px-1 font-heading text-lg text-navy">Maintenance mode</legend>
            <input type="hidden" name="maintenance_enabled" value="0">
            <label class="flex items-center gap-2 text-sm font-semibold">
                <input type="checkbox" name="maintenance_enabled" value="1" class="accent-navy"
                    @checked($flag('maintenance_enabled'))> Switch the public website to maintenance mode
            </label>
            <p class="mt-1 text-sm">Visitors see a "back soon" page (HTTP 503). Everyone logged in to the admin, the
                admin login, and online-payment confirmations keep working.</p>
            <div class="mt-4 grid gap-5 md:grid-cols-2">
                <x-form.textarea name="maintenance_message_en" label="Message (English, optional)" :value="$v('maintenance_message')"
                    rows="3" maxlength="500" />
                <x-form.textarea name="maintenance_message_bn" label="Message (বাংলা, optional)" :value="$v('maintenance_message', 'bn')"
                    rows="3" maxlength="500" />
            </div>
        </fieldset>

        <button type="submit" class="btn-primary">Save</button>
    </form>
</x-admin.layout>
