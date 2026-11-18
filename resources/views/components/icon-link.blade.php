@props(['href', 'icon', 'label'])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'icon-btn']) }} title="{{ $label }}" aria-label="{{ $label }}">
    <x-icon :name="$icon" />
</a>
