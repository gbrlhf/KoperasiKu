<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Konfigurasi Identitas & Kontak Koperasi (KoperasiKu)
    |--------------------------------------------------------------------------
    | Menggunakan brand KoperasiKu dan data kontak referensi KOPEG.
    | Seluruh teks antarmuka membaca dari file konfigurasi ini agar mudah diubah.
    */

    'brand_name' => env('KOPERASI_BRAND_NAME', 'KoperasiKu'),
    'full_name' => 'Koperasi Konsumen Pegawai (KOPEG)',
    'tagline' => 'Sistem Koperasi Karyawan Terintegrasi & Berdaya Saing',
    'slogan' => 'Belanja Mudah, Transparan, dan Mandiri untuk Kesejahteraan Anggota',

    'contact' => [
        'address' => 'Jl. Kanayakan No. A11, Ir. H. Juanda (Dago), Kecamatan Coblong, Kota Bandung, Jawa Barat 40135',
        'location_note' => 'Gedung Koperasi (Depan Kampus POLMAN Bandung)',
        'phone' => '(022) 2500241',
        'whatsapp' => '081395440981',
        'whatsapp_url' => 'https://api.whatsapp.com/send?phone=6281395440981&text=Halo%20Admin%20Koperasi,%20saya%20ingin%20bertanya',
        'email' => 'kopeg@polman-bandung.ac.id',
    ],

    'operating_hours' => [
        'mart' => [
            'days' => 'Senin s.d. Sabtu',
            'hours' => '07.00 – 18.00 WIB',
            'note' => 'Minggu & Hari Libur Nasional Tutup',
        ],
        'office' => [
            'days' => 'Senin s.d. Jumat',
            'hours' => '08.00 – 16.00 WIB',
            'note' => 'Layanan Administrasi & Keanggotaan',
        ],
    ],

    'external_links' => [
        'pilkopeg' => 'https://kopeg.polman-bandung.ac.id/pilkopeg/',
        'berita_lama' => 'https://kopeg.polman-bandung.ac.id/index.php?m=301&b=1',
        'faq_lama' => 'https://kopeg.polman-bandung.ac.id/index.php?m=312&b=1',
    ],
];
