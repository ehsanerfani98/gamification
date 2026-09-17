<?php

namespace App\Domain\Subscription\Services;

use App\Models\Game;
use App\Models\Plan;
use App\Models\Store;

/**
 * تنها مرجع پاسخ به «این Store الان چه مجازی دارد؟» — فصل ۷-۱ سند معماری.
 *
 * همه دامنه‌ها از همین سرویس می‌پرسند؛ پرس‌وجوی مستقیم به Plan از درون
 * دامنه‌های دیگر ممنوع است. پس از انقضای اشتراک، Feature Gating به سطح
 * Plan رایگان برمی‌گردد و هیچ داده‌ای حذف نمی‌شود.
 *
 * سقف عددی null به معنای «بدون سقف» است (مثلاً Plan حرفه‌ای).
 */
final class FeatureGate
{
    private ?Plan $resolvedPlan = null;

    public function __construct(private readonly Store $store) {}

    public static function for(Store|int $store): self
    {
        return new self(is_int($store) ? Store::findOrFail($store) : $store);
    }

    /** Plan مؤثر جاری: Plan اشتراک فعال، یا Plan رایگان به‌عنوان سطح بازگشتی */
    public function plan(): ?Plan
    {
        return $this->resolvedPlan ??= ($this->store->activeSubscription()?->plan ?? Plan::free());
    }

    /** @return array<string, mixed> */
    public function features(): array
    {
        return $this->plan()?->features ?? [];
    }

    /** سقف عددی یک امکان؛ null یعنی بدون سقف */
    public function limit(string $key): ?int
    {
        $value = $this->features()[$key] ?? null;

        return $value === null ? null : (int) $value;
    }

    public function maxActiveCampaigns(): ?int
    {
        return $this->limit('max_active_campaigns');
    }

    public function maxParticipantsPerMonth(): ?int
    {
        return $this->limit('max_participants_per_month');
    }

    public function maxRewards(): ?int
    {
        return $this->limit('max_rewards');
    }

    public function analyticsLevel(): string
    {
        return (string) ($this->features()['analytics'] ?? 'summary');
    }

    public function customizationLevel(): string
    {
        return (string) ($this->features()['customization'] ?? 'basic');
    }

    /** امکان بولی (referral, daily_games, remove_branding و…) */
    public function has(string $feature): bool
    {
        return (bool) ($this->features()[$feature] ?? false);
    }

    /** دسترسی بازی بر اساس جدول plan_game — Feature Gating داده‌محور */
    public function canAccessGame(Game $game): bool
    {
        $plan = $this->plan();

        return $plan !== null
            && $plan->games()->whereKey($game->getKey())->exists();
    }
}
