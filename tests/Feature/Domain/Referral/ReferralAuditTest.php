<?php

namespace Tests\Feature\Domain\Referral;

use App\Domain\Referral\Events\ReferralRewardGranted;
use App\Models\AuditLog;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMerchants;
use Tests\TestCase;

/**
 * رگرسیون Sprint 7: Listener «AuditReferralReward» باید روی رخداد واقعی
 * App\Domain\Referral\Events\ReferralRewardGranted ثبت شود (قبلاً به دلیل
 * نبود use statement، listener زیر نام کلاس اشتباه ثبت شده بود و هرگز
 * فعال نمی‌شد — بی‌صدا رد می‌شد چون تستی پوششش نمی‌داد).
 */
final class ReferralAuditTest extends TestCase
{
    use CreatesMerchants, RefreshDatabase;

    public function test_referral_reward_is_recorded_in_audit_log(): void
    {
        $this->seed();

        [, $store] = $this->createMerchantWithStore();

        $referrer = Customer::query()->create([
            'store_id' => $store['id'],
            'phone' => '09122220000',
            'name' => 'دعوت‌کننده',
        ]);

        // dispatch واقعی رخداد — باید AuditReferralReward را فعال کند
        ReferralRewardGranted::dispatch($referrer, 100, 1, 1);

        $this->assertDatabaseHas(AuditLog::class, [
            'action' => 'referral.rewarded',
            'subject_type' => Customer::class,
            'subject_id' => $referrer->id,
            'actor_type' => 'customer',
        ]);
    }
}
