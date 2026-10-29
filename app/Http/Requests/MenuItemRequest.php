<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-menu') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => Str::slug($this->input('slug') ?: (string) $this->input('name_en'))]);

        // Ignore completely empty rows (an untouched template row).
        $keep = [
            'variations' => fn($r) => filled($r['name_en'] ?? null) || filled($r['price'] ?? null),
            'addons' => fn($r) => filled($r['name_en'] ?? null) || filled($r['price'] ?? null),
            'recipe' => fn($r) => filled($r['ingredient_id'] ?? null) || filled($r['quantity'] ?? null),
        ];

        foreach ($keep as $key => $isUsed) {
            $rows = $this->input($key);

            if (is_array($rows)) {
                $this->merge([$key => collect($rows)->filter(fn($r) => is_array($r) && $isUsed($r))->values()->all()]);
            }
        }
    }

    public function rules(): array
    {
        $id = $this->route('item')?->id;
        $money = ['nullable', 'numeric', 'min:0', 'max:99999999', 'decimal:0,2'];

        return [
            'menu_category_id' => ['required', Rule::exists('menu_categories', 'id')->whereNull('deleted_at')],
            'name_en' => ['required', 'string', 'max:150'],
            'name_bn' => ['nullable', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:160', Rule::unique('menu_items', 'slug')->ignore($id)],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'description_bn' => ['nullable', 'string', 'max:2000'],
            'ingredients_en' => ['nullable', 'string', 'max:1000'],
            'ingredients_bn' => ['nullable', 'string', 'max:1000'],
            'allergens_en' => ['nullable', 'string', 'max:1000'],
            'allergens_bn' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999', 'decimal:0,2'],
            'dietary' => ['nullable', 'array'],
            'dietary.*' => ['nullable', Rule::in(['vegetarian', 'halal', 'spicy'])],
            'is_available' => ['required', 'boolean'],
            'is_featured' => ['required', 'boolean'],
            'is_special' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
            'remove_image' => ['nullable', 'boolean'],

            'stock_mode' => ['nullable', Rule::in(['none', 'quantity', 'recipe'])],
            'stock_qty' => ['nullable', 'integer', 'min:0', 'max:1000000', 'required_if:stock_mode,quantity'],
            'cost_price' => $money,
            'recipe_form' => ['nullable', 'boolean'],

            'variations' => ['nullable', 'array', 'max:10'],
            'variations.*.id' => ['nullable', 'integer'],
            'variations.*.name_en' => ['required', 'string', 'max:100'],
            'variations.*.name_bn' => ['nullable', 'string', 'max:100'],
            'variations.*.price' => ['required', 'numeric', 'min:0', 'max:99999999', 'decimal:0,2'],
            'variations.*.portion_factor' => ['nullable', 'numeric', 'gt:0', 'max:100', 'decimal:0,2'],
            'variations.*.cost_price' => $money,
            'variations.*.is_active' => ['nullable', 'boolean'],

            'addons' => ['nullable', 'array', 'max:20'],
            'addons.*.id' => ['nullable', 'integer'],
            'addons.*.name_en' => ['required', 'string', 'max:100'],
            'addons.*.name_bn' => ['nullable', 'string', 'max:100'],
            'addons.*.price' => ['required', 'numeric', 'min:0', 'max:99999999', 'decimal:0,2'],
            'addons.*.cost_price' => $money,
            'addons.*.is_active' => ['nullable', 'boolean'],

            'recipe' => ['nullable', 'array', 'max:40'],
            'recipe.*.ingredient_id' => ['required', 'integer', 'distinct', Rule::exists('ingredients', 'id')->whereNull('deleted_at')],
            'recipe.*.quantity' => ['required', 'numeric', 'gt:0', 'max:1000000', 'decimal:0,3'],
        ];
    }
}
