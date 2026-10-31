<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CmsPageController extends Controller
{
    public function index()
    {
        return view('admin.pages.index', ['pages' => Page::orderBy('sort_order')->orderBy('id')->paginate(20)]);
    }

    public function create()
    {
        return view('admin.pages.form', ['page' => new Page(['is_active' => true, 'sort_order' => 0])]);
    }

    public function store(Request $request)
    {
        $request->merge(['slug' => Str::slug($request->input('slug') ?: (string) $request->input('title_en'))]);

        $page = Page::create($this->clean($request->validate($this->rules(null))));

        return redirect()->route('admin.pages.edit', $page)->with('status', 'Page created.');
    }

    public function edit(Page $page)
    {
        return view('admin.pages.form', compact('page'));
    }

    public function update(Request $request, Page $page)
    {
        // The address of Privacy Policy and Terms never changes.
        $request->merge(['slug' => $page->is_system ? $page->slug : Str::slug($request->input('slug') ?: (string) $request->input('title_en'))]);

        $page->update($this->clean($request->validate($this->rules($page))));

        return redirect()->route('admin.pages.edit', $page)->with('status', 'Page saved.');
    }

    public function destroy(Page $page)
    {
        if ($page->is_system) {
            return back()->with('error', 'Privacy Policy and Terms are built in. You can edit them, or hide them, but not delete them.');
        }

        $page->delete();

        return redirect()->route('admin.pages.index')->with('status', 'Page deleted.');
    }

    private function rules(?Page $page): array
    {
        return [
            'title_en' => ['required', 'string', 'max:150'],
            'title_bn' => ['nullable', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('pages', 'slug')->ignore($page?->id)],
            'body_en' => ['nullable', 'string', 'max:60000'],
            'body_bn' => ['nullable', 'string', 'max:60000'],
            'meta_title_en' => ['nullable', 'string', 'max:160'],
            'meta_title_bn' => ['nullable', 'string', 'max:160'],
            'meta_description_en' => ['nullable', 'string', 'max:300'],
            'meta_description_bn' => ['nullable', 'string', 'max:300'],
            'show_in_header' => ['required', 'boolean'],
            'show_in_footer' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    private function clean(array $data): array
    {
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
