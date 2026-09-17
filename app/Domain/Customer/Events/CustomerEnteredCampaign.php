<?php

namespace App\Domain\Customer\Events;

use App\Models\Campaign;
use App\Models\Customer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** ورود مشتری به کمپین — مصرف‌کنندگان: Analytics، Referral، Audit — فصل ۴-۳ */
final class CustomerEnteredCampaign
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Customer $customer,
        public readonly Campaign $campaign,
        public readonly bool $isNew,
    ) {}
}
