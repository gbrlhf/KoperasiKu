{{-- ==============================================================================
   faq.blade.php - Bantuan & Pertanyaan Umum (FAQ KoperasiKu)
   - Kebijakan pickup gerai, metode bayar & piutang tempo 7 hari
   ============================================================================== --}}
<section id="faq" class="py-5 bg-white border-bottom">
    <div class="container px-3 px-lg-4">
        <div class="row g-4 g-lg-5">
            <!-- Left Info -->
            <div class="col-12 col-lg-4">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1.5 fw-semibold small mb-2">
                    Bantuan Transaksi
                </span>
                <h2 class="fw-bold text-dark mb-3 fs-3">Pertanyaan Umum (FAQ)</h2>
                <p class="text-secondary small mb-4 lh-base">
                    Panduan seputar alur pesanan pickup, kebijakan piutang tempo 7 hari, dan layanan belanja di gerai KoperasiKu.
                </p>

                <div class="p-3.5 rounded-2 bg-light border mb-3">
                    <h6 class="fw-bold text-dark mb-1 small">Butuh Bantuan Langsung?</h6>
                    <p class="text-secondary mb-3" style="font-size: 0.78rem;">Petugas kasir dan admin pengurus siap melayani pertanyaan Anda pada jam operasional gerai.</p>
                    <a href="{{ config('koperasi.contact.whatsapp_url') }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-success btn-sm w-100 fw-semibold d-inline-flex align-items-center justify-content-center py-2">
                        <i class="bi bi-whatsapp me-2"></i>
                        <span>Chat WhatsApp Admin</span>
                    </a>
                </div>

                <a href="{{ config('koperasi.external_links.faq_lama') }}" target="_blank" rel="noopener noreferrer" class="text-primary text-decoration-none small fw-semibold d-inline-flex align-items-center">
                    <span class="me-1">Lihat Halaman FAQ Lengkap</span>
                    <i class="bi bi-box-arrow-up-right small"></i>
                </a>
            </div>

            <!-- Right: Accordion -->
            <div class="col-12 col-lg-8">
                <div class="accordion faq-accordion" id="accordionFaq">
                    @foreach($faqs as $index => $faq)
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading{{ $index }}">
                                <button class="accordion-button {{ $index !== 0 ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#collapse{{ $index }}" aria-expanded="{{ $index === 0 ? 'true' : 'false' }}" aria-controls="collapse{{ $index }}">
                                    <span class="me-2 text-primary fw-bold">Q{{ $index + 1 }}.</span> {{ $faq['question'] }}
                                </button>
                            </h2>
                            <div id="collapse{{ $index }}" class="accordion-collapse collapse {{ $index === 0 ? 'show' : '' }}" aria-labelledby="heading{{ $index }}" data-bs-parent="#accordionFaq">
                                <div class="accordion-body text-secondary small lh-base bg-white">
                                    {{ $faq['answer'] }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
