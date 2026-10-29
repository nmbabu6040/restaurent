<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\RestaurantSetting;
use App\Services\ImageService;
use App\Support\Color;
use App\Support\Sections;
use Illuminate\Http\Request;

class AppearanceController extends Controller
{
    /** key => [has English + Bangla versions, validation rules] */
    private const TEXT = [
        'author_name' => [false, ['nullable', 'string', 'max:100']],
        'author_url' => [false, ['nullable', 'url:http,https', 'max:255']],
        'owner_name' => [false, ['nullable', 'string', 'max:100']],
        'owner_title' => [true, ['nullable', 'string', 'max:100']],
        'owner_bio' => [true, ['nullable', 'string', 'max:800']],
        'whatsapp_number' => [false, ['nullable', 'regex:/^\+?[0-9\s\-()]{8,20}$/']],
        'whatsapp_message' => [true, ['nullable', 'string', 'max:200']],
        'maintenance_message' => [true, ['nullable', 'string', 'max:500']],
    ];

    private const FLAGS = ['whatsapp_enabled', 'back_to_top_enabled', 'maintenance_enabled'];

    private const IMAGES = [
        'logo_header' => ['label' => 'Header logo (transparent PNG works best)', 'icon' => false],
        'logo_footer' => ['label' => 'Footer logo (empty = header logo is used)', 'icon' => false],
        'favicon' => ['label' => 'Site icon / favicon (square image)', 'icon' => true],
        'owner_photo' => ['label' => 'Owner photo (shown on the About page)', 'icon' => false],
    ];

    public function edit()
    {
        return view('admin.appearance.edit', [
            'values' => RestaurantSetting::cached(),
            'sections' => Sections::all(),
            'images' => self::IMAGES,
        ]);
    }

    public function update(Request $request, ImageService $images)
    {
        $rules = [];

        foreach (self::TEXT as $key => [$translatable, $rule]) {
            if ($translatable) {
                $rules[$key . '_en'] = $rule;
                $rules[$key . '_bn'] = $rule;
            } else {
                $rules[$key] = $rule;
            }
        }

        foreach (self::FLAGS as $flag) {
            $rules[$flag] = ['required', 'boolean'];
        }

        foreach (self::IMAGES as $key => $cfg) {
            $rules[$key] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'];
            $rules['remove_' . $key] = ['nullable', 'boolean'];
        }

        foreach (Sections::all() as $key => $section) {
            $rules['color_' . $key] = [
                'nullable',
                'regex:/^#[0-9A-Fa-f]{6}$/',
                function (string $attribute, mixed $value, \Closure $fail) use ($section) {
                    if ($value && Color::bestContrast($value) < 4.5) {
                        $fail("{$section['label']}: this color is too mid-tone, so text on it would be hard to read. Pick a lighter or a darker shade.");
                    }
                },
            ];
            $rules['reset_' . $key] = ['nullable', 'boolean'];
        }

        $data = $request->validate($rules);
        $stored = RestaurantSetting::cached();
        $wasInMaintenance = ($stored['maintenance_enabled']['en'] ?? '0') === '1';

        $put = fn(string $key, ?string $en, ?string $bn = null) => RestaurantSetting::updateOrCreate(
            ['key' => $key],
            ['value_en' => $en, 'value_bn' => $bn],
        );

        foreach (self::TEXT as $key => [$translatable, $rule]) {
            if ($translatable) {
                $put($key, $data[$key . '_en'] ?? null, $data[$key . '_bn'] ?? null);
            } elseif ($key === 'whatsapp_number') {
                $put($key, $this->normalizeNumber($data[$key] ?? null));
            } else {
                $put($key, $data[$key] ?? null);
            }
        }

        foreach (self::FLAGS as $flag) {
            $put($flag, $request->boolean($flag) ? '1' : '0');
        }

        foreach (self::IMAGES as $key => $cfg) {
            $current = $stored[$key]['en'] ?? null;

            if ($request->hasFile($key)) {
                $images->delete($current);
                $path = $cfg['icon']
                    ? $images->storeIcon($request->file($key), 'branding')
                    : $images->store($request->file($key), 'branding');
                $put($key, $path);
            } elseif ($request->boolean('remove_' . $key)) {
                $images->delete($current);
                $put($key, null);
            }
        }

        foreach (Sections::all() as $key => $section) {
            $value = isset($data['color_' . $key]) ? strtoupper($data['color_' . $key]) : null;
            $isDefault = $value === null || $value === strtoupper($section['default']);

            $put('color_' . $key, ($request->boolean('reset_' . $key) || $isDefault) ? null : $value);
        }

        ActivityLog::record('appearance.updated', 'Updated appearance and site settings');

        $isInMaintenance = $request->boolean('maintenance_enabled');
        if ($isInMaintenance !== $wasInMaintenance) {
            ActivityLog::record($isInMaintenance ? 'maintenance.on' : 'maintenance.off', 'Maintenance mode ' . ($isInMaintenance ? 'switched on' : 'switched off'));
        }

        return redirect()->route('admin.appearance.edit')->with('status', 'Appearance saved.');
    }

    /** Digits only; a Bangladeshi mobile number without the country code (01XXXXXXXXX) gets 88 in front. */
    private function normalizeNumber(?string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $raw);

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '01')) {
            $digits = '88' . $digits;
        }

        return $digits;
    }
}
