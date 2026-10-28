<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RestaurantSetting;
use Illuminate\Http\Request;
use App\Models\ActivityLog;

class SettingController extends Controller
{
    private const SOCIAL = ['nullable', 'max:255', 'regex:/^(#|https?:\/\/\S+)$/'];

    public static function fields(): array
    {
        return [
            'name' => ['label' => 'Restaurant name', 'type' => 'text', 'translatable' => true, 'required' => true, 'rules' => ['max:100']],
            'cuisine' => ['label' => 'Cuisine', 'type' => 'text', 'translatable' => true, 'required' => false, 'rules' => ['max:150']],
            'tagline' => ['label' => 'Tagline', 'type' => 'text', 'translatable' => true, 'required' => false, 'rules' => ['max:200']],
            'hero_title' => ['label' => 'Home headline', 'type' => 'text', 'translatable' => true, 'required' => false, 'rules' => ['max:200']],
            'hero_subtitle' => ['label' => 'Home subheading', 'type' => 'textarea', 'translatable' => true, 'required' => false, 'rules' => ['max:400']],
            'about_story' => ['label' => 'About page story', 'type' => 'textarea', 'translatable' => true, 'required' => false, 'rules' => ['max:1500']],
            'address' => ['label' => 'Address', 'type' => 'textarea', 'translatable' => true, 'required' => false, 'rules' => ['max:300']],
            'hours' => ['label' => 'Opening hours (one line per row)', 'type' => 'textarea', 'translatable' => true, 'required' => false, 'rules' => ['max:300']],
            'phone' => ['label' => 'Phone', 'type' => 'text', 'translatable' => false, 'required' => true, 'rules' => ['regex:/^[0-9+\-\s()]{7,20}$/']],
            'email' => ['label' => 'Email', 'type' => 'text', 'translatable' => false, 'required' => true, 'rules' => ['email:rfc', 'max:150']],
            'facebook' => ['label' => 'Facebook URL', 'type' => 'text', 'translatable' => false, 'required' => false, 'rules' => ['max:255', 'regex:/^(#|https?:\/\/\S+)$/']],
            'instagram' => ['label' => 'Instagram URL', 'type' => 'text', 'translatable' => false, 'required' => false, 'rules' => ['max:255', 'regex:/^(#|https?:\/\/\S+)$/']],
            'map_embed_url' => ['label' => 'Google Maps embed URL (starts with https://www.google.com/maps/embed)', 'type' => 'text', 'translatable' => false, 'required' => false, 'rules' => ['max:1000', 'starts_with:https://www.google.com/maps/embed']],
        ];
    }

    public function edit()
    {
        return view('admin.settings.edit', [
            'fields' => self::fields(),
            'values' => RestaurantSetting::cached(),
        ]);
    }

    public function update(Request $request)
    {
        $fields = self::fields();
        $rules = [];

        foreach ($fields as $key => $f) {
            $rules["{$key}_en"] = array_merge([$f['required'] ? 'required' : 'nullable', 'string'], $f['rules']);

            if ($f['translatable']) {
                $rules["{$key}_bn"] = array_merge(['nullable', 'string'], $f['rules']);
            }
        }

        $data = $request->validate($rules);

        foreach ($fields as $key => $f) {
            RestaurantSetting::updateOrCreate(
                ['key' => $key],
                [
                    'value_en' => $data["{$key}_en"] ?? null,
                    'value_bn' => $f['translatable'] ? ($data["{$key}_bn"] ?? null) : null,
                ],
            );
        }

        ActivityLog::record('settings.updated', 'Updated restaurant settings');

        return redirect()->route('admin.settings.edit')->with('status', 'Settings saved.');
    }
}
