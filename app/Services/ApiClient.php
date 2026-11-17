<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * ApiClient
 * 
 * Kerangka pemanggilan REST API internal backend KoperasiKu (/api/v1).
 * Dikelola oleh PIC A untuk konsumsi data dari service PIC B, C, dan D.
 */
class ApiClient
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.koperasiku.base_url', 'http://localhost:8000/api/v1');
    }

    /**
     * Mendapatkan base URL API backend
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    // TODO: Implementasikan fungsi request HTTP (GET, POST, PATCH) terotentikasi ke /api/v1
}
