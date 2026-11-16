@props([
    'title' => null,
    'subtitle' => null,
    'headerAction' => null,
    'footer' => null,
    'class' => '',
])

<div {{ $attributes->merge(['class' => 'card shadow-sm ' . $class]) }}>
    @if($title || $headerAction || $subtitle)
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <div>
                @if($title)
                    <h5 class="card-title mb-0 fw-semibold text-dark">{{ $title }}</h5>
                @endif
                @if($subtitle)
                    <small class="text-muted d-block mt-1">{{ $subtitle }}</small>
                @endif
            </div>
            @if($headerAction)
                <div>
                    {{ $headerAction }}
                </div>
            @endif
        </div>
    @endif

    <div class="card-body">
        {{ $slot }}
    </div>

    @if($footer)
        <div class="card-footer bg-light py-2 px-3 border-top text-muted small">
            {{ $footer }}
        </div>
    @endif
</div>
