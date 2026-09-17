<?php

namespace App\Domain\Referral\Actions;

use App\Domain\Referral\Events\ReferralRegistered;
use App\Domain\Referral\Services\ReferralTierAwarder;
use App\Models\Customer;
use App\Models\Referral;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

/**
 * ثبت دعوت دوست — فصل ۱۰ سند معماری.
 *
 * قواعد:
 *  - دعوت‌کننده و دعوت‌شده باید هم‌Store باشند (کد دعوت قلمرو Store دارد)
 *  - دعوت خودی (کد خود مشتری) رد می‌شود
 *  - هر دعوت‌شده در هر Store فقط یک‌بار شمرده می‌شود (unique) → فراخوانی
 *    تکراری Idempotent است و جایزه جدیدی نمی‌سازد
 *
 * پس از ثبت، جوایز پله‌ای ۱/۳/۵ دعوت برای دعوت‌کننده پردازش می‌شود.
 *
 * @return array{status: string, referrer: Customer, awarded: array|null, total: int}
 */
final class CompleteReferralAction
{
    public function __construct(
        private readonly ReferralTierAwarder $awarder,
    ) {}

    public function handle(Customer $invited, string $code): array
    {
        $normalized = mb_strtoupper(trim($code));

        $referrer = Customer::query()
            ->where('store_id', $invited->store_id)
            ->where('referral_code', $normalized)
            ->first();

        if ($referrer === null) {
            throw new ApiException('REFERRAL_INVALID', 'کد دعوت معتبر نیست.', 422);
        }

        if ((int) $referrer->getKey() === (int) $invited->getKey()) {
            throw new ApiException('REFERRAL_INVALID', 'کد دعوت معتبر نیست.', 422);
        }

        $existing = Referral::query()
            ->where('store_id', $invited->store_id)
            ->where('invited_id', $invited->getKey())
            ->first();

        if ($existing !== null) {
            $total = Referral::query()
                ->where('store_id', $invited->store_id)
                ->where('referrer_id', $existing->referrer_id)
                ->count();

            return ['status' => 'already', 'referrer' => $referrer, 'awarded' => null, 'total' => $total];
        }

        [$referral, $awarded] = DB::transaction(function () use ($invited, $referrer): array {
            $referral = Referral::query()->create([
                'store_id' => $invited->store_id,
                'referrer_id' => $referrer->getKey(),
                'invited_id' => $invited->getKey(),
                'status' => Referral::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            $invited->forceFill(['referred_by' => $referrer->getKey()])->save();

            $awarded = $this->awarder->process($referrer, $referral);

            return [$referral, $awarded];
        });

        ReferralRegistered::dispatch($referral);

        $total = $awarded['total'] ?? (int) Referral::query()
            ->where('store_id', $invited->store_id)
            ->where('referrer_id', $referrer->getKey())
            ->count();

        return ['status' => 'completed', 'referrer' => $referrer, 'awarded' => $awarded, 'total' => $total];
    }
}
