<?php

namespace Tests\Concerns;

trait CreatesMerchants
{
    /** درخواست OTP + تأیید → توکن Merchant */
    protected function merchantToken(string $phone = '09121110000'): string
    {
        $request = $this->postJson('/api/v1/auth/otp/request', ['phone' => $phone]);

        $code = $request->json('data.debug_code');

        $verify = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => $phone,
            'code' => $code,
        ]);

        return $verify->json('data.token');
    }

    /** ساخت Merchant با یک Store (روی Plan رایگان) → [token, store] */
    protected function createMerchantWithStore(string $phone = '09121110000'): array
    {
        $token = $this->merchantToken($phone);

        $store = $this->withToken($token)->postJson('/api/v1/stores', [
            'name' => 'فروشگاه تست',
        ])->assertCreated();

        return [$token, $store->json('data.store')];
    }
}
