<?php

namespace Tests\Feature;

use Tests\TestCase;

class TransactionE2ETest extends TestCase
{
    public function test_checkout_endpoint_creates_order_and_pickup_code(): void
    {
        $response = $this->postJson('/checkout', [
            'payment_choice' => 'CREDIT',
            'channel' => 'ONLINE_PICKUP',
            'items' => [
                ['product_id' => 'SEM-001', 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'order_id',
                    'order_no',
                    'pickup_code',
                    'status',
                    'payment_choice',
                    'total_amount',
                ]
            ]);

        $pickupCode = $response->json('data.pickup_code');
        $this->assertStringStartsWith('PKP-', $pickupCode);

        // Test Confirm Pickup
        $pickupResponse = $this->postJson('/cashier/confirm-pickup', [
            'pickup_code' => $pickupCode,
        ]);

        $pickupResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'COMPLETED',
                ]
            ]);
    }

    public function test_checkout_with_storefront_prd_code_like_prd_005(): void
    {
        $response = $this->postJson('/checkout', [
            'payment_choice' => 'CASH',
            'channel' => 'ONLINE_PICKUP',
            'items' => [
                ['product_id' => 'PRD-005', 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'order_id',
                    'order_no',
                    'pickup_code',
                    'status',
                    'payment_choice',
                    'total_amount',
                ]
            ]);

        $this->assertEquals(28500, (float) $response->json('data.total_amount'));
    }

    public function test_checkout_with_sku_ssu_001(): void
    {
        $response = $this->postJson('/checkout', [
            'payment_choice' => 'CASH',
            'channel' => 'ONLINE_PICKUP',
            'items' => [
                ['product_id' => 'SSU-001', 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $pickupCode = $response->json('data.pickup_code');
        $this->assertNotEmpty($pickupCode);
    }

    public function test_pos_direct_sale_endpoint(): void
    {
        $response = $this->postJson('/cashier/pos-sale', [
            'payment_choice' => 'CASH',
            'channel' => 'POS',
            'items' => [
                ['product_id' => 'MNY-001', 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'COMPLETED',
                    'channel' => 'POS',
                ]
            ]);
    }

    public function test_product_barcode_lookup_endpoint(): void
    {
        $response = $this->getJson('/api/v1/products/lookup?barcode=899100100001');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'sku' => 'SEM-001',
                    'barcode' => '899100100001',
                    'name' => 'Beras Premium Ramos 5 Kg',
                ]
            ]);
    }
}
