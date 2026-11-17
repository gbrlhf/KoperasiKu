<?php

namespace Tests\Published;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Run after integrated_bc.py. Reads its published mart without migrating/resetting it. */
class PicDPublishedReportsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'pgsql' || ! str_starts_with(DB::connection()->getDatabaseName(), 'picd_test_')) {
            throw new \RuntimeException('Published report tests require a dedicated picd_test_* database.');
        }
        DB::beginTransaction();
        $user = User::factory()->create();
        DB::table('user_roles')->insert(['user_id' => $user->id, 'role_code' => 'MANAGER']);
        $this->actingAs($user)->withoutVite();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_api_and_blade_use_published_financial_values(): void
    {
        $end = now('Asia/Jakarta')->toDateString();
        $start = now('Asia/Jakarta')->subDays(8)->toDateString();
        $query = '?start='.$start.'&end='.$end;
        $this->getJson('/api/v1/analytics/summary'.$query)->assertOk()
            ->assertJsonPath('metrics.sales_revenue', '200000.00')
            ->assertJsonPath('metrics.cash_receipts', '75000.00')
            ->assertJsonPath('metrics.receivables_outstanding_as_of_end', '125000.00')
            ->assertJsonPath('metrics.estimated_net_profit', '50000.00')
            ->assertJsonPath('is_stale', false);
        foreach (['analytics/top-products', 'analytics/categories', 'analytics/trend', 'reports/financial', 'manager/receivables/aging'] as $endpoint) {
            $this->getJson('/api/v1/'.$endpoint.$query)->assertOk();
        }
        foreach (['day', 'week', 'month', 'year'] as $frequency) {
            $rows = $this->getJson('/api/v1/reports/financial'.$query.'&frequency='.$frequency)
                ->assertOk()->json('rows');
            $this->assertSame(200000, array_sum(array_map(fn ($row) => (int) $row['sales_revenue'], $rows)));
            $this->assertSame(75000, array_sum(array_map(fn ($row) => (int) $row['cash_receipts'], $rows)));
        }
        $this->getJson('/api/v1/analytics/summary?start=2026-11-31&end=2026-12-01')->assertUnprocessable();
        foreach (['csv', 'xlsx', 'pdf'] as $format) {
            $response = $this->get('/api/v1/reports/financial/export'.$query.'&format='.$format)->assertOk();
            $this->assertNotEmpty($response->getContent());
        }
        foreach (['dashboard', 'piutang', 'laporan'] as $page) {
            $this->get('/manager/'.$page.$query)->assertOk()->assertDontSee('Masuk dengan akun Manajer');
        }
    }

    public function test_failed_run_keeps_last_success_but_marks_report_stale(): void
    {
        DB::table('analytics.etl_runs')->insert(['run_id' => (string) Str::uuid(), 'status' => 'FAILED']);
        $this->getJson('/api/v1/analytics/summary?period=month')->assertOk()->assertJsonPath('is_stale', true);
    }
}
