@props(['title', 'description' => null])

<section class="form-section">
    <div class="form-section-head">
        <h2>{{ $title }}</h2>
        @if ($description)
            <p>{{ $description }}</p>
        @endif
    </div>
    <div {{ $attributes->merge(['class' => 'form-section-body']) }}>
        {{ $slot }}
    </div>
</section>
