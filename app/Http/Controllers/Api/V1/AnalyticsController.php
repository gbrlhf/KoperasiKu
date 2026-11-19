<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsQueryService;
use App\Services\Analytics\ReportExporter;
use App\Services\Analytics\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AnalyticsController extends Controller
{
    public function __construct(private AnalyticsQueryService $analytics) {}

    private function ready(): void
    {
        // Avoid returning successful zero KPIs when the PIC D warehouse is not installed.
        if (! $this->analytics->installed()) {
            abort(503, 'Warehouse PIC D belum diinstal. Jalankan migrasi PostgreSQL.');
        }
    }

    private function period(Request $request): array
    {
        return ReportPeriod::fromRequest($request);
    }

    public function summary(Request $request)
    {
        $this->ready();
        [$from,$to] = $this->period($request);

        return response()->json($this->analytics->readSnapshot(fn () => $this->analytics->summary($from, $to)));
    }

    public function financial(Request $request)
    {
        if ($request->query('format', 'json') !== 'json') {
            return $this->export($request, app(ReportExporter::class));
        }
        $this->ready();
        [$from,$to] = $this->period($request);

        return response()->json($this->analytics->readSnapshot(function () use ($request, $from, $to) {
            return array_merge($this->analytics->summary($from, $to), [
                'frequency' => $request->query('frequency', 'day'),
                'rows' => $this->analytics->trend($from, $to, $request->query('frequency', 'day')),
            ]);
        }));
    }

    public function topProducts(Request $request)
    {
        $this->ready();
        [$from,$to] = $this->period($request);
        $limit = min(100, max(1, (int) $request->query('limit', 10)));

        return response()->json($this->analytics->readSnapshot(function () use ($from, $to, $limit) {
            $summary = $this->analytics->summary($from, $to);

            return ['period' => $summary['period'],
                'data' => $this->analytics->topProducts($from, $to, $limit),
                'last_updated_at' => $summary['last_updated_at'], 'is_stale' => $summary['is_stale'],
                'data_quality' => $summary['data_quality']];
        }));
    }

    public function trend(Request $request)
    {
        $this->ready();
        [$from,$to] = $this->period($request);
        $freq = $request->query('frequency', 'day');
        if (! in_array($freq, ['day', 'week', 'month', 'year'], true)) {
            throw ValidationException::withMessages(['frequency' => 'Gunakan day/week/month/year.']);
        }

        return response()->json($this->analytics->readSnapshot(fn () => array_merge($this->analytics->summary($from, $to), ['frequency' => $freq, 'data' => $this->analytics->trend($from, $to, $freq)])));
    }

    public function categories(Request $request)
    {
        $this->ready();
        [$from, $to] = $this->period($request);

        return response()->json($this->analytics->readSnapshot(fn () => array_merge(
            $this->analytics->summary($from, $to),
            ['data' => $this->analytics->categories($from, $to)]
        )));
    }

    public function aging(Request $request)
    {
        $this->ready();
        $asof = $request->query('as_of', CarbonImmutable::now('Asia/Jakarta')->toDateString());
        Validator::make(['as_of' => $asof], ['as_of' => 'required|date_format:Y-m-d'])->validate();

        return response()->json($this->analytics->readSnapshot(fn () => $this->analytics->aging($asof)));
    }

    public function forecasts(Request $request)
    {
        $this->ready();
        $horizonRaw = (string) $request->query('horizon', '7');
        $primaryHorizon = (trim($horizonRaw) === '30') ? 30 : 7;

        return response()->json([
            'success' => true,
            'data' => $this->analytics->readSnapshot(fn () => $this->analytics->forecasts($primaryHorizon)),
        ]);
    }

    public function export(Request $request, ReportExporter $exporter)
    {
        $this->ready();
        [$from,$to] = $this->period($request);
        $format = $request->query('format', 'xlsx');
        if (! in_array($format, ['xlsx', 'csv', 'pdf'], true)) {
            throw ValidationException::withMessages(['format' => 'Ekspor: xlsx, csv, atau pdf.']);
        }
        $data = $this->analytics->readSnapshot(fn () => $this->analytics->summary($from, $to));
        if (! $data['ready']) {
            abort(503, 'Belum ada hasil ETL sukses yang dapat diekspor.');
        }
        $bytes = match ($format) {
            'xlsx' => $exporter->xlsx($data),
            'csv' => $exporter->csv($data),
            'pdf' => $exporter->pdf($data),
        };
        $mime = match ($format) {
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv; charset=utf-8',
            'pdf' => 'application/pdf',
        };

        return response($bytes, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="koperadata-keuangan-'.$from.'-'.$to.'.'.$format.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
