@php
    $editing = $item->exists;
    $dietary = old('dietary', $item->dietary ?? []);
    $dietary = is_array($dietary) ? $dietary : [];
    $rowsFor = fn(string $key, $relation) => old(
        $key,
        $relation
            ->map(
                fn($o) => [
                    'id' => $o->id,
                    'name_en' => $o->name_en,
                    'name_bn' => $o->name_bn,
                    'price' => $o->price,
                    'is_active' => $o->is_active,
                    'portion_factor' => $o->portion_factor ?? null,
                    'cost_price' => $o->cost_price ?? null,
                ],
            )
            ->all(),
    );
    $recipeRows = old(
        'recipe',
        $item->recipeLines->map(fn($l) => ['ingredient_id' => $l->ingredient_id, 'quantity' => $l->quantity])->all(),
    );
    $stockMode = old('stock_mode', $item->stock_mode ?: 'none');
    $variationRows = $rowsFor('variations', $item->variations);
    $addonRows = $rowsFor('addons', $item->addons);
    $nextIndex = fn(array $rows) => $rows ? max(array_keys($rows)) + 1 : 0;
@endphp

<x-admin.layout :title="$editing ? 'Edit menu item' : 'New menu item'">
    <form method="POST" enctype="multipart/form-data"
        action="{{ $editing ? route('admin.items.update', $item) : route('admin.items.store') }}"
        class="card mt-6 space-y-6 p-6 sm:p-8">
        @csrf
        <input type="hidden" name="recipe_form" value="1">
        @if ($editing)
            @method('PUT')
        @endif

        <div class="grid gap-5 md:grid-cols-2">
            <x-form.field name="name_en" label="Name (English)" :required="true" :value="$item->name_en" maxlength="150" />
            <x-form.field name="name_bn" label="Name (বাংলা)" :value="$item->name_bn" maxlength="150" />
            <x-form.textarea name="description_en" label="Description (English)" :value="$item->description_en" rows="3"
                maxlength="2000" />
            <x-form.textarea name="description_bn" label="Description (বাংলা)" :value="$item->description_bn" rows="3"
                maxlength="2000" />
            <x-form.textarea name="ingredients_en" label="Ingredients (English)" :value="$item->ingredients_en" rows="2"
                maxlength="1000" />
            <x-form.textarea name="ingredients_bn" label="Ingredients (বাংলা)" :value="$item->ingredients_bn" rows="2"
                maxlength="1000" />
            <x-form.textarea name="allergens_en" label="Allergen information (English)" :value="$item->allergens_en" rows="2"
                maxlength="1000" />
            <x-form.textarea name="allergens_bn" label="Allergen information (বাংলা)" :value="$item->allergens_bn" rows="2"
                maxlength="1000" />
        </div>

        <div class="grid gap-5 md:grid-cols-3">
            <div>
                <label for="menu_category_id" class="block text-sm font-medium text-navy">Category<span
                        aria-hidden="true" class="text-terracotta-dark"> *</span></label>
                <select id="menu_category_id" name="menu_category_id" required aria-describedby="menu_category_id-error"
                    class="mt-1 w-full rounded-lg border border-navy/20 bg-surface px-4 py-2.5 text-sm focus:border-terracotta">
                    <option value="">Choose…</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}" @selected((int) old('menu_category_id', $item->menu_category_id) === $c->id)>{{ $c->name_en }}</option>
                    @endforeach
                </select>
                <p id="menu_category_id-error" class="mt-1 text-sm text-red-700">
                    {{ $errors->first('menu_category_id') }}</p>
            </div>
            <x-form.field name="price" type="number" label="Base price (used when the item has no sizes)"
                :required="true" :value="$item->price" step="0.01" min="0" />
            <x-form.field name="sort_order" type="number" label="Sort order" :value="$item->sort_order" min="0"
                max="65535" />
        </div>

        <x-form.field name="slug" label="URL slug (auto from English name if empty)" :value="$item->slug"
            maxlength="160" />

        <fieldset class="rounded-xl border border-navy/10 p-4">
            <legend class="px-1 font-heading text-lg text-navy">Stock and cost</legend>
            <div class="grid gap-5 md:grid-cols-3">
                <div>
                    <label for="stock_mode" class="block text-sm font-medium text-navy">Stock tracking</label>
                    <select id="stock_mode" name="stock_mode" data-stock-mode
                        class="mt-1 w-full rounded-lg border border-navy/20 bg-surface px-4 py-2.5 text-sm focus:border-terracotta">
                        <option value="none" @selected($stockMode === 'none')>None (always available)</option>
                        <option value="quantity" @selected($stockMode === 'quantity')>Dish quantity (e.g. 50 plates today)
                        </option>
                        <option value="recipe" @selected($stockMode === 'recipe')>Recipe (uses ingredient stock)</option>
                    </select>
                    <p class="mt-1 text-sm text-red-700">{{ $errors->first('stock_mode') }}</p>
                </div>
                <div data-show-for="quantity">
                    <x-form.field name="stock_qty" type="number" label="Plates in stock" :value="$item->stock_qty"
                        min="0" max="1000000" />
                    <p class="mt-1 text-xs">At 0 the dish shows "Sold out" by itself. After restocking, tick "Available"
                        below.</p>
                </div>
                <div data-show-for="none quantity">
                    <x-form.field name="cost_price" type="number" label="Cost per portion" :value="$item->cost_price"
                        step="0.01" min="0" />
                    <p class="mt-1 text-xs">What one portion costs you to make. Used in profit and loss.</p>
                </div>
            </div>

            <div data-show-for="recipe" class="mt-5">
                <h3 class="font-heading text-base text-navy">Recipe (per standard portion)</h3>
                <p class="text-sm">Amounts are in each ingredient's own unit. Sizes use their "Portion x" below (Half =
                    0.6 means 60% of this recipe).
                    @if ($item->exists && $item->recipeLines->isNotEmpty())
                        Calculated cost per portion: <strong>{{ money($item->recipeCost()) }}</strong>.
                    @endif
                </p>
                <div data-option-list="recipe" data-next-index="{{ $nextIndex($recipeRows) }}" class="mt-3 space-y-3">
                    @foreach ($recipeRows as $i => $row)
                        @include('admin.items._recipe-row', ['i' => $i, 'row' => $row])
                    @endforeach
                </div>
                <template id="tpl-recipe">
                    @include('admin.items._recipe-row', ['i' => '__INDEX__', 'row' => []])
                </template>
                <button type="button" data-add-option="recipe" class="btn mt-3 border border-navy/20 text-navy">Add
                    ingredient</button>
                @if ($ingredients->isEmpty())
                    <p class="mt-2 text-sm">No ingredients yet. <a href="{{ route('admin.ingredients.create') }}"
                            class="font-semibold text-navy underline">Add one first</a>.</p>
                @endif
            </div>
        </fieldset>

        <fieldset class="rounded-xl border border-navy/10 p-4">
            <legend class="px-1 font-heading text-lg text-navy">Sizes / portions</legend>
            <p class="text-sm">If you add sizes, the customer must choose one, and the size price replaces the base
                price.</p>
            <div data-option-list="variations" data-next-index="{{ $nextIndex($variationRows) }}"
                class="mt-3 space-y-3">
                @foreach ($variationRows as $i => $row)
                    @include('admin.items._option-row', [
                        'field' => 'variations',
                        'i' => $i,
                        'row' => $row,
                        'priceLabel' => 'Size price',
                    ])
                @endforeach
            </div>
            <template id="tpl-variations">
                @include('admin.items._option-row', [
                    'field' => 'variations',
                    'i' => '__INDEX__',
                    'row' => [],
                    'priceLabel' => 'Size price',
                ])
            </template>
            <button type="button" data-add-option="variations" class="btn mt-3 border border-navy/20 text-navy">Add
                size</button>
        </fieldset>

        <fieldset class="rounded-xl border border-navy/10 p-4">
            <legend class="px-1 font-heading text-lg text-navy">Extras / add-ons</legend>
            <p class="text-sm">Optional extras the customer can tick. The price is added on top of the item price.</p>
            <div data-option-list="addons" data-next-index="{{ $nextIndex($addonRows) }}" class="mt-3 space-y-3">
                @foreach ($addonRows as $i => $row)
                    @include('admin.items._option-row', [
                        'field' => 'addons',
                        'i' => $i,
                        'row' => $row,
                        'priceLabel' => 'Extra price',
                    ])
                @endforeach
            </div>
            <template id="tpl-addons">
                @include('admin.items._option-row', [
                    'field' => 'addons',
                    'i' => '__INDEX__',
                    'row' => [],
                    'priceLabel' => 'Extra price',
                ])
            </template>
            <button type="button" data-add-option="addons" class="btn mt-3 border border-navy/20 text-navy">Add
                extra</button>
        </fieldset>

        <fieldset>
            <legend class="text-sm font-medium text-navy">Dietary labels</legend>
            <input type="hidden" name="dietary[]" value="">
            <div class="mt-2 flex flex-wrap gap-5 text-sm">
                @foreach (['vegetarian' => 'Vegetarian', 'halal' => 'Halal', 'spicy' => 'Spicy'] as $value => $label)
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="dietary[]" value="{{ $value }}" class="accent-navy"
                            @checked(in_array($value, $dietary, true))> {{ $label }}
                    </label>
                @endforeach
            </div>
            <p class="mt-1 text-sm text-red-700">{{ $errors->first('dietary.*') }}</p>
        </fieldset>

        <div class="flex flex-wrap gap-6 text-sm">
            @foreach (['is_available' => 'Available', 'is_featured' => 'Featured', 'is_special' => 'Special'] as $field => $label)
                <input type="hidden" name="{{ $field }}" value="0">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="{{ $field }}" value="1" class="accent-navy"
                        @checked(old($field, $item->{$field}))> {{ $label }}
                </label>
            @endforeach
        </div>

        <div>
            <label for="image" class="block text-sm font-medium text-navy">Image (JPG, PNG or WebP, max 2
                MB)</label>
            @if ($item->image_url)
                <img src="{{ $item->image_url }}" alt="Current image of {{ $item->name_en }}" width="160"
                    height="120" class="mt-2 h-24 w-32 rounded-lg object-cover">
                <input type="hidden" name="remove_image" value="0">
                <label class="mt-2 flex items-center gap-2 text-sm">
                    <input type="checkbox" name="remove_image" value="1" class="accent-navy"> Remove current
                    image
                </label>
            @endif
            <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp"
                aria-describedby="image-error"
                class="mt-2 block w-full text-sm file:mr-3 file:rounded-full file:border-0 file:bg-navy file:px-4 file:py-2 file:text-white">
            <p id="image-error" class="mt-1 text-sm text-red-700">{{ $errors->first('image') }}</p>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="btn-primary">Save</button>
            <a href="{{ route('admin.items.index') }}" class="btn border border-navy/20 text-navy">Cancel</a>
        </div>
    </form>
</x-admin.layout>
