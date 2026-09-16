<?php

namespace App\Domain\Reward\DTO;

/**
 * نتیجه صدور جایزه توسط یک Issuer — فصل ۶-۴ سند معماری.
 */
final class IssuanceResult
{
    public const KIND_COUPON = 'coupon';

    public const KIND_POINTS = 'points';

    public const KIND_CUSTOM = 'custom';

    public const KIND_GIFT = 'gift';

    public const KIND_NONE = 'none';

    /**
     * @param  string  $kind  نوع خروجی (کوپن، امتیاز، سفارشی و…)
     * @param  array<string, mixed>  $payload  داده‌های صادرشده (کد کوپن، امتیاز، دستورالعمل نمایش و…)
     */
    public function __construct(
        public readonly string $kind,
        public readonly array $payload = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['kind' => $this->kind] + $this->payload;
    }
}
