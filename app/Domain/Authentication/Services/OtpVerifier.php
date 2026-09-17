<?php

namespace App\Domain\Authentication\Services;

use App\Models\AuditLog;
use App\Models\OtpCode;
use App\Support\Exceptions\ApiException;

/**
 * هسته مشترک تأیید OTP — فصل ۲-۵ سند معماری.
 *
 * هم Merchant (پنل) و هم Customer (کمپین عمومی) از همین منطق عبور می‌کنند:
 * سقف تلاش، hash_equals، مصرف اتمی کد.
 */
final class OtpVerifier
{
    public function verify(string $phone, string $code, string $purpose = OtpCode::PURPOSE_AUTH): OtpCode
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

            AuditLog::record('auth.otp_failed', null, $otp, ['purpose' => $purpose]);

            throw new ApiException('OTP_INVALID', 'کد واردشده صحیح نیست.');
        }

        // مصرف اتمی — دفاع دوم در برابر مصرف هم‌زمان یک کد
        if (! $otp->consume()) {
            throw new ApiException('OTP_CONSUMED', 'این کد قبلاً استفاده شده است.', 409);
        }

        return $otp;
    }
}
