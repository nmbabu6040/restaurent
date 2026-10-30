<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminResources;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Services\ImageService;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ContentController extends Controller
{
    public function index(string $resource)
    {
        $cfg = $this->cfg($resource);
        [$column, $direction] = $cfg['order'] ?? ['sort_order', 'asc'];

        return view('admin.content.index', [
            'key' => $resource,
            'cfg' => $cfg,
            'records' => $cfg['model']::query()->orderBy($column, $direction)->orderBy('id')->paginate(20),
        ]);
    }

    public function create(string $resource)
    {
        $cfg = $this->cfg($resource);
        $defaults = collect($cfg['fields'])
            ->filter(fn($f) => array_key_exists('default', $f))
            ->mapWithKeys(fn($f) => [$f['name'] => $f['default']])
            ->all();

        return view('admin.content.form', [
            'key' => $resource,
            'cfg' => $cfg,
            'record' => new $cfg['model']($defaults),
        ]);
    }

    public function store(Request $request, string $resource)
    {
        $cfg = $this->cfg($resource);
        $data = $this->prepare($request->validate($this->rules($cfg, true)), $cfg);

        if ($file = $request->file('image')) {
            $data[$cfg['image']['field']] = app(ImageService::class)->store($file, $cfg['image']['dir']);
        }

        $cfg['model']::create($data);

        return redirect()->route('admin.content.index', $resource)->with('status', ucfirst($cfg['singular']) . ' created.');
    }

    public function edit(string $resource, int $id)
    {
        $cfg = $this->cfg($resource);

        return view('admin.content.form', [
            'key' => $resource,
            'cfg' => $cfg,
            'record' => $cfg['model']::findOrFail($id),
        ]);
    }

    public function update(Request $request, string $resource, int $id)
    {
        $cfg = $this->cfg($resource);
        $record = $cfg['model']::findOrFail($id);
        $data = $this->prepare($request->validate($this->rules($cfg, false, $id)), $cfg);
        $img = $cfg['image'];

        if ($img && $request->hasFile('image')) {
            $this->deleteFile($record->{$img['field']});
            $data[$img['field']] = app(ImageService::class)->store($request->file('image'), $img['dir']);
        } elseif ($img && ! $img['required'] && $request->boolean('remove_image')) {
            $this->deleteFile($record->{$img['field']});
            $data[$img['field']] = null;
        }

        $record->update($data);

        return redirect()->route('admin.content.index', $resource)->with('status', ucfirst($cfg['singular']) . ' updated.');
    }

    public function destroy(string $resource, int $id)
    {
        $cfg = $this->cfg($resource);
        $record = $cfg['model']::findOrFail($id);

        if ($cfg['image']) {
            $this->deleteFile($record->{$cfg['image']['field']});
        }

        $record->delete();

        return redirect()->route('admin.content.index', $resource)->with('status', ucfirst($cfg['singular']) . ' deleted.');
    }

    private function cfg(string $resource): array
    {
        $all = AdminResources::all();
        abort_unless(isset($all[$resource]), 404);

        return $all[$resource];
    }

    private function rules(array $cfg, bool $creating, ?int $id = null): array
    {
        $rules = [];

        foreach ($cfg['fields'] as $f) {
            $fieldRules = $f['rules'];

            if (isset($f['unique'])) {
                $fieldRules[] = Rule::unique($f['unique'], $f['name'])->ignore($id);
            }

            $rules[$f['name']] = $fieldRules;
        }

        if ($cfg['image']) {
            $rules['image'] = [
                $creating && $cfg['image']['required'] ? 'required' : 'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
                'dimensions:max_width=4000,max_height=4000',
            ];
            $rules['remove_image'] = ['nullable', 'boolean'];
        }

        return $rules;
    }

    private function prepare(array $data, array $cfg): array
    {
        unset($data['image'], $data['remove_image']);

        if (array_key_exists('sort_order', $data)) {
            $data['sort_order'] = $data['sort_order'] ?? 0;
        }

        if (isset($data['code'])) {
            $data['code'] = Str::upper(trim($data['code']));
        }

        // A "starts on" date means the start of that day, an "ends on" date means its last second.
        foreach ($cfg['fields'] as $field) {
            if (isset($field['bound']) && filled($data[$field['name']] ?? null)) {
                $day = Carbon::createFromFormat('Y-m-d', $data[$field['name']]);
                $data[$field['name']] = $field['bound'] === 'end' ? $day->endOfDay() : $day->startOfDay();
            }
        }

        return $data;
    }

    private function deleteFile(?string $path): void
    {
        app(ImageService::class)->delete($path);
    }
}
