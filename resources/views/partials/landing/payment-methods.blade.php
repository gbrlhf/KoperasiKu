<section class="py-5 bg-light border-bottom">
    <div class="container px-3 px-lg-4">
        <div class="text-center max-w-2xl mx-auto mb-5">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1.5 fw-semibold small mb-2">
                Opsi Pembayaran
            </span>
            <h2 class="fw-bold text-dark mb-2 fs-3">Metode Pembayaran Resmi Koperasi</h2>
            <p class="text-secondary small mb-0">
                Pilih opsi pembayaran yang sesuai dengan preferensi Anda saat checkout atau di kasir.
            </p>
        </div>

        <div class="row g-4 row-cols-1 row-cols-lg-3">
            @foreach($paymentMethods as $pm)
                @php
                    $borderClass = match($pm['code']) {
                        'CASH' => 'cash-border',
                        'TRANSFER' => 'transfer-border',
                        'CREDIT' => 'credit-border',
                        default => '',
                    };
                    $badgeBg = match($pm['code']) {
                        'CASH' => 'bg-success text-white',
                        'TRANSFER' => 'bg-primary text-white',
                        'CREDIT' => 'bg-dark text-white',
                        default => 'bg-secondary text-white',
                    };
                @endphp
                <div class="col">
                    <div class="payment-card {{ $borderClass }}">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="p-2.5 rounded-2 bg-light border text-primary d-inline-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                <i class="bi {{ $pm['icon'] }} fs-4"></i>
                            </div>
                            <span class="badge {{ $badgeBg }} px-2.5 py-1 small fw-semibold">
                                {{ $pm['badge'] }}
                            </span>
                        </div>

                        <h4 class="fw-bold text-dark mb-2 fs-5">{{ $pm['title'] }}</h4>
                        <p class="text-secondary small mb-4 lh-base">{{ $pm['desc'] }}</p>

                        <div class="border-top pt-3 mt-auto">
                            <small class="text-uppercase text-muted fw-bold d-block mb-2.5" style="font-size: 0.72rem; letter-spacing: 0.04em;">
                                Ketentuan Transaksi:
                            </small>
                            <ul class="list-unstyled mb-0 small text-secondary d-flex flex-column gap-2">
                                @foreach($pm['features'] as $f)
                                    <li class="d-flex align-items-start gap-2">
                                        <i class="bi bi-check2 text-primary fs-6 mt-0.5 fw-bold"></i>
                                        <span>{{ $f }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Note on Credit Policy -->
        <div class="text-center mt-4 pt-1">
            <span class="badge bg-light text-secondary border px-3 py-2 small">
                <i class="bi bi-info-circle me-1 text-primary"></i>
                Jatuh tempo piutang dihitung tepat 7 hari kalender sejak serah terima fisik di kasir.
            </span>
        </div>
    </div>
</section>
