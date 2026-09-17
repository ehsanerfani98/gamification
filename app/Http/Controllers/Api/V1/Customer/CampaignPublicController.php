<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Domain\Authentication\Actions\RequestOtpAction;
use App\Domain\Campaign\Events\CampaignViewed;
use App\Domain\Campaign\Services\CampaignRuleEngine;
use App\Domain\Customer\Actions\EnterCampaignAction;
use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * مسیرهای عمومی PWA کمپین — فصل ۸-۲ (بدون احراز هویت پنل).
 * افشای داده حداقلی: پیکربندی امنِ عمومی (بدون وزن و مرجع جایزه).
 */
final class CampaignPublicController extends Controller
{
    use ApiResponse;

    /** GET /api/v1/c/{slug} — جزئیات عمومی کمپین برای PWA */
    public function show(string $slug, Request $request): JsonResponse
    {
        $campaign = Campaign::query()
            ->with('game:id,code,name', 'configuration')
            ->where('slug', $slug)
            ->first();

        if ($campaign === null) {
            abort(404);
        }

        $data = [
            'slug' => $campaign->slug,
            'title' => $campaign->title,
            'status' => $campaign->status,
            'is_playable' => $campaign->isPlayable(),
            'game' => $campaign->game ? [
                'code' => $campaign->game->code,
                'name' => $campaign->game->name,
            ] : null,
            // پیکربندی امنِ عمومی — وزن‌ها و reward_refها حذف شده‌اند (فصل ۸-۱)
            'config' => $campaign->configuration?->toPublicArray(),
            'theme' => $campaign->theme,
            'rules_summary' => [
                'daily_plays' => 1,
            ],
        ];

        // اگر مشتری توکن داشته باشد، باقیمانده سهمش هم برمی‌گردد
        if ($customer = $this->currentCustomer($request)) {
            $data['rules_summary']['remaining'] = app(CampaignRuleEngine::class)
                ->remainingToday($campaign, $customer);
        }

        // مرحله ۱ قیف Analytics — فصل ۱۰ (Sprint 5)
        CampaignViewed::dispatch($campaign);

        return $this->ok($data);
    }

    /** POST /api/v1/c/{slug}/otp — درخواست کد ورود مشتری */
    public function otp(string $slug, Request $request): JsonResponse
    {
        $campaign = Campaign::query()->where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^09\d{9}$/'],
        ]);

        $result = app(RequestOtpAction::class)
            ->handle($data['phone'], 'customer_login');

        return $this->ok($result);
    }

    /** POST /api/v1/c/{slug}/enter — تأیید کد و صدور توکن محدود مشتری (فصل ۹-۱) */
    public function enter(string $slug, Request $request): JsonResponse
    {
        $campaign = Campaign::query()->where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^09\d{9}$/'],
            'code' => ['required', 'string', 'digits:6'],
            // کد دعوت اختیاری دوست — فصل ۱۰ (Sprint 5)
            'referral_code' => ['nullable', 'string', 'max:20'],
        ]);

        $result = app(EnterCampaignAction::class)->handle(
            $campaign,
            $data['phone'],
            $data['code'],
            $data['referral_code'] ?? null,
        );

        return $this->ok([
            'token' => $result['token'],
            'is_new' => $result['is_new'],
            'customer' => [
                'id' => $result['customer']->id,
                'phone' => $result['customer']->phone,
                'referral_code' => $result['customer']->referral_code,
            ],
        ]);
    }

    private function currentCustomer(Request $request): ?Customer
    {
        // توکن مشتری به‌صورت اختیاری پذیرفته می‌شود (Bearer)
        if ($request->bearerToken() === null) {
            return null;
        }

        /** @var Customer|null $customer */
        $customer = auth('customer')->user();

        return $customer;
    }
}
