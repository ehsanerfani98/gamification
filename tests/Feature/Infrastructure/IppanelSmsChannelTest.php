<?php

namespace Tests\Feature\Infrastructure;

use App\Infrastructure\Sms\Drivers\IppanelSmsChannel;
use App\Infrastructure\Sms\Drivers\LogSmsChannel;
use App\Infrastructure\Sms\SmsChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * تست درایور پیامک IPPanel — Sprint 7.
 * بایند پویا با env + صحت Payload و Endpoint + رفتار خطا برای Retry صف.
 */
final class IppanelSmsChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_channel_is_default_binding(): void
    {
        $this->assertInstanceOf(LogSmsChannel::class, app(SmsChannel::class));
    }

    public function test_ippanel_binding_follows_config(): void
    {
        config(['gamification.sms.channel' => 'ippanel']);

        $this->assertInstanceOf(IppanelSmsChannel::class, app(SmsChannel::class));
    }

    public function test_sends_single_sms_with_correct_endpoint_and_payload(): void
    {
        config([
            'gamification.sms.channel' => 'ippanel',
            'gamification.sms.ippanel.api_key' => 'test-api-key',
            'gamification.sms.ippanel.originator' => '+983000505',
        ]);

        Http::fake([
            'api2.ippanel.com/*' => Http::response([
                'data' => ['code' => 200, 'message_id' => 1234],
                'meta' => ['status' => true, 'message' => 'انجام شد'],
            ], 200),
        ]);

        app(SmsChannel::class)->send('09121234567', 'کد ورود شما: 123456');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api2.ippanel.com/api/v1/sms/send/webservice/single'
                && $request->header('apikey') === ['test-api-key']
                && $request['originator'] === '+983000505'
                && $request['recipient'] === '09121234567'
                && $request['message'] === 'کد ورود شما: 123456';
        });
    }

    public function test_rejected_meta_status_throws_for_queue_retry(): void
    {
        config([
            'gamification.sms.channel' => 'ippanel',
            'gamification.sms.ippanel.api_key' => 'test-api-key',
        ]);

        // پاسخ HTTP 200 ولی meta.status=false → خطای سرویس (مثلاً موجودی ناکافی)
        Http::fake([
            'api2.ippanel.com/*' => Http::response([
                'data' => [],
                'meta' => ['status' => false, 'message' => 'Insufficient credit'],
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient credit');

        app(SmsChannel::class)->send('09121234567', 'test');
    }

    public function test_http_error_throws_for_queue_retry(): void
    {
        config([
            'gamification.sms.channel' => 'ippanel',
            'gamification.sms.ippanel.api_key' => 'invalid-key',
        ]);

        // کلید نامعتبر → HTTP 401 → Exception تا صف با backoff پلکانی Retry کند
        Http::fake([
            'api2.ippanel.com/*' => Http::response(['errors' => ['Unauthenticated']], 401),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 401');

        app(SmsChannel::class)->send('09121234567', 'test');
    }
}
