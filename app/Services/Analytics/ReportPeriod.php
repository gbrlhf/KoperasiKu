<?php

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReportPeriod
{
    public static function normalizePeriod(string $period): string
    {
        return match (strtolower(trim($period))) {
            'today', 'day', 'daily', 'harian', 'hari_ini' => 'today',
            'yesterday', 'kemarin' => 'yesterday',
            'two_days_ago', '2_days_ago', 'day_before_yesterday', 'kemarin_lusa' => 'two_days_ago',
            'last_7_days', '7_days', '7d', 'week', 'this_week', 'weekly', 'pekanan', 'pekan_ini' => 'last_7_days',
            'last_week', 'pekan_lalu', 'minggu_lalu', 'prev_week' => 'last_week',
            'last_30_days', '30_days', '30d' => 'last_30_days',
            'month', 'this_month', 'monthly', 'bulanan', 'bulan_ini' => 'month',
            'last_month', 'bulan_lalu', 'prev_month' => 'last_month',
            'year', 'this_year', 'yearly', 'tahunan', 'tahun_ini' => 'year',
            'last_year', 'tahun_lalu', 'prev_year' => 'last_year',
            'custom', 'kustom' => 'custom',
            default => 'month',
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function fromRequest(Request $request): array
    {
        $today = CarbonImmutable::now('Asia/Jakarta');
        $rawPeriod = (string) $request->query('period', '');
        $period = self::normalizePeriod($rawPeriod !== '' ? $rawPeriod : 'month');

        $lastWeek = $today->subWeek();
        $lastMonth = $today->subMonthNoOverflow();
        $lastYear = $today->subYearNoOverflow();

        [$defaultStart, $defaultEnd] = match ($period) {
            'today' => [$today->toDateString(), $today->toDateString()],
            'yesterday' => [$today->subDay()->toDateString(), $today->subDay()->toDateString()],
            'two_days_ago' => [$today->subDays(2)->toDateString(), $today->subDays(2)->toDateString()],
            'last_7_days' => [$today->subDays(6)->toDateString(), $today->toDateString()],
            'last_week' => [
                $lastWeek->startOfWeek(CarbonInterface::MONDAY)->toDateString(),
                $lastWeek->endOfWeek(CarbonInterface::SUNDAY)->toDateString(),
            ],
            'last_30_days' => [$today->subDays(29)->toDateString(), $today->toDateString()],
            'last_month' => [
                $lastMonth->startOfMonth()->toDateString(),
                $lastMonth->endOfMonth()->toDateString(),
            ],
            'year' => [$today->startOfYear()->toDateString(), $today->toDateString()],
            'last_year' => [
                $lastYear->startOfYear()->toDateString(),
                $lastYear->endOfYear()->toDateString(),
            ],
            default => [$today->startOfMonth()->toDateString(), $today->toDateString()],
        };

        $rawFrom = $request->filled('from')
            ? (string) $request->query('from')
            : ($request->filled('start') ? (string) $request->query('start') : null);
        $rawTo = $request->filled('to')
            ? (string) $request->query('to')
            : ($request->filled('end') ? (string) $request->query('end') : null);

        // Hanya pakai override tanggal eksplisit jika period='custom' atau parameter period tidak dikirim eksplisit
        $useExplicitDates = ($period === 'custom' || $rawPeriod === '') && ($rawFrom !== null || $rawTo !== null);
        $from = $useExplicitDates ? ($rawFrom ?? $defaultStart) : $defaultStart;
        $to = $useExplicitDates ? ($rawTo ?? $defaultEnd) : $defaultEnd;

        if ($period === 'custom' && ($rawFrom === null || $rawTo === null)) {
            if ($rawFrom !== null && $rawTo === null) {
                $to = $from;
            } elseif ($rawFrom === null && $rawTo !== null) {
                $from = $to;
            }
        }

        if (! self::isValidDate($from) || ! self::isValidDate($to) || $to < $from) {
            throw ValidationException::withMessages([
                'from' => 'Rentang tanggal laporan tidak valid (format Y-m-d dan from <= to).',
            ]);
        }

        return [$from, $to];
    }

    private static function isValidDate(string $date): bool
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }
}
