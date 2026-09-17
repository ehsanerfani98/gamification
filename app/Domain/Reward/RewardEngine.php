<?php

namespace App\Domain\Reward;

use App\Domain\Game\DTO\GameResult;
use App\Models\Campaign;
use App\Models\GameSession;

/**
 * Reward Engine — موتور مستقل جایزه (فصل ۶ سند معماری).
 *
 * تنها ورودی استاندارد آن «نتیجه بازی» است؛ هیچ اطلاعی از بازی مبدأ ندارد.
 * پلاگین‌های بازی هرگز مستقیم جایزه نمی‌سازند — فقط مرجع جایزه (reward_ref) می‌دهند.
 *
 * در Sprint 2 این نقطه واگذاری فقط «رزولوشن مرجع» انجام می‌دهد؛ پایپ‌لاین کامل
 * (فیلترها → انتخاب وزنی → قفل اتمی موجودی → صدور با ۹ Issuer) در Sprint 3 روی
 * همین قرارداد تکمیل می‌شود.
 */
final class RewardEngine
{
    public function resolveForSession(Campaign $campaign, GameSession $session, GameResult $result): ?array
    {
        if (! $result->isWin()) {
            return null; // پاسخ محترمانه «بدون جایزه» + ثبت NoReward (فصل ۶-۲)
        }

        return [
            'reward_ref' => $result->rewardRef,
            'status' => 'resolved',
        ];
    }
}
