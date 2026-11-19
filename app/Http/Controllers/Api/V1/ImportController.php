<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Services\Import\ExcelImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function __construct(
        private readonly ExcelImportService $importService
    ) {}

    public function index(): JsonResponse
    {
        $batches = ImportBatch::query()
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat batch impor berhasil diambil.',
            'data' => $batches,
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->merge([
            'type' => strtoupper(trim((string) $request->input('type', ''))),
        ]);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'type' => ['required', 'string', 'in:PRODUK,ANGGOTA,STOK,PEMASOK,PRODUCTS,PRODUCT,MEMBERS,MEMBER,STOCK,STOCK_OPENING,SUPPLIERS,SUPPLIER'],
            'cutoff_at' => ['nullable', 'date'],
            'cut_off_date' => ['nullable', 'date'],
        ]);

        $cutoffAt = $validated['cutoff_at'] ?? $validated['cut_off_date'] ?? null;

        $batch = $this->importService->upload(
            file: $request->file('file'),
            type: $validated['type'],
            cutoffAt: $cutoffAt,
            actorUserId: $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'File impor berhasil diunggah dan divalidasi di staging.',
            'data' => $this->formatBatchResponse($batch),
        ], 201);
    }

    public function validateBatch(string $id): JsonResponse
    {
        $batch = ImportBatch::findOrFail($id);
        $validatedBatch = $this->importService->validateBatch($batch);

        return response()->json([
            'success' => true,
            'message' => 'Validasi batch impor selesai.',
            'data' => $this->formatBatchResponse($validatedBatch),
        ]);
    }

    public function commit(Request $request, string $id): JsonResponse
    {
        $batch = ImportBatch::findOrFail($id);
        $committedBatch = $this->importService->commitBatch($batch, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Batch impor berhasil di-commit ke database master.',
            'data' => $this->formatBatchResponse($committedBatch),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $batch = ImportBatch::with(['rows.errors'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail batch impor berhasil diambil.',
            'data' => $this->formatBatchResponse($batch),
        ]);
    }

    private function formatBatchResponse(ImportBatch $batch): array
    {
        $batch->loadMissing(['rows.errors']);

        $flatErrors = [];
        foreach ($batch->rows as $row) {
            foreach ($row->errors as $err) {
                $flatErrors[] = [
                    'row_number' => $row->row_number,
                    'identifier' => $row->source_key ?: $err->field_name,
                    'field_name' => $err->field_name,
                    'error_code' => $err->error_code,
                    'message' => $err->error_message,
                ];
            }
        }

        $arr = $batch->toArray();
        $arr['valid_count'] = $batch->valid_rows;
        $arr['duplicate_count'] = $batch->duplicate_rows;
        $arr['errors'] = $flatErrors;

        return $arr;
    }
}
