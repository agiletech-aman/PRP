@props(['name', 'label', 'checked' => false, 'hint' => null])

<label class="toggle">
    <input type="checkbox" name="{{ $name }}" value="1" @checked($checked) {{ $attributes }}>
    <span class="toggle-track" aria-hidden="true"><span class="toggle-thumb"></span></span>
    <span class="toggle-text">
        <strong>{{ $label }}</strong>
        @if ($hint)<span>{{ $hint }}</span>@endif
    </span>
</label>
