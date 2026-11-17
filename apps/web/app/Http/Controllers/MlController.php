<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class MlController extends Controller
{
    private function baseUrl(): string
    {
        return rtrim((string) env('ML_SERVICE_URL', 'http://ml:8001'), '/');
    }

    public function health(): JsonResponse
    {
        try {
            $response = Http::timeout(5)->get($this->baseUrl() . '/health');
            return response()->json([
                'laravel' => 'ok',
                'ml_service' => $response->json(),
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'laravel' => 'ok',
                'ml_service' => 'unreachable',
                'error' => $e->getMessage(),
            ], 503);
        }
    }

    public function forecastDemand(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'history' => ['nullable', 'array', 'min:2'],
            'history.*' => ['numeric', 'min:0'],
            'horizon_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        try {
            $response = Http::timeout(8)->post($this->baseUrl() . '/predict/demand', [
                'product_id' => $validated['product_id'],
                'history' => $validated['history'] ?? null,
                'horizon_days' => $validated['horizon_days'] ?? 7,
            ]);

            return response()->json($response->json(), $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'ML demand forecast service unavailable',
                'error' => $e->getMessage(),
            ], 503);
        }
    }

    public function creditRisk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'credit_limit' => ['required', 'numeric', 'gt:0'],
            'current_debt' => ['required', 'numeric', 'min:0'],
            'overdue_count' => ['required', 'integer', 'min:0'],
            'on_time_ratio' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);

        try {
            $response = Http::timeout(8)->post($this->baseUrl() . '/predict/credit-risk', [
                'credit_limit' => $validated['credit_limit'],
                'current_debt' => $validated['current_debt'],
                'overdue_count' => $validated['overdue_count'],
                'on_time_ratio' => $validated['on_time_ratio'] ?? 1.0,
            ]);

            return response()->json($response->json(), $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'ML credit risk service unavailable',
                'error' => $e->getMessage(),
            ], 503);
        }
    }
}