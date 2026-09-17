<?php

namespace App\Domain\Campaign\Services;

use App\Models\Campaign;
use App\Models\CampaignParticipation;
use App\Models\CampaignRule;
use App\Models\Customer;
use App\Models\GameSession;
use App\Support\Exceptions\ApiException;

/**
 * Campaign Rule Engine — قوانین مشارکت و سقف‌ها (فصل ۳-۳ و ۲-۵).
 *
 * شمارش مصرف روی Sessionهای شروع‌شده انجام می‌شود (نه فقط شرکت‌های کامل)
 * تا کاربر نتواند با رهاکردن Session، سهمیه را دور بزند.
 */
final class CampaignRuleEngine
{
    /** آیا مشتری اجازه شروع Session دارد؟ در غیر این صورت خطای ماشین‌خوان */
    public function assertCanPlay(Campaign $campaign, Customer $customer): void
    {
        // قانون روزانه (پیش‌فرض همه کمپین‌ها: روزی یک‌بار — ایندکس یکتا پنجره روزانه)
        $startedToday = GameSession::query()
            ->where('campaign_id', $campaign->getKey())
            ->where('customer_id', $customer->getKey())
            ->where('started_at', '>=', now()->startOfDay())
            ->count();

        if ($startedToday > 0) {
            throw new ApiException('DAILY_LIMIT_REACHED', 'امروز سهم بازی شما تمام شده است.');
        }

        // سقف کل مشارکت
        $maxTotal = $this->ruleValue($campaign, CampaignRule::TYPE_MAX_TOTAL_PLAYS);

        if ($maxTotal !== null) {
            $totalPlays = GameSession::query()
                ->where('campaign_id', $campaign->getKey())
                ->where('customer_id', $customer->getKey())
                ->count();

            if ($totalPlays >= (int) $maxTotal) {
                throw new ApiException('TOTAL_LIMIT_REACHED', 'سهم کل شما در این کمپین به پایان رسیده است.');
            }
        }

        // فقط مشتری جدید
        if ($this->hasRule($campaign, CampaignRule::TYPE_NEW_CUSTOMER_ONLY)
            && CampaignParticipation::query()
                ->where('customer_id', $customer->getKey())
                ->exists()) {
            throw new ApiException('EXISTING_CUSTOMER', 'این کمپین فقط برای مشتریان جدید است.');
        }
    }

    /** تعداد بازی باقیمانده امروز */
    public function remainingToday(Campaign $campaign, Customer $customer): int
    {
        $startedToday = GameSession::query()
            ->where('campaign_id', $campaign->getKey())
            ->where('customer_id', $customer->getKey())
            ->where('started_at', '>=', now()->startOfDay())
            ->count();

        return max(0, 1 - $startedToday);
    }

    public function hasRule(Campaign $campaign, string $type): bool
    {
        return $campaign->rules->contains(fn (CampaignRule $rule) => $rule->type === $type);
    }

    public function ruleValue(Campaign $campaign, string $type): mixed
    {
        $rule = $campaign->rules->first(fn (CampaignRule $rule) => $rule->type === $type);

        return $rule?->value;
    }
}
