@props([
    'type' => 'submit',
    'variant' => 'primary',
    'loadingText' => 'Memproses...',
    'icon' => null,
])

<button 
    type="{{ $type }}" 
    {{ $attributes->merge([
        'class' => "btn btn-{$variant} d-inline-flex align-items-center justify-content-center gap-2",
    ]) }}
    onclick="handleButtonClick(this, '{{ addslashes($loadingText) }}')"
>
    <span class="btn-spinner spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
    @if($icon)
        <i class="bi {{ $icon }} btn-icon"></i>
    @endif
    <span class="btn-text">{{ $slot }}</span>
</button>

@once
    @push('scripts')
    <script>
        function handleButtonClick(btn, loadingText) {
            // Jika form valid (atau tombol bukan di dalam form)
            const form = btn.closest('form');
            if (form && !form.checkValidity()) {
                // Biarkan validasi bawaan browser berjalan
                return;
            }

            // Mencegah klik ganda
            setTimeout(() => {
                btn.disabled = true;
                const spinner = btn.querySelector('.btn-spinner');
                const icon = btn.querySelector('.btn-icon');
                const text = btn.querySelector('.btn-text');

                if (spinner) spinner.classList.remove('d-none');
                if (icon) icon.classList.add('d-none');
                if (text && loadingText) text.textContent = loadingText;

                if (form) {
                    form.submit();
                }
            }, 10);
        }
    </script>
    @endpush
@endonce
