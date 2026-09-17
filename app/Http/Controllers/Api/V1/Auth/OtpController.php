<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Authentication\Actions\RequestOtpAction;
use App\Domain\Authentication\Actions\VerifyOtpAction;
use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class OtpController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly RequestOtpAction $requestOtp,
        private readonly VerifyOtpAction $verifyOtp,
    ) {}

    /** POST /api/v1/auth/otp/request — درخواست کد یک‌بارمصرف (فصل ۸-۲) */
    public function request(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^09\d{9}$/'],
            'purpose' => ['sometimes', 'string', Rule::in(['auth', 'customer_login'])],
        ]);

        $result = $this->requestOtp->handle(
            $data['phone'],
            $data['purpose'] ?? 'auth',
        );

        return $this->ok($result);
    }

    /** POST /api/v1/auth/otp/verify — تأیید کد و صدور توکن Sanctum */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^09\d{9}$/'],
            'code' => ['required', 'string', 'digits:6'],
            'purpose' => ['sometimes', 'string', Rule::in(['auth', 'customer_login'])],
        ]);

        $result = $this->verifyOtp->handle(
            $data['phone'],
            $data['code'],
            $data['purpose'] ?? 'auth',
        );

        return $this->ok([
            'token' => $result['token'],
            'is_new_user' => $result['is_new'],
            'user' => [
                'id' => $result['user']->id,
                'phone' => $result['user']->phone,
                'role' => $result['user']->role,
            ],
        ]);
    }
}
