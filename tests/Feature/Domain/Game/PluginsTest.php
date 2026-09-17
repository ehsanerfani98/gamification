<?php

namespace Tests\Feature\Domain\Game;

use App\Domain\Game\Contracts\GameInterface;
use App\Domain\Game\DTO\PlayerAction;
use App\Domain\Game\GameRegistry;
use App\Domain\Game\Services\ResultSigner;
use App\Models\Campaign;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesCampaigns;
use Tests\Concerns\CreatesMerchants;
use Tests\TestCase;

/**
 * Sprint 4 — ده بازی MVP روی یک هسته واحد (فصل ۵ سند معماری).
 *
 * معیار معماری: افزودن هر بازی فقط یعنی پلاگین جدید + ثبت در config/games.php
 * + رکورد جدول games — بدون هیچ تغییری در هسته Campaign/Reward/Subscription.
 */
final class PluginsTest extends TestCase
{
    use CreatesCampaigns, CreatesMerchants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    /** پیکربندی حداقلی معتبر برای هر ده بازی MVP */
    public static function gameConfigProvider(): array
    {
        return [
            'wheel' => ['wheel', [
                'segments' => [
                    ['label' => 'برنده', 'weight' => 50, 'reward_ref' => 'r-1'],
                    ['label' => 'بدون جایزه', 'weight' => 50],
                ],
            ]],
            'dice' => ['dice', [
                'faces' => [
                    ['label' => '۱', 'weight' => 1, 'reward_ref' => 'r-1'],
                    ['label' => '۲', 'weight' => 1], ['label' => '۳', 'weight' => 1],
                    ['label' => '۴', 'weight' => 1], ['label' => '۵', 'weight' => 1],
                    ['label' => '۶', 'weight' => 1],
                ],
            ]],
            'scratch' => ['scratch', [
                'symbols' => [
                    ['label' => 'ستاره', 'weight' => 30, 'reward_ref' => 'r-1'],
                    ['label' => 'لوزی', 'weight' => 30],
                    ['label' => 'دایره', 'weight' => 40],
                ],
                'grid' => ['rows' => 3, 'cols' => 3],
                'match_required' => 3,
            ]],
            'pick-box' => ['pick-box', [
                'boxes' => 3,
                'prizes' => [['label' => 'جایزه', 'weight' => 50, 'reward_ref' => 'r-1']],
                'empty_weight' => 50,
            ]],
            'pick-card' => ['pick-card', [
                'cards' => 5,
                'prizes' => [['label' => 'جایزه', 'weight' => 50, 'reward_ref' => 'r-1']],
                'empty_weight' => 50,
            ]],
            'lucky-ticket' => ['lucky-ticket', [
                'prizes' => [['label' => 'قرعه اول', 'weight' => 50, 'reward_ref' => 'r-1']],
                'empty_weight' => 50,
                'code_digits' => 6,
                'prefix' => 'BR',
            ]],
            'quiz' => ['quiz', [
                'questions' => [
                    ['text' => '۲+۲؟', 'options' => ['۳', '۴'], 'correct_index' => 1],
                ],
                'pass_score' => 1,
                'reward_ref' => 'r-1',
            ]],
            'memory' => ['memory', [
                'pairs' => 6,
                'prizes' => [['label' => 'جایزه', 'weight' => 50, 'reward_ref' => 'r-1']],
                'empty_weight' => 50,
            ]],
            'reaction' => ['reaction', [
                'rounds' => 3,
                'threshold_ms' => 300,
                'prizes' => [['label' => 'جایزه', 'weight' => 50, 'reward_ref' => 'r-1']],
                'empty_weight' => 50,
            ]],
            'claw' => ['claw', [
                'prizes' => [['label' => 'عروسک', 'weight' => 50, 'reward_ref' => 'r-1']],
                'empty_weight' => 50,
                'difficulty' => 4,
            ]],
        ];
    }

    public function test_all_ten_games_are_registered_with_valid_plugins(): void
    {
        $this->assertSame(
            ['wheel', 'dice', 'scratch', 'pick-box', 'pick-card', 'lucky-ticket', 'quiz', 'memory', 'reaction', 'claw'],
            array_keys(GameRegistry::plugins()),
        );

        foreach (GameRegistry::plugins() as $code => $class) {
            $plugin = GameRegistry::for($code);

            $this->assertInstanceOf(GameInterface::class, $plugin);
            $this->assertSame($code, $plugin::metadata()->code);
            $this->assertNotSame('', $plugin::metadata()->name);
            $this->assertNotEmpty($plugin::configSchema());
            $this->assertNotNull($plugin->validateConfig($this->minimalConfigFor($code)));
        }
    }

    /** چرخه کامل ساخت → انتشار → بازی برای هر ده بازی — Plan حرفه‌ای همه بازی‌ها را دارد. */
    #[DataProvider('gameConfigProvider')]
    public function test_campaign_create_publish_and_play_for_each_game(string $code, array $config): void
    {
        [$customerToken, , $campaign] = $this->publishProCampaign($code, $config);

        $playToken = $this->startGameSession($customerToken, $campaign->slug)
            ->assertOk()
            ->json('data.session.play_token');

        $response = $this->playAction($customerToken, $playToken, $this->actionPayloadFor($code))
            ->assertOk();

        $result = $response->json('data.result');

        $this->assertContains($result['outcome'], ['win', 'no_reward']);
        $this->assertNotEmpty($result['display']);
        // پاسخ امضاشده — نسخه دستکاری‌شده رد می‌شود (فصل ۵-۲)
        $this->assertTrue(ResultSigner::verify($result, $response->json('data.signature')));
    }

    public function test_dice_requires_exactly_six_faces(): void
    {
        [$token, $store] = $this->createMerchantWithStore();
        $this->subscribePro($store['id']);

        $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson('/api/v1/campaigns', [
                'title' => 'کمپین تاس خراب',
                'game_code' => 'dice',
                'config' => [
                    'faces' => [
                        ['label' => '۱', 'weight' => 1, 'reward_ref' => 'r-1'],
                        ['label' => '۲', 'weight' => 1],
                        ['label' => '۳', 'weight' => 1],
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_GAME_CONFIG');
    }

    /** کوئیز: تصحیح کامل سمت سرور — پاس درست → برد با صدور جایزه، پاسخ غلط → بدون جایزه */
    public function test_quiz_grading_issues_reward_on_pass_and_none_on_fail(): void
    {
        $config = [
            'questions' => [
                ['text' => 'پایتخت ایران؟', 'options' => ['شیراز', 'تهران', 'اصفهان'], 'correct_index' => 1],
                ['text' => '۲×۳؟', 'options' => ['۵', '۶'], 'correct_index' => 1],
            ],
            'pass_score' => 2,
            'reward_ref' => 'r-quiz',
            'show_answers' => true,
        ];

        // سناریوی قبولی → برد + صدور جایزه
        [$customerToken, , $campaign] = $this->publishProCampaign('quiz', $config, ['r-quiz']);

        $playToken = $this->startGameSession($customerToken, $campaign->slug)
            ->assertOk()
            ->json('data.session.play_token');

        $win = $this->playAction($customerToken, $playToken, ['answers' => [1, 1]])->assertOk();

        $this->assertSame('win', $win->json('data.result.outcome'));
        $this->assertSame('issued', $win->json('data.result.reward.status'));
        $this->assertSame(2, $win->json('data.result.display.score'));
        $this->assertTrue($win->json('data.result.display.passed'));

        // سناریوی مردودی → بدون جایزه (مشتری دیگر در کمپین جدا)
        [, $store2, $campaign2] = $this->publishProCampaign('quiz', $config, ['r-quiz'], '09121110002');
        $failingToken = $this->customerToken($store2['id'], '09334445566');

        $playToken2 = $this->startGameSession($failingToken, $campaign2->slug)
            ->assertOk()
            ->json('data.session.play_token');

        $fail = $this->playAction($failingToken, $playToken2, ['answers' => [0, 1]])->assertOk();

        $this->assertSame('no_reward', $fail->json('data.result.outcome'));
        $this->assertSame(1, $fail->json('data.result.display.score'));
        // مرور پاسخ‌ها فقط با show_answers فعال برمی‌گردد
        $this->assertIsArray($fail->json('data.result.display.review.0'));
    }

    /** کلید پاسخ کوئیز هرگز در پیکربندی عمومی افشا نمی‌شود (فصل ۸-۱) */
    public function test_quiz_correct_index_is_never_exposed_publicly(): void
    {
        [, , $campaign] = $this->publishProCampaign('quiz', [
            'questions' => [
                ['text' => 'پایتخت ایران؟', 'options' => ['شیراز', 'تهران'], 'correct_index' => 1],
            ],
            'pass_score' => 1,
            'reward_ref' => 'r-quiz',
        ]);

        $public = $this->getJson("/api/v1/c/{$campaign->slug}")->assertOk();

        $questions = $public->json('data.config.questions');

        $this->assertIsArray($questions);
        $this->assertArrayNotHasKey('correct_index', $questions[0]);
        // متن سؤال و گزینه‌ها برای رندر PWA می‌مانند
        $this->assertSame(['شیراز', 'تهران'], $questions[0]['options']);
        // هیچ وزن یا مرجع جایزه‌ای در پاسخ عمومی نیست
        $this->assertStringNotContainsString('reward_ref', (string) $public->getContent());
        $this->assertStringNotContainsString('correct_index', (string) $public->getContent());
    }

    /** کارت Scratch کاملاً سمت سرور تولید می‌شود و نتیجه با حد نصاب سازگار است */
    public function test_scratch_card_is_fully_generated_server_side(): void
    {
        [, , $campaign] = $this->publishProCampaign('scratch', [
            'symbols' => [
                ['label' => 'ستاره', 'weight' => 500, 'reward_ref' => 'r-win'],
                ['label' => 'لوزی', 'weight' => 1],
                ['label' => 'دایره', 'weight' => 1],
            ],
            'grid' => ['rows' => 3, 'cols' => 3],
            'match_required' => 3,
        ], ['r-win']);

        $plugin = GameRegistry::for('scratch');

        $runs = 300;
        $wins = 0;

        for ($i = 0; $i < $runs; $i++) {
            $result = $plugin->resolveResult(
                $this->makeSession($campaign),
                new PlayerAction('scratch'),
            );

            $cells = $result->display['cells'];
            $this->assertCount(9, $cells);

            // سازگاری: برد ⇔ رسیدن یک نماد جایزه‌دار به حد نصاب روی همان کارت
            $counts = array_count_values($cells);
            $hasMatch = collect($counts)->contains(fn ($count) => $count >= 3);

            $this->assertSame($hasMatch, $result->isWin());

            if ($result->isWin()) {
                $wins++;
            }
        }

        // وزن ۵۰۰ در برابر ۱+۱ → برد تقریباً حتمی (P ≈ ۹۹.۹٪) — آستانه ایمن ۹۰٪
        $this->assertGreaterThan($runs * 0.9, $wins);
    }

    /** Pick a Box: نتیجه روی جعبه انتخابی برجسته می‌شود — انتخاب کلاینت فقط نمایشی */
    public function test_pick_box_projects_outcome_onto_chosen_box(): void
    {
        [, , $campaign] = $this->publishProCampaign('pick-box', [
            'boxes' => 3,
            'prizes' => [['label' => 'جایزه بزرگ', 'weight' => 1000, 'reward_ref' => 'r-1']],
            'empty_weight' => 1,
        ]);

        $plugin = GameRegistry::for('pick-box');

        $wins = 0;
        $runs = 200;

        for ($i = 0; $i < $runs; $i++) {
            $result = $plugin->resolveResult(
                $this->makeSession($campaign),
                new PlayerAction('pick', ['pick_index' => 2]),
            );

            $this->assertSame(2, $result->display['chosen_index']);

            if ($result->isWin()) {
                $wins++;
                $this->assertSame(2, $result->display['reveal_index']);
                $this->assertSame('prize', $result->display['content']);
            } else {
                // در باخت، «محل جایزه» همیشه جعبه‌ای دیگر از انتخاب کاربر است
                $this->assertNotSame(2, $result->display['reveal_index']);
                $this->assertSame('empty', $result->display['content']);
            }
        }

        // P(win) ≈ ۹۹.۹٪ — آستانه ایمن ۹۵٪
        $this->assertGreaterThan($runs * 0.95, $wins);
    }

    /** ورودی دستکاری‌شده کلاینت (ایندکس خارج از بازه و reward_ref جعلی) بی‌اثر است */
    public function test_pick_games_ignore_out_of_range_client_input(): void
    {
        [, , $campaign] = $this->publishProCampaign('pick-box', [
            'boxes' => 3,
            'prizes' => [['label' => 'جایزه', 'weight' => 1, 'reward_ref' => 'r-1']],
            'empty_weight' => 100000,
        ]);

        $result = GameRegistry::for('pick-box')->resolveResult(
            $this->makeSession($campaign),
            new PlayerAction('pick', ['pick_index' => 999, 'reward_ref' => 'HACK', 'force_win' => true]),
        );

        // ایندکس خارج از بازه → clamp به آخرین جعبه (فقط نمایشی)
        $this->assertSame(2, $result->display['chosen_index']);
        // reward_ref جعلی کلاینت هرگز در نتیجه نمی‌آید
        $this->assertNotSame('HACK', $result->rewardRef);
    }

    public function test_lucky_ticket_generates_server_side_code(): void
    {
        [, , $campaign] = $this->publishProCampaign('lucky-ticket', [
            'prizes' => [['label' => 'قرعه اول', 'weight' => 1, 'reward_ref' => 'r-1']],
            'empty_weight' => 100000,
            'code_digits' => 6,
            'prefix' => 'BR',
        ]);

        $result = GameRegistry::for('lucky-ticket')->resolveResult(
            $this->makeSession($campaign),
            new PlayerAction('tap'),
        );

        $this->assertMatchesRegularExpression('/^BR[0-9]{6}$/', $result->display['code']);
        $this->assertSame($result->display['code'], $result->raw['code']);
        $this->assertNull($result->display['prize_label']);
    }

    public function test_memory_board_has_each_symbol_exactly_twice(): void
    {
        [, , $campaign] = $this->publishProCampaign('memory', [
            'pairs' => 6,
            'prizes' => [['label' => 'جایزه', 'weight' => 1, 'reward_ref' => 'r-1']],
            'empty_weight' => 1,
        ]);

        $plugin = GameRegistry::for('memory');

        for ($i = 0; $i < 20; $i++) {
            $result = $plugin->resolveResult(
                $this->makeSession($campaign),
                new PlayerAction('tap'),
            );

            $board = $result->display['board'];
            $this->assertCount(12, $board);

            foreach (array_count_values($board) as $symbol => $count) {
                $this->assertSame(2, $count, "نماد {$symbol} باید دقیقاً دو بار در board باشد.");
            }
        }
    }

    /** Reaction: زمان‌های کلاینت فقط در raw ثبت می‌شوند؛ آستانه از پیکربندی می‌آید */
    public function test_reaction_keeps_decision_server_side(): void
    {
        [, , $campaign] = $this->publishProCampaign('reaction', [
            'rounds' => 3,
            'threshold_ms' => 300,
            'prizes' => [['label' => 'جایزه', 'weight' => 1, 'reward_ref' => 'r-1']],
            'empty_weight' => 100000,
        ]);

        $result = GameRegistry::for('reaction')->resolveResult(
            $this->makeSession($campaign),
            new PlayerAction('tap', ['times_ms' => [99999, 120, 'fake']]),
        );

        $this->assertFalse($result->isWin());
        $this->assertSame(3, $result->display['rounds']);
        $this->assertSame(300, $result->display['threshold_ms']);
        $this->assertFalse($result->display['won']);
        // فقط مقادیر عددی نگه داشته می‌شوند؛ 'fake' حذف شده است
        $this->assertSame([99999, 120], $result->raw['client_times_ms']);
    }

    public function test_claw_grabbed_matches_outcome(): void
    {
        [, , $campaign] = $this->publishProCampaign('claw', [
            'prizes' => [['label' => 'عروسک', 'weight' => 1000, 'reward_ref' => 'r-1']],
            'empty_weight' => 1,
            'difficulty' => 4,
        ]);

        $plugin = GameRegistry::for('claw');
        $wins = 0;

        for ($i = 0; $i < 20; $i++) {
            $result = $plugin->resolveResult(
                $this->makeSession($campaign),
                new PlayerAction('tap'),
            );

            $this->assertSame($result->isWin(), $result->display['grabbed']);

            if ($result->isWin()) {
                $wins++;
                $this->assertSame('عروسک', $result->display['prize_label']);
                $this->assertGreaterThanOrEqual(15, $result->display['target_x']);
                $this->assertLessThanOrEqual(85, $result->display['target_y']);
            }
        }

        // P(win) ≈ ۹۹.۹٪ در هر اجرا — حداقل ۱۹ برد از ۲۰ (عمداً قطعی است)
        $this->assertGreaterThanOrEqual(19, $wins);
    }

    // ── ابزارهای کمکی ────────────────────────────────────────────────

    /**
     * کمپین منتشرشده روی Plan حرفه‌ای (دسترسی به همه بازی‌ها).
     *
     * @param  array<int, string>  $rewardRefs
     * @return array{0: string, 1: array<string, mixed>, 2: Campaign} [customerToken, store, campaign]
     */
    private function publishProCampaign(
        string $code,
        array $config,
        array $rewardRefs = [],
        string $merchantPhone = '09121110001',
    ): array {
        [$token, $store] = $this->createMerchantWithStore($merchantPhone);
        $this->subscribePro($store['id']);

        $create = $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson('/api/v1/campaigns', [
                'title' => 'کمپین '.$code,
                'game_code' => $code,
                'config' => $config,
            ])->assertCreated();

        $campaign = Campaign::query()->with('configuration')->findOrFail($create->json('data.campaign.id'));

        foreach ($rewardRefs as $ref) {
            $this->createReward($campaign, ['ref' => $ref, 'total_qty' => 5]);
        }

        $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson("/api/v1/campaigns/{$campaign->id}/publish")->assertOk();

        return [$this->customerToken($store['id']), $store, $campaign->refresh()];
    }

    private function subscribePro(int $storeId): void
    {
        Subscription::query()->create([
            'store_id' => $storeId,
            'plan_id' => Plan::query()->where('slug', 'pro')->value('id'),
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);
    }

    /** payload اکشن متناسب با هر بازی برای تست E2E */
    private function actionPayloadFor(string $code): array
    {
        return match ($code) {
            'quiz' => ['answers' => [1]], // پاسخ درست سؤال یکتا
            'pick-box', 'pick-card' => ['pick_index' => 1],
            default => [],
        };
    }

    private function minimalConfigFor(string $code): array
    {
        return self::gameConfigProvider()[$code][1];
    }
}
