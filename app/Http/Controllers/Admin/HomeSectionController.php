<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomeSection;
use App\Support\Color;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HomeSectionController extends Controller
{
    public function index()
    {
        return view('admin.home-sections.index', ['sections' => HomeSection::ordered()->get()]);
    }

    public function create()
    {
        return view('admin.home-sections.form', ['section' => new HomeSection(['is_custom' => true, 'is_visible' => true])]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules(true));

        HomeSection::create($data + [
            'key' => 'custom-' . Str::lower(Str::random(8)),
            'is_custom' => true,
            'sort_order' => ((int) HomeSection::max('sort_order')) + 10,
        ]);

        return redirect()->route('admin.home-sections.index')->with('status', 'Section added at the bottom. Use the arrows to move it.');
    }

    public function edit(HomeSection $section)
    {
        return view('admin.home-sections.form', compact('section'));
    }

    public function update(Request $request, HomeSection $section)
    {
        $section->update($request->validate($this->rules($section->is_custom)));

        return redirect()->route('admin.home-sections.index')->with('status', 'Section saved.');
    }

    public function destroy(HomeSection $section)
    {
        if (! $section->is_custom) {
            return back()->with('error', 'Built-in sections cannot be deleted. Hide them instead.');
        }

        $section->delete();

        return back()->with('status', 'Section deleted.');
    }

    public function toggle(HomeSection $section)
    {
        $section->update(['is_visible' => ! $section->is_visible]);

        return back()->with('status', $section->label() . ($section->is_visible ? ' is now shown.' : ' is now hidden.'));
    }

    public function move(HomeSection $section, string $direction)
    {
        abort_unless(in_array($direction, ['up', 'down'], true), 404);

        DB::transaction(function () use ($section, $direction) {
            $list = HomeSection::ordered()->lockForUpdate()->get()->values();
            $i = $list->search(fn($s) => $s->id === $section->id);
            $j = $direction === 'up' ? $i - 1 : $i + 1;

            if ($i === false || ! isset($list[$j])) {
                return;
            }

            $order = $list->all();
            [$order[$i], $order[$j]] = [$order[$j], $order[$i]];

            foreach ($order as $position => $row) {
                $row->update(['sort_order' => ($position + 1) * 10]);
            }
        });

        return back();
    }

    private function rules(bool $custom): array
    {
        $rules = [
            'title_en' => ['nullable', 'string', 'max:150'],
            'title_bn' => ['nullable', 'string', 'max:150'],
            'subtitle_en' => ['nullable', 'string', 'max:300'],
            'subtitle_bn' => ['nullable', 'string', 'max:300'],
            'body_en' => ['nullable', 'string', 'max:5000'],
            'body_bn' => ['nullable', 'string', 'max:5000'],
            'is_visible' => ['required', 'boolean'],
        ];

        if ($custom) {
            $rules['title_en'] = ['required', 'string', 'max:150'];
            $rules += [
                'button_text_en' => ['nullable', 'string', 'max:60'],
                'button_text_bn' => ['nullable', 'string', 'max:60'],
                'button_url' => ['nullable', 'string', 'max:255', 'regex:#^(/(?!/)|https?://)\S*$#'],
                'bg_color' => [
                    'nullable',
                    'regex:/^#[0-9A-Fa-f]{6}$/',
                    function (string $attribute, mixed $value, \Closure $fail) {
                        if ($value && Color::bestContrast($value) < 4.5) {
                            $fail('This color is too mid-tone: text on it would be hard to read. Pick a lighter or a darker shade.');
                        }
                    },
                ],
            ];
        }

        return $rules;
    }
}
