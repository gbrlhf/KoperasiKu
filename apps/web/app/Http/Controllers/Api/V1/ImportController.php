<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Services\Import\ExcelImportService;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    private function actorId(Request $request): string
    {
        return (string) ($request->user()?->id ?? $request->header('X-Mock-User-Id', '00000000-0000-0000-0000-000000000001'));
    }

    public function index()
    {
        return response()->json(ImportBatch::latest()->paginate(20));
    }

    public function upload(Request $request, ExcelImportService $importService)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
            'import_type' => 'required|in:MEMBERS,SUPPLIERS,PRODUCTS,STOCK_OPENING',
            'cutoff_at' => 'nullable|date',
        ]);

        $batch = $importService->stageUpload(
            file: $request->file('file'),
            importType: $validated['import_type'],
            actorUserId: $this->actorId($request),
            cutoffAt: $validated['cutoff_at'] ?? null
        );

        return response()->json(['data' => $batch], 201);
    }

    public function show(string $id)
    {
        $batch = ImportBatch::with(['rows' => function ($q) {
            $q->orderBy('row_number')->limit(100)->with('errors');
        }])->findOrFail($id);

        return response()->json(['data' => $batch]);
    }

    public function revalidate(Request $request, string $id, ExcelImportService $importService)
    {
        $batch = ImportBatch::findOrFail($id);
        $mapping = $request->input('column_mapping');

        $updated = $importService->validateBatch($batch, is_array($mapping) ? $mapping : null);

        return response()->json(['data' => $updated]);
    }

    public function commit(Request $request, string $id, ExcelImportService $importService)
    {
        $batch = ImportBatch::findOrFail($id);
        $committed = $importService->commitBatch($batch, $this->actorId($request));

        return response()->json([
            'message' => 'Import batch berhasil di-commit secara idempotent.',
            'data' => $committed,
        ]);
    }
}
