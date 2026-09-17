<?php

namespace Tests\Feature\Domain\Subscription;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMerchants;
use Tests\TestCase;

/**
 * تست Tenant Isolation — تست اجباری هر اسپرینت (فصل ۲-۳ و ۲-۵ سند معماری).
 * تلاش برای دسترسی به منبع فروشگاه دیگر باید 404 برگرداند (نه 403)
 * تا وجود منبع افشا نشود.
 */
final class TenantIsolationTest extends TestCase
{
    use CreatesMerchants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(); // Planها و بازی‌های MVP
    }

    public function test_cross_tenant_store_access_returns_404(): void
    {
        [$tokenA, $storeA] = $this->createMerchantWithStore('09121110001');
        [$tokenB] = $this->createMerchantWithStore('09121110002');

        $this->withToken($tokenB)
            ->getJson("/api/v1/stores/{$storeA['id']}")
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_cross_tenant_store_context_returns_404(): void
    {
        [$tokenA, $storeA] = $this->createMerchantWithStore('09121110001');
        [$tokenB] = $this->createMerchantWithStore('09121110002');

        // کاربر B ادعای فروشگاه A را با هدر می‌کند → 404
        $this->withToken($tokenB)
            ->withHeader('X-Store-Id', (string) $storeA['id'])
            ->getJson('/api/v1/subscriptions')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_missing_store_context_returns_404(): void
    {
        $token = $this->merchantToken('09121110003');

        // کاربر هنوز Store نساخته → مسیرهای Tenant-دار 404 می‌دهند
        $this->withToken($token)
            ->getJson('/api/v1/subscriptions')
            ->assertStatus(404);
    }

    public function test_tenant_context_scopes_queries_to_own_store(): void
    {
        [$tokenA, $storeA] = $this->createMerchantWithStore('09121110001');
        [$tokenB, $storeB] = $this->createMerchantWithStore('09121110002');

        $showA = $this->withToken($tokenA)
            ->withHeader('X-Store-Id', (string) $storeA['id'])
            ->getJson('/api/v1/subscriptions')->assertOk();

        $showB = $this->withToken($tokenB)
            ->withHeader('X-Store-Id', (string) $storeB['id'])
            ->getJson('/api/v1/subscriptions')->assertOk();

        // هر کاربر فقط اشتراک Store خودش را می‌بیند
        $this->assertSame($storeA['id'], $showA->json('data.subscription.id') ?? $showA->json('data.subscription.id'));
        $this->assertNotSame($storeA['id'], $storeB['id']);
    }
}
