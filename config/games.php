<?php

use App\Domain\Game\Plugins\Wheel\WheelGame;

// Game Registry — نگاشت «کد بازی» به «کلاس پلاگین» (فصل ۵-۱ سند معماری)
// افزودن بازی جدید فقط یعنی ساخت پکیج در app/Domain/Game/Plugins و ثبت یک خط در اینجا.
// هیچ تغییری در هسته Campaign، Subscription، Reward یا Analytics لازم نیست.

return [

    /*
    |--------------------------------------------------------------------------
    | پلاگین‌های فعال
    |--------------------------------------------------------------------------
    | کد بازی (همان کد جدول games) => کلاس پیاده‌کننده GameInterface
    */
    'plugins' => [
        'wheel' => WheelGame::class,
        // 'dice'         => \App\Domain\Game\Plugins\Dice\DiceGame::class,
        // 'scratch'      => \App\Domain\Game\Plugins\Scratch\ScratchGame::class,
        // 'pick-box'     => \App\Domain\Game\Plugins\PickBox\PickBoxGame::class,
        // 'pick-card'    => \App\Domain\Game\Plugins\PickCard\PickCardGame::class,
        // 'lucky-ticket' => \App\Domain\Game\Plugins\LuckyTicket\LuckyTicketGame::class,
        // 'quiz'         => \App\Domain\Game\Plugins\Quiz\QuizGame::class,
        // 'memory'       => \App\Domain\Game\Plugins\Memory\MemoryGame::class,
        // 'reaction'     => \App\Domain\Game\Plugins\Reaction\ReactionGame::class,
        // 'claw'         => \App\Domain\Game\Plugins\LuckyClaw\LuckyClawGame::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | دسته‌های بازی — داده‌ای، نه شاخه کد (فصل ۵-۵)
    |--------------------------------------------------------------------------
    */
    'categories' => [
        'chance' => 'شانس',
        'pick' => 'انتخاب',
        'scratch' => 'Scratch',
        'skill' => 'مهارتی',
        'arcade' => 'Arcade',
        'puzzle' => 'پازل',
        'quiz' => 'Quiz',
        'social' => 'اجتماعی',
        'referral' => 'Referral',
        'daily' => 'روزانه',
        'lottery' => 'Lottery',
        'combo' => 'ترکیبی',
    ],
];
