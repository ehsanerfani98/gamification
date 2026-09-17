<?php

namespace Tests\Feature\Domain\Analytics;

use App\Models\Customer;
use App\Models\CustomerCheckin;
use App\Models\PointTransaction;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * چک‌این روزانه و زنجیره Streak — فصل ۱۰ سند معماری (Sprint 5):
 * امتیاز پایه + پاداش آستانه زنجیره (۷ روز) — همه از طریق Ledger.
 * چک‌این تکراری همان روز Idempotent است و شکست زنجیره آن را ریست می‌کند.
 */
final class CheckinTest extends TestCase
{
    use RefreshDatabase;

    private string $customerToken;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::query()->create([
            'phone' => '09'.random_int(100000000, 999999999),
            'role' => 'merchant',
        ]);

        $store = Store::query()->create([
            'user_id' => $user->id,
            'name' => 'فروشگاه چک‌این',
            'slug' => 'checkin-'.strtolower(Str::random(6)),
        ]);

        $this->customer = Customer::query()->create([
            'store_id' => $store->id,
            'phone' => '0933334000'.random_int(0, 9),
        ]);

        $this->customerToken = $this->customer->createToken('campaign', ['customer'])->plainTextToken;
    }

    public function test_first_checkin_awards_base_points_and_streak_one(): void
    {
        $response = $this->withToken($this->customerToken)
            ->postJson('/api/v1/daily/checkin')
            ->assertOk()
            ->json('data');

        $this->assertFalse($response['already']);
        $this->assertSame(1, $response['streak']);
        $this->assertSame(10, $response['points_awarded']);
        $this->assertSame(10, $response['balance']);
    }

    public function test_same_day_repeat_is_idempotent_without_double_points(): void
    {
        $this->withToken($this->customerToken)->postJson('/api/v1/daily/checkin')->assertOk();

        $response = $this->withToken($this->customerToken)
            ->postJson('/api/v1/daily/checkin')
            ->assertOk()
            ->json('data');

        $this->assertTrue($response['already']);
        $this->assertSame(0, $response['points_awarded']);
        $this->assertSame(10, $response['balance']);
        $this->assertDatabaseCount('customer_checkins', 1);
        $this->assertSame(10, (int) PointTransaction::query()->where('customer_id', $this->customer->id)->sum('delta'));
    }

    public function test_consecutive_days_build_streak(): void
    {
        $this->withToken($this->customerToken)->postJson('/api/v1/daily/checkin')->assertOk();

        Date::setTestNow(now()->addDay());
        $day2 = $this->withToken($this->customerToken)->postJson('/api/v1/daily/checkin')->assertOk()->json('data');

        $this->assertSame(2, $day2['streak']);
        $this->assertSame(20, $day2['balance']);

        Date::setTestNow(now()->addDay());
        $day3 = $this->withToken($this->customerToken)->postJson('/api/v1/daily/checkin')->assertOk()->json('data');

        $this->assertSame(3, $day3['streak']);
        $this->assertSame(30, $day3['balance']);
    }

    public function test_gap_day_resets_streak_to_one(): void
    {
        $this->withToken($this->customerToken)->postJson('/api/v1/daily/checkin')->assertOk();

        // پرش دو روزه — زنجیره می‌شکند
        Date::setTestNow(now()->addDays(2));

        $response = $this->withToken($this->customerToken)
            ->postJson('/api/v1/daily/checkin')
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $response['streak']);
        $this->assertDatabaseCount('customer_checkins', 2);
    }

    public function test_seven_day_streak_earns_bonus(): void
    {
        $base = now()->startOfDay();
        $total = 0;

        for ($day = 0; $day < 6; $day++) {
            Date::setTestNow($base->copy()->addDays($day)->addHours(12));
            $data = $this->withToken($this->customerToken)->postJson('/api/v1/daily/checkin')->assertOk()->json('data');
            $total += $data['points_awarded'];
        }

        // روز هفتم — پاداش ۵۰+ امتیاز پایه
        Date::setTestNow($base->copy()->addDays(6)->addHours(12));

        $response = $this->withToken($this->customerToken)
            ->postJson('/api/v1/daily/checkin')
            ->assertOk()
            ->json('data');

        $this->assertSame(7, $response['streak']);
        $this->assertSame(60, $response['points_awarded']); // ۱۰ پایه + ۵۰ پاداش
        $this->assertSame($total + 60, $response['balance']);

        // تراز Ledger با مجموع تراکنش‌ها برقرار است
        $this->assertSame(
            $response['balance'],
            (int) PointTransaction::query()->where('customer_id', $this->customer->id)->sum('delta'),
        );
    }

    public function test_checkin_row_records_streak_and_points(): void
    {
        $this->withToken($this->customerToken)->postJson('/api/v1/daily/checkin')->assertOk();

        $checkin = CustomerCheckin::query()->where('customer_id', $this->customer->id)->firstOrFail();

        $this->assertSame(1, $checkin->streak);
        $this->assertSame(10, $checkin->points_awarded);
        $this->assertSame(now()->toDateString(), $checkin->checkin_date->toDateString());
    }
}
