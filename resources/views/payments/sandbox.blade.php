<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>پرداخت آزمایشی (سندباکس)</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Vazirmatn, Tahoma, sans-serif;
            background: #f1f5f9; color: #1e293b;
            min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px;
        }
        .card {
            background: #fff; border-radius: 20px; max-width: 420px; width: 100%;
            box-shadow: 0 10px 40px rgb(15 23 42 / .12); overflow: hidden;
        }
        .head { background: #7c3aed; color: #fff; padding: 20px 24px; }
        .head h1 { font-size: 17px; font-weight: 800; }
        .head p { font-size: 12px; opacity: .85; margin-top: 4px; }
        .body { padding: 24px; }
        .sandbox-badge {
            background: #fef9c3; color: #854d0e; border: 1px solid #fde047;
            border-radius: 12px; padding: 10px 14px; font-size: 13px; font-weight: 700; margin-bottom: 20px;
        }
        .row { display: flex; justify-content: space-between; padding: 10px 0; font-size: 14px; border-bottom: 1px dashed #e2e8f0; }
        .row span:first-child { color: #64748b; }
        .amount { color: #7c3aed; font-weight: 800; font-size: 16px; }
        .btns { display: grid; gap: 10px; margin-top: 24px; }
        a.btn {
            display: block; text-align: center; border-radius: 14px; padding: 13px;
            font-weight: 800; font-size: 14px; text-decoration: none; transition: opacity .15s;
        }
        a.btn:hover { opacity: .9; }
        .pay { background: #10b981; color: #fff; }
        .cancel { background: #fee2e2; color: #b91c1c; }
        .foot { text-align: center; font-size: 11px; color: #94a3b8; padding: 0 24px 20px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="head">
            <h1>🧪 دروازه پرداخت سندباکس</h1>
            <p>شبیه‌ساز داخلی — همان جریان دروازه واقعی</p>
        </div>
        <div class="body">
            <p class="sandbox-badge">حالت سندباکس فعال است؛ پرداخت واقعی انجام نمی‌شود و وجهی جابه‌جا نمی‌گردد.</p>

            <div class="row"><span>فروشگاه</span><b>{{ $payment->store->name }}</b></div>
            <div class="row"><span>Plan</span><b>{{ $payment->plan->name }}</b></div>
            <div class="row"><span>مبلغ</span><b class="amount">{{ number_format((int) $payment->amount_irt) }} تومان</b></div>
            <div class="row"><span>شناسه پرداخت</span><b>#{{ $payment->id }}</b></div>

            <div class="btns">
                <a class="btn pay" href="{{ url('/api/v1/payments/zarinpal/callback', ['Authority' => $payment->reference, 'Status' => 'OK']) }}">✅ پرداخت موفق (شبیه‌سازی)</a>
                <a class="btn cancel" href="{{ url('/api/v1/payments/zarinpal/callback', ['Authority' => $payment->reference, 'Status' => 'NOK']) }}">✖ انصراف / پرداخت ناموفق</a>
            </div>
        </div>
        <p class="foot">پس از انتخاب، به پنل فروشگاه‌دار بازمی‌گردید. برای پرداخت واقعی، حالت سندباکس را در تنظیمات سایت خاموش و کلیدهای ZarinPal را در env تنظیم کنید.</p>
    </div>
</body>
</html>
