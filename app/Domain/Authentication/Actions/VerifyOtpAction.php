<?php

namespace App\Domain\Authentication\Actions;

use App\Domain\Authentication\Services\OtpCodeHasher;
use App\Models\AuditLog;
use App\Models\OtpCode;
use App\Models\User;
use App\Support\Exceptions\ApiException;

/**
 * تأیید OTP و صدور توکن — فصل ۸-۲ (auth/otp/verify).
 *
 * دفاع‌ها: سقف تلاش ناموفق (قفل کد)، مقایسه hash_equals، مصرف اتمی کد
 * (هرگز دو توکن از یک کد ساخته نمی‌شود) و ثبت Audit.
 * کاربر جدید به‌صورت خودکار ساخته می‌شود (ثبت‌نام بدون فرم اضافه).
 */
final class VerifyOtpAction
{
    public function handle(string $phone, string $code, string $purpose = OtpCode::PURPOSE_AUTH): array
    {
        $maxAttempts = (int) config('gamification.otp.max_attempts', 5);

        $otp = OtpCode::valid($phone, $purpose)->first();

        if (! $otp) {
            throw new ApiException('OTP_NOT_FOUND', 'کد معتبری برای این شماره یافت نشد؛ دوباره درخواست دهید.');
        }

        if (! $otp->hasAttemptsLeft($maxAttempts)) {
            throw new ApiException('OTP_LOCKED', 'به‌دلیل تلاش‌های ناموفق زیاد، این کد قفل شده است.', 423);
        }

        if (! hash_equals($otp->code_hash, OtpCodeHasher::make($code))) {
            $otp->increment('attempts');

            AuditLog::record('auth.otp_failed', null, $otp);

            throw new ApiException('OTP_INVALID', 'کد واردشده صحیح نیست.');
        }

        // مصرف اتمی — دفاع دوم در برابر مصرف هم‌زمان یک کد
        if (! $otp->consume()) {
            throw new ApiException('OTP_CONSUMED', 'این کد قبلاً استفاده شده است.', 409);
        }

        $user = User::query()->firstOrCreate(
            ['phone' => $phone],
            ['role' => $purpose === OtpCode::PURPOSE_CUSTOMER_LOGIN ? 'customer' : 'merchant'],
        );

        AuditLog::record('auth.login', $user, $user);

        $abilities = $purpose === OtpCode::PURPOSE_CUSTOMER_LOGIN ? ['customer'] : ['merchant'];

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
