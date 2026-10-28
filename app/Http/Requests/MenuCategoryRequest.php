<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MenuCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-menu') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => Str::slug($this->input('slug') ?: (string) $this->input('name_en'))]);
    }

    public function rules(): array
    {
        $id = $this->route('category')?->id;

        return [
            'name_en' => ['required', 'string', 'max:100'],
            'name_bn' => ['nullable', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:120', Rule::unique('menu_categories', 'slug')->ignore($id)],
            'description_en' => ['nullable', 'string', 'max:500'],
            'description_bn' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
