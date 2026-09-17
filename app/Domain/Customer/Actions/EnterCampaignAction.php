<?php

namespace App\Domain\Customer\Actions;

use App\Domain\Authentication\Services\OtpVerifier;
use App\Models\Campaign;
use App\Models\Customer;
use Illuminate\Support\Str;

/**
 * ورود مشتری به کمپین — فصل ۹-۱: کمترین اصطکاک؛ هیچ فرمی جز شماره موبایل نیست.
 *
 * مشتری در قلمرو Storeِ کمپین با موبایل یکتا شناسه می‌گیرد و توکنِ
 * محدودِ دامنه‌دار (abilities: customer) دریافت می‌کند که فقط به
 * Sessionها و کدهای خودش دسترسی دارد (فصل ۸-۱).
 */
final class EnterCampaignAction
{
    public function __construct(
        private readonly OtpVerifier $verifier,
    ) {}

    public function handle(Campaign $campaign, string $phone, string $code): array
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

        $token = $customer->createToken('campaign', ['customer'])->plainTextToken;

        return [
            'customer' => $customer,
            'token' => $token,
            'is_new' => $customer->wasRecentlyCreated,
        ];
    }
}
