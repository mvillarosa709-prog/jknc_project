{{--
    A monthly trend line with the area beneath it shaded.

    Drawn as an inline SVG rather than a chart library: this is the project's only
    chart, it is a single polyline, and adding a charting dependency to render it
    would be a larger change than the chart itself. The mock uses the same
    approach — a viewBox normalised to 0-100 with preserveAspectRatio="none" so
    the shape stretches to whatever width the card has.

    That normalisation is also why the stroke uses vector-effect="non-scaling-
    stroke": without it the stroke would be scaled with the shape and end up
    hairline-thin on a wide card and heavy on a narrow one.

    `points` is a list of ['label' => string, 'value' => int]. Every month in the
    window is passed, including empty ones, so the line spans the full width and
    a quiet period reads as quiet rather than being squeezed out of the axis.
--}}
@props(['points' => [], 'empty' => null])

@php
    $values = array_map(static fn (array $point): int => (int) ($point['value'] ?? 0), $points);
    $max = $values === [] ? 0 : max($values);
    $total = array_sum($values);

    // Every point at zero is a flat line at the baseline. Drawn honestly it is a
    // line along the floor, but it reads as a chart that failed rather than one
    // reporting nothing, so the card says so instead.
    $isEmpty = $total === 0;

    $step = count($points) > 1 ? 100 / (count($points) - 1) : 100;
    $offset = 0.0;
    $polyline = [];

    foreach ($values as $value) {
        // A single point of activity would otherwise divide by a maximum of its
        // own value and sit pinned to the ceiling; half height reads as "some".
        $y = $max > 0 ? 100 - ($value / $max * 88) : 100;
        $polyline[] = number_format($offset, 2, '.', '').','.number_format($y, 2, '.', '');
        $offset += $step;
    }

    $line = implode(' ', $polyline);

    // Only alternate labels, so twelve month names do not collide on a narrow card.
    $stride = count($points) > 8 ? 2 : 1;
@endphp

@if ($isEmpty)
    <x-ordo.empty :message="$empty ?? 'Not enough history yet to show a trend.'" />
@else
    <div>
        <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="h-[150px] w-full"
             role="img" aria-label="Monthly filings trend">
            {{-- The shaded area, closed along the baseline. --}}
            <polygon points="0,100 {{ $line }} 100,100" fill="rgba(47,98,231,.08)" />
            <polyline points="{{ $line }}" fill="none" stroke="#2f62e7" stroke-width="2"
                      vector-effect="non-scaling-stroke" stroke-linejoin="round" />
        </svg>

        <div class="mt-1.5 flex justify-between text-[9px] text-ordo-muted">
            @foreach ($points as $index => $point)
                @if ($index % $stride === 0 || $index === count($points) - 1)
                    <span>{{ $point['label'] }}</span>
                @endif
            @endforeach
        </div>
    </div>
@endif
