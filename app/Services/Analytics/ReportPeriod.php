<?php

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReportPeriod
{
    /**
     * @return array{0: string, 1: string}
     */
    public static function fromRequest(Request $request): array
    {
        $today = CarbonImmutable::now('Asia/Jakarta');
        $period = strtolower((string) $request->query('period', 'month'));

        $defaultStart = match ($period) {
            'day' => $today->toDateString(),
            'week' => $today->startOfWeek(CarbonImmutable::MONDAY)->toDateString(),
            'year' => $today->startOfYear()->toDateString(),
            default => $today->startOfMonth()->toDateString(),
        };
        $defaultEnd = $today->toDateString();

        $from = (string) $request->query('start', $defaultStart);
        $to = (string) $request->query('end', $defaultEnd);

        if (! self::isValidDate($from) || ! self::isValidDate($to) || $to < $from) {
            throw ValidationException::withMessages([
                'start' => 'Rentang tanggal laporan tidak valid (format Y-m-d dan start <= end).',
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
