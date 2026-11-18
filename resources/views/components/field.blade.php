@props(['name', 'label', 'for' => null, 'required' => false, 'hint' => null, 'full' => false, 'bag' => 'default'])

@php
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $fieldErrors = $errors->getBag($bag);
@endphp

<div {{ $attributes->class(['field', 'field-full' => $full, 'has-error' => $fieldErrors->has($errorKey)]) }}>
    <label for="{{ $for ?? $name }}">
        {{ $label }}
        @if ($required)<span class="required" aria-hidden="true">*</span>@endif
    </label>

    {{ $slot }}

    @if ($fieldErrors->has($errorKey))
        <p class="field-error">{{ $fieldErrors->first($errorKey) }}</p>
    @elseif ($hint)
        <p class="field-hint">{{ $hint }}</p>
    @endif
</div>
