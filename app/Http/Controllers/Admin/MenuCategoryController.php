<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MenuCategoryRequest;
use App\Models\MenuCategory;

class MenuCategoryController extends Controller
{
    public function index()
    {
        return view('admin.categories.index', [
            'categories' => MenuCategory::withCount('items')->ordered()->paginate(20),
        ]);
    }

    public function create()
    {
        return view('admin.categories.form', [
            'category' => new MenuCategory(['sort_order' => 0, 'is_active' => true]),
        ]);
    }

    public function store(MenuCategoryRequest $request)
    {
        MenuCategory::create($request->validated());

        return redirect()->route('admin.categories.index')->with('status', 'Category created.');
    }

    public function edit(MenuCategory $category)
    {
        return view('admin.categories.form', compact('category'));
    }

    public function update(MenuCategoryRequest $request, MenuCategory $category)
    {
        $category->update($request->validated());

        return redirect()->route('admin.categories.index')->with('status', 'Category updated.');
    }

    public function destroy(MenuCategory $category)
    {
        if ($category->items()->exists()) {
            return back()->with('error', 'This category still has menu items. Move or delete them first.');
        }

        $category->delete();

        return redirect()->route('admin.categories.index')->with('status', 'Category deleted.');
    }
}
