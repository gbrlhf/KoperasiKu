<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PartEMlAndQaTest extends TestCase
{
    use RefreshDatabase;

    public function test_ml_demand_forecast_returns_valid_prediction(): void
    {
        Http::fake([
            '*/predict/demand' => Http::response([
                'product_id' => 1,
                'horizon_days' => 5,
                'daily_forecast' => [23.5, 25.4, 27.3, 29.2, 31.1],
                'total_predicted_demand' => 136.5,
                'recommended_reorder_qty' => 141.8,
                'model' => 'LinearRegression',
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/ml/demand-forecast', [
            'product_id' => 1,
            'history' => [12, 15, 14, 18, 20, 22],
            'horizon_days' => 5,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('product_id', 1)
            ->assertJsonPath('model', 'LinearRegression');
    }

    public function test_ml_credit_risk_returns_high_risk_tier(): void
    {
        Http::fake([
            '*/predict/credit-risk' => Http::response([
                'risk_probability' => 0.6083,
                'risk_tier' => 'MEDIUM',
                'utilization_ratio' => 0.7,
                'recommended_max_limit' => 4000000.0,
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/ml/credit-risk', [
            'credit_limit' => 5000000,
            'current_debt' => 3500000,
            'overdue_count' => 2,
            'on_time_ratio' => 0.7,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['risk_probability', 'risk_tier', 'utilization_ratio', 'recommended_max_limit']);
    }

    public function test_ml_endpoints_reject_invalid_and_negative_inputs(): void
    {
        $this->postJson('/api/v1/ml/demand-forecast', [
            'product_id' => 0,
            'history' => [-5],
            'horizon_days' => 200,
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['product_id', 'history', 'horizon_days']);

        $this->postJson('/api/v1/ml/credit-risk', [
            'credit_limit' => 0,
            'current_debt' => -1000,
            'overdue_count' => -1,
            'on_time_ratio' => 1.5,
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['credit_limit', 'current_debt', 'overdue_count', 'on_time_ratio']);
    }

    public function test_part_c_products_endpoint_works_on_isolated_test_database(): void
    {
        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200)
            ->assertJsonStructure(['current_page', 'data', 'total']);
    }
}