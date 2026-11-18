@props(['action', 'method' => 'POST', 'submit' => 'Save', 'cancel' => null])

<form method="POST" action="{{ $action }}" {{ $attributes->merge(['class' => 'form-card']) }}>
    @csrf
    @unless (strtoupper($method) === 'POST')
        @method($method)
    @endunless

    {{ $slot }}

    <div class="form-footer">
        @if ($cancel)
            <a href="{{ $cancel }}" class="btn btn-ghost">Cancel</a>
        @endif
        <button type="submit" class="btn btn-primary">{{ $submit }}</button>
    </div>
</form>
