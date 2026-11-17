<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RequireManager;
use App\Services\Analytics\AnalyticsQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** Manager frontend integration with KoperasiKu's existing Blade layout. */
class AnalyticsPageController extends Controller
{
    public function __construct(private AnalyticsQueryService $analytics) {}

    private function loadData(Request $request): array
    {
        // Mock role selector in the base UI cannot read confidential financial data.
        if (! RequireManager::allowed($request)) {
            return ['authorized' => false, 'data' => null, 'trend' => [], 'top' => [], 'aging' => null];
        }
        if (! $this->analytics->installed()) {
            return ['authorized' => true, 'data' => null, 'trend' => [], 'top' => [], 'aging' => null];
        }
        $today = CarbonImmutable::now('Asia/Jakarta');
        $from = $today->startOfMonth()->toDateString();
        $to = $today->toDateString();

        return $this->analytics->readSnapshot(fn () => [
            'authorized' => true,
            'data' => $this->analytics->summary($from, $to),
            'trend' => $this->analytics->trend($from, $to, 'day'),
            'top' => $this->analytics->topProducts($from, $to, 6),
            'aging' => $this->analytics->aging($to),
        ]);
    }

    public function dashboard(Request $request)
    {
        return view('manager.dashboard', $this->loadData($request));
    }

    public function receivables(Request $request)
    {
        return view('manager.piutang', $this->loadData($request));
    }

    public function reports(Request $request)
    {
        // This route is itself protected by auth + picd.manager.
        $from = $request->query('start', CarbonImmutable::now('Asia/Jakarta')->startOfMonth()->toDateString());
        $to = $request->query('end', CarbonImmutable::now('Asia/Jakarta')->toDateString());
        if (! is_string($from) || ! is_string($to) || ! preg_match('/^\d{4}-\d\d-\d\d$/', $from) ||
            ! preg_match('/^\d{4}-\d\d-\d\d$/', $to) || ! checkdate((int) substr($from, 5, 2), (int) substr($from, 8, 2), (int) substr($from, 0, 4)) ||
            ! checkdate((int) substr($to, 5, 2), (int) substr($to, 8, 2), (int) substr($to, 0, 4)) || $to < $from) {
            abort(422, 'Tanggal laporan tidak valid');
        }
        if (! $this->analytics->installed()) {
            return view('manager.laporan', ['data' => null, 'start' => $from, 'end' => $to, 'top' => []]);
        }

        return view('manager.laporan', $this->analytics->readSnapshot(fn () => [
            'data' => $this->analytics->summary($from, $to),
            'start' => $from, 'end' => $to, 'top' => $this->analytics->topProducts($from, $to, 20),
        ]));
    }
}
