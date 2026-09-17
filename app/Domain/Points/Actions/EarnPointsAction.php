<?php

namespace App\Domain\Points\Actions;

use App\Domain\Points\Events\PointsEarned;
use App\Models\Customer;
use App\Models\PointAccount;
use App\Models\PointTransaction;
use Illuminate\Support\Facades\DB;

/**
 * تراکنش Ledger امتیاز — فصل ۶-۴ سند معماری.
 *
 * موجودی هر مشتری فقط از جمع تراکنش‌ها به‌دست می‌آید و هیچ کدی موجودی را
 * مستقیم به‌روزرسانی نمی‌کند؛ این الگوی Ledger از افت حساب‌ها جلوگیری
 * می‌کند و ردگیری کامل دارد. delta منفی برای خرج/انقضا مجاز است.
 */
final class EarnPointsAction
{
    public function handle(
        Customer $customer,
        int $delta,
        string $type = PointTransaction::TYPE_EARN,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $description = null,
    ): int {
        return DB::transaction(function () use ($customer, $delta, $type, $referenceType, $referenceId, $description): int {
            $account = PointAccount::query()->firstOrCreate(
                ['customer_id' => $customer->getKey()],
                ['store_id' => $customer->store_id],
            );

            PointTransaction::query()->create([
                'store_id' => $customer->store_id,
                'customer_id' => $customer->getKey(),
                'point_account_id' => $account->getKey(),
                'delta' => $delta,
                'type' => $type,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'created_at' => now(),
            ]);

            $balance = $this->balance($customer);

            if ($delta > 0) {
                PointsEarned::dispatch($customer, $delta, $balance);
            }

            return $balance;
        });
    }

    /** موجودی فقط از جمع تراکنش‌ها — منبع واحد حقیقت */
    public function balance(Customer $customer): int
    {
        return (int) PointTransaction::query()
            ->where('customer_id', $customer->getKey())
            ->sum('delta');
    }
}
