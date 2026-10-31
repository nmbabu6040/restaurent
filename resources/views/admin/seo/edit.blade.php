@php $v = fn (string $key, string $lang = 'en') => $values[$key][$lang] ?? ''; @endphp

<x-admin.layout title="SEO">
    <form method="POST" action="{{ route('admin.seo.update') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
        @csrf @method('PUT')

        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ($health as $label => $count)
                <x-admin.stat :label="$label" :value="number_format($count)" icon="search" :tone="$count ? 'amber' : 'emerald'" :sub="$count ? 'Fix these for better search results' : 'All good'" />
            @endforeach
        </div>

        <fieldset class="card p-6">
            <legend class="px-1 font-heading text-lg text-navy">Whole site</legend>
            <div class="mt-3 grid gap-5 md:grid-cols-2">
                <x-form.textarea name="seo_default_description_en"
                    label="Default description (English), used when a page has none" :value="$v('seo_default_description')" rows="3"
                    maxlength="300" />
                <x-form.textarea name="seo_default_description_bn" label="Default description (বাংলা)" :value="$v('seo_default_description', 'bn')"
                    rows="3" maxlength="300" />
                <div>
                    <label for="seo_default_image" class="block text-sm font-medium text-navy">Default link-preview
                        image (shown when someone shares your site on Facebook, WhatsApp...)</label>
                    @if ($img = setting_image('seo_default_image'))
                        <img src="{{ $img }}" alt="Current preview image" class="mt-2 h-20 w-auto rounded">
                        <input type="hidden" name="remove_seo_default_image" value="0">
                        <label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox"
                                name="remove_seo_default_image" value="1" class="accent-navy"> Remove</label>
                    @endif
                    <input id="seo_default_image" name="seo_default_image" type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="mt-2 block w-full text-sm file:mr-3 file:rounded-full file:border-0 file:bg-navy file:px-4 file:py-2 file:text-white">
                    <p class="mt-1 text-xs">It is cropped to 1200 x 630. Use a wide, simple photo.</p>
                    <p class="mt-1 text-sm text-red-700">{{ $errors->first('seo_default_image') }}</p>
                </div>
                <div class="space-y-4">
                    <x-form.field name="seo_google_verification" label="Google Search Console verification code"
                        :value="$v('seo_google_verification')" maxlength="120" />
                    <x-form.field name="seo_bing_verification" label="Bing Webmaster verification code"
                        :value="$v('seo_bing_verification')" maxlength="120" />
                </div>
            </div>
            <input type="hidden" name="seo_noindex_all" value="0">
            <label class="mt-4 flex items-center gap-2 text-sm font-semibold"><input type="checkbox"
                    name="seo_noindex_all" value="1" class="accent-navy" @checked(old('seo_noindex_all', $v('seo_noindex_all')) == '1')> Hide the
                whole site from Google and other search engines</label>
            <p class="text-xs">Use this while the site is being built. Switch it off before launch.</p>
        </fieldset>

        @foreach ($pages as $key => $label)
            <fieldset class="card p-6">
                <legend class="px-1 font-heading text-lg text-navy">{{ $label }}</legend>
                <div class="mt-3 grid gap-5 md:grid-cols-2">
                    <x-form.field :name="'seo_' . $key . '_title_en'" label="Search title (English)" :value="$v('seo_' . $key . '_title')" maxlength="160" />
                    <x-form.field :name="'seo_' . $key . '_title_bn'" label="Search title (বাংলা)" :value="$v('seo_' . $key . '_title', 'bn')" maxlength="160" />
                    <x-form.textarea :name="'seo_' . $key . '_description_en'" label="Search description (English, about 150 characters)"
                        :value="$v('seo_' . $key . '_description')" rows="2" maxlength="300" />
                    <x-form.textarea :name="'seo_' . $key . '_description_bn'" label="Search description (বাংলা)" :value="$v('seo_' . $key . '_description', 'bn')"
                        rows="2" maxlength="300" />
                </div>
            </fieldset>
        @endforeach

        <p class="text-sm">Dishes and custom pages have their own SEO fields on their edit forms. The sitemap and
            robots.txt are made automatically.</p>
        <button type="submit" class="btn-primary">Save</button>
    </form>
</x-admin.layout>
