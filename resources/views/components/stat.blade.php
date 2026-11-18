@props(['label', 'value', 'href' => null, 'hint' => null])

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => 'stat stat-link']) }}>
@else
    <div {{ $attributes->merge(['class' => 'stat']) }}>
@endif
        <span class="stat-label">{{ $label }}</span>
        <span class="stat-value">{{ $value }}</span>
        @if (filled((string) $hint))
            <span class="stat-hint">{{ $hint }}</span>
        @endif
@if ($href)
    </a>
@else
    </div>
@endif
