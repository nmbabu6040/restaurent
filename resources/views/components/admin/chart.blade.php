@props(['title', 'config', 'height' => 'h-72'])

@php
    $money = $config['money'] ?? false;
    $fmt = fn($v) => $money ? money($v) : number_format((float) $v, 0);
    $empty = collect($config['datasets'])->every(fn($d) => array_sum($d['data']) == 0);
    $summary =
        $title .
        ': ' .
        collect($config['labels'])
            ->map(fn($l, $i) => $l . ' ' . $fmt($config['datasets'][0]['data'][$i] ?? 0))
            ->take(6)
            ->implode(', ') .
        (count($config['labels']) > 6 ? ', and more' : '');
@endphp

<figure class="card p-5">
    <figcaption class="font-heading text-lg text-navy">{{ $title }}</figcaption>

    @if ($empty)
        <p class="mt-3 flex {{ $height }} items-center justify-center text-sm">No data for this period yet.</p>
    @else
        <div class="relative mt-3 {{ $height }}">
            <canvas data-chart="{{ json_encode($config) }}" role="img" aria-label="{{ $summary }}"></canvas>
        </div>

        <table class="sr-only">
            <caption>{{ $title }}</caption>
            <thead>
                <tr>
                    <th scope="col">Label</th>
                    @foreach ($config['datasets'] as $dataset)
                        <th scope="col">{{ $dataset['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($config['labels'] as $i => $label)
                    <tr>
                        <th scope="row">{{ $label }}</th>
                        @foreach ($config['datasets'] as $dataset)
                            <td>{{ $fmt($dataset['data'][$i] ?? 0) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</figure>
