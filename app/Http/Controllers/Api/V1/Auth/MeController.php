<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * مشخصات کاربر جاری پنل — Sprint 8.
 * پنل پس از refresh صفحه نقش کاربر را با این Endpoint بازمی‌خواند
 * تا منوی «تنظیمات سایت» فقط برای Admin نمایش داده شود.
 */
final class MeController extends Controller
{
    use ApiResponse;

    /** GET /api/v1/auth/me */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->ok([
            'user' => [
                'id' => $user->id,
                'phone' => $user->phone,
                'role' => $user->role,
            ],
        ]);
    }
}
