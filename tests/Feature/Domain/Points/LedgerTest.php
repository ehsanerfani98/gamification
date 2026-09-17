<?php

namespace Tests\Feature\Domain\Points;

use App\Domain\Points\Actions\EarnPointsAction;
use App\Models\Customer;
use App\Models\PointTransaction;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Ledger امتیاز — فصل ۶-۴: موجودی فقط از جمع تراکنش‌ها؛ تراز همیشه برقرار.
 */
final class LedgerTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(string $phone = '09331117777'): Customer
    {
        $user = User::query()->create([
            'phone' => '09'.random_int(100000000, 999999999),
            'role' => 'merchant',
        ]);

        $store = Store::query()->create([
            'user_id' => $user->id,
            'name' => 'فروشگاه لجر',
            'slug' => 'ledger-'.strtolower(Str::random(6)),
        ]);

        return Customer::query()->create([
            'store_id' => $store->id,
            'phone' => $phone,
        ]);
    }

    public function test_balance_equals_sum_of_transactions(): void
    {
        $customer = $this->makeCustomer();
        $action = app(EarnPointsAction::class);

        $action->handle($customer, 50);
        $action->handle($customer, 30, description: 'بونوس');
        $action->handle($customer, -20, PointTransaction::TYPE_REDEEM, description: 'خرج در فروشگاه');

        $this->assertSame(60, $action->balance($customer));
        $this->assertDatabaseCount('point_transactions', 3);
        $this->assertDatabaseCount('point_accounts', 1); // حساب یک‌بار ساخته می‌شود
    }

    public function test_ledger_is_append_only(): void
    {
        $customer = $this->makeCustomer('09331118888');
        $action = app(EarnPointsAction::class);
        $action->handle($customer, 100);

        // هیچ APIای برای ویرایش تراکنش وجود ندارد؛ Append-Only (فصل ۳-۴)
        $transaction = PointTransaction::query()->where('customer_id', $customer->id)->first();

        $this->assertFalse($transaction->usesTimestamps());
        $this->assertSame(100, $action->balance($customer));
    }

    public function test_negative_balance_is_possible_but_tracked(): void
    {
        $customer = $this->makeCustomer('09331119999');
        $action = app(EarnPointsAction::class);
        $action->handle($customer, 10);
        $action->handle($customer, -25, PointTransaction::TYPE_REDEEM);

        // ردگیری کامل حتی برای منفی (تصحیح در لایه بالاتر انجام می‌شود)
        $this->assertSame(-15, $action->balance($customer));
    }
}
