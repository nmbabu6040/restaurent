<x-admin.layout title="Settings">
    <p class="mt-2 text-sm">These values appear across the public website. Leave the Bangla box empty to show the English
        text.</p>

    <form method="POST" action="{{ route('admin.settings.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('PUT')

        @foreach ($fields as $key => $f)
            <fieldset class="card p-6">
                <legend class="px-1 font-heading text-lg text-navy">{{ $f['label'] }}</legend>

                <div class="mt-3 grid gap-4 {{ $f['translatable'] ? 'md:grid-cols-2' : '' }}">
                    @foreach ($f['translatable'] ? ['en' => 'English', 'bn' => 'বাংলা'] : ['en' => $f['label']] as $lang => $langLabel)
                        @if ($f['type'] === 'textarea')
                            <x-form.textarea :name="$key . '_' . $lang" :label="$langLabel" :required="$f['required'] && $lang === 'en'" :value="$values[$key][$lang] ?? ''"
                                rows="3" />
                        @else
                            <x-form.field :name="$key . '_' . $lang" :label="$langLabel" :required="$f['required'] && $lang === 'en'" :value="$values[$key][$lang] ?? ''" />
                        @endif
                    @endforeach
                </div>
            </fieldset>
        @endforeach

        <button type="submit" class="btn-primary">Save settings</button>
    </form>
</x-admin.layout>
