<?php

namespace Tests\Feature\Domain\Store;

use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMerchants;
use Tests\TestCase;

/**
 * تست Store و مالکیت — فصل ۲-۳ و ۸-۲ سند معماری.
 */
final class StoreTest extends TestCase
{
    use CreatesMerchants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(); // Planها و بازی‌های MVP
    }

    public function test_merchant_creates_store_and_gets_free_subscription(): void
    {
        $token = $this->merchantToken();

        $response = $this->withToken($token)->postJson('/api/v1/stores', [
            'name' => 'فروشگاه من',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['store' => ['id', 'name', 'slug', 'subscription']]]);

        // هر Store جدید خودکار روی Plan رایگان شروع می‌کند (فصل ۷-۱)
        $response->assertJsonPath('data.subscription.status', 'active')
            ->assertJsonPath('data.subscription.plan.slug', 'free');

        $this->assertDatabaseHas('stores', ['name' => 'فروشگاه من', 'status' => 'active']);
    }

    public function test_store_slug_is_unique(): void
    {
        $token = $this->merchantToken();

        $first = $this->withToken($token)->postJson('/api/v1/stores', ['name' => 'فروشگاه']);
        $second = $this->withToken($token)->postJson('/api/v1/stores', ['name' => 'فروشگاه']);

        $first->assertCreated();
        $second->assertCreated();

        $this->assertNotSame(
            $first->json('data.store.slug'),
            $second->json('data.store.slug'),
        );
    }

    public function test_lists_only_own_stores(): void
    {
        [$tokenA] = $this->createMerchantWithStore('09121110001');
        [$tokenB] = $this->createMerchantWithStore('09121110002');

        $listA = $this->withToken($tokenA)->getJson('/api/v1/stores')->assertOk();
        $listB = $this->withToken($tokenB)->getJson('/api/v1/stores')->assertOk();

        $this->assertCount(1, $listA->json('data.stores'));
        $this->assertCount(1, $listB->json('data.stores'));
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/v1/stores', ['name' => 'X'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_show_own_store_returns_200(): void
    {
        [$token, $store] = $this->createMerchantWithStore();

        $this->withToken($token)
            ->getJson("/api/v1/stores/{$store['id']}")
            ->assertOk()
            ->assertJsonPath('data.store.id', $store['id']);
    }

    public function test_slug_can_be_chosen_explicitly(): void
    {
        $token = $this->merchantToken();

        $this->withToken($token)->postJson('/api/v1/stores', [
            'name' => 'نوروزی',
            'slug' => 'nowruz-shop',
        ])->assertCreated()->assertJsonPath('data.store.slug', 'nowruz-shop');

        $this->assertDatabaseHas(Store::class, ['slug' => 'nowruz-shop']);
    }
}
