<?php

namespace App\Domain\Authentication\Actions;

use App\Domain\Authentication\Services\OtpVerifier;
use App\Models\AuditLog;
use App\Models\OtpCode;
use App\Models\User;

/**
 * تأیید OTP پنل (Merchant/Admin) و صدور توکن — فصل ۸-۲.
 * کاربر جدید به‌صورت خودکار ساخته می‌شود (ثبت‌نام بدون فرم اضافه).
 */
final class VerifyOtpAction
{
    public function __construct(
        private readonly OtpVerifier $verifier,
    ) {}

    public function handle(string $phone, string $code, string $purpose = OtpCode::PURPOSE_AUTH): array
    {
        $this->verifier->verify($phone, $code, $purpose);

        $user = User::query()->firstOrCreate(
            ['phone' => $phone],
            ['role' => $purpose === OtpCode::PURPOSE_CUSTOMER_LOGIN ? 'customer' : 'merchant'],
        );

        AuditLog::record('auth.login', $user, $user);

        $abilities = $purpose === OtpCode::PURPOSE_CUSTOMER_LOGIN
            ? ['customer']
            // Admin علاوه بر دسترسی‌های کامل پنل، ability «admin» برای تنظیمات سایت می‌گیرد (Sprint 8)
            : ($user->role === 'admin' ? ['merchant', 'admin'] : ['merchant']);

        $token = $user->createToken(
            $purpose === OtpCode::PURPOSE_CUSTOMER_LOGIN ? 'campaign' : 'panel',
            $abilities,
        )->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'is_new' => $user->wasRecentlyCreated,
        ];
    }
}
