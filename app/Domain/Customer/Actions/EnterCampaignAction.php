<?php

namespace App\Domain\Customer\Actions;

use App\Domain\Authentication\Services\OtpVerifier;
use App\Domain\Customer\Events\CustomerEnteredCampaign;
use App\Domain\Referral\Actions\CompleteReferralAction;
use App\Models\Campaign;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ورود مشتری به کمپین — فصل ۹-۱: کمترین اصطکاک؛ هیچ فرمی جز شماره موبایل نیست.
 *
 * مشتری در قلمرو Storeِ کمپین با موبایل یکتا شناسه می‌گیرد و توکنِ
 * محدودِ دامنه‌دار (abilities: customer) دریافت می‌کند که فقط به
 * Sessionها و کدهای خودش دسترسی دارد (فصل ۸-۱).
 *
 * Referral (فصل ۱۰): کد دعوت اختیاری است؛ دعوت نامعتبر هرگز ورود را
 * نمی‌شکند (اصطکاک صفر ورود) و فقط Log می‌شود. مسیر صریح خطا،
 * POST /referrals/apply است.
 */
final class EnterCampaignAction
{
    public function __construct(
        private readonly OtpVerifier $verifier,
    ) {}

    public function handle(Campaign $campaign, string $phone, string $code, ?string $referralCode = null): array
    {
        $this->verifier->verify($phone, $code, 'customer_login');

        /** @var Customer $customer */
        $customer = Customer::query()->firstOrCreate(
            ['store_id' => $campaign->store_id, 'phone' => $phone],
            [
                'referral_code' => strtoupper(Str::random(6)),
                'last_seen_at' => now(),
            ],
        );

        $customer->markSeen();

        if ($referralCode !== null && trim($referralCode) !== '') {
            $this->applyReferral($customer, $referralCode);
        }

        CustomerEnteredCampaign::dispatch($customer, $campaign, $customer->wasRecentlyCreated);

        $token = $customer->createToken('campaign', ['customer'])->plainTextToken;

        return [
            'customer' => $customer,
            'token' => $token,
            'is_new' => $customer->wasRecentlyCreated,
        ];
    }

    /** دعوت دوست — شکست آن غیرمسدودکننده است (فصل ۹-۱: ورود بدون اصطکاک) */
    private function applyReferral(Customer $customer, string $referralCode): void
    {
        try {
            app(CompleteReferralAction::class)->handle($customer, $referralCode);
        } catch (\Throwable $e) {
            Log::info('referral.enter_apply_ignored', [
                'customer_id' => $customer->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
