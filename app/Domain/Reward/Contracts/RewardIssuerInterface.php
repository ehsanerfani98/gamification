<?php

namespace App\Domain\Reward\Contracts;

use App\Domain\Reward\DTO\IssuanceResult;
use App\Models\Customer;
use App\Models\GameSession;
use App\Models\Reward;

/**
 * قرارداد Issuerها — فصل ۶-۲ سند معماری.
 *
 * افزودن نوع جایزه جدید فقط یعنی یک کلاس Issuer جدید؛ هیچ تغییری در
 * هسته Reward Engine، Game Engine یا Campaign لازم نیست.
 * هر Issuer فقط «نتیجه بازی» را به‌عنوان ورودی استاندارد می‌شناسد و
 * هیچ اطلاعی از بازی مبدأ (چرخ، تاس، کوئیز و…) ندارد.
 */
interface RewardIssuerInterface
{
    /** نوع جایزه‌ای که این Issuer صادر می‌کند (percentage, fixed, points, ...) */
    public static function type(): string;

    /**
     * صدور جایزه — باید داخل Transaction فراخوانی شود.
     * خطای هر مرحله به معنای عدم صدور و آزادسازی رزرو موجودی است.
     */
    public function issue(Reward $reward, Customer $customer, GameSession $session): IssuanceResult;
}
