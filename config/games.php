<?php

use App\Domain\Game\Plugins\Dice\DiceGame;
use App\Domain\Game\Plugins\LuckyClaw\LuckyClawGame;
use App\Domain\Game\Plugins\LuckyTicket\LuckyTicketGame;
use App\Domain\Game\Plugins\Memory\MemoryGame;
use App\Domain\Game\Plugins\PickBox\PickBoxGame;
use App\Domain\Game\Plugins\PickCard\PickCardGame;
use App\Domain\Game\Plugins\Quiz\QuizGame;
use App\Domain\Game\Plugins\Reaction\ReactionGame;
use App\Domain\Game\Plugins\Scratch\ScratchGame;
use App\Domain\Game\Plugins\Wheel\WheelGame;

// Game Registry — نگاشت «کد بازی» به «کلاس پلاگین» (فصل ۵-۱ سند معماری)
// افزودن بازی جدید فقط یعنی ساخت پکیج در app/Domain/Game/Plugins و ثبت یک خط در اینجا.
// هیچ تغییری در هسته Campaign، Subscription، Reward یا Analytics لازم نیست.

return [

    /*
    |--------------------------------------------------------------------------
    | پلاگین‌های فعال — هر ۱۰ بازی MVP (فصل ۱-۴ سند معماری)
    |--------------------------------------------------------------------------
    | کد بازی (همان کد جدول games) => کلاس پیاده‌کننده GameInterface
    */
    'plugins' => [
        'wheel' => WheelGame::class,
        'dice' => DiceGame::class,
        'scratch' => ScratchGame::class,
        'pick-box' => PickBoxGame::class,
        'pick-card' => PickCardGame::class,
        'lucky-ticket' => LuckyTicketGame::class,
        'quiz' => QuizGame::class,
        'memory' => MemoryGame::class,
        'reaction' => ReactionGame::class,
        'claw' => LuckyClawGame::class,
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
