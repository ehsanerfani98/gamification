<script setup>
/**
 * تنظیمات سایت (فقط Admin) — Sprint 8 + 9
 * سندباکس پیامک/پرداخت + همه متغیرهای درایور پیامک (IPPanel) و درگاه پرداخت (ZarinPal).
 * همه متغیرها از همین بخش قابل ذخیره و بروزرسانی‌اند — بدون تغییر env سرور.
 */
import { computed, onMounted, ref } from 'vue';
import { ApiError } from '../../shared/api.js';
import { papi } from '../client.js';

const loading = ref(true);
const saving = ref(false);
const error = ref('');
const notice = ref('');

// سندباکس‌های داخلی
const smsSandbox = ref(false);
const paymentSandbox = ref(false);

// پیامک — IPPanel
const smsChannel = ref('');           // '' یعنی پیش‌فرض env
const ippanelApiKey = ref('');        // خالی = حفظ کلید فعلی
const ippanelApiKeyMasked = ref('');
const ippanelOriginator = ref('');
const ippanelBaseUrl = ref('');

// پرداخت — ZarinPal
const paymentGateway = ref('');       // '' یعنی پیش‌فرض env
const zarinpalMerchantId = ref('');
const zarinpalSandbox = ref(false);   // سندباکس رسمی زرین‌پال
const zarinpalBaseUrl = ref('');
const zarinpalTomanToRial = ref(true);
const zarinpalCallbackUrl = ref('');
const zarinpalDescription = ref('');

const effective = ref({});

const smsSource = computed(() => (smsChannel.value ? 'پنل' : 'env'));
const paymentSource = computed(() => (paymentGateway.value ? 'پنل' : 'env'));

function apply(data) {
    smsSandbox.value = !!data.sms_sandbox;
    paymentSandbox.value = !!data.payment_sandbox;

    smsChannel.value = data.sms_channel || '';
    ippanelApiKeyMasked.value = data.ippanel_api_key_masked || '';
    ippanelApiKey.value = '';
    ippanelOriginator.value = data.ippanel_originator || '';
    ippanelBaseUrl.value = data.ippanel_base_url || '';

    paymentGateway.value = data.payment_gateway || '';
    zarinpalMerchantId.value = data.zarinpal_merchant_id || '';
    zarinpalSandbox.value = !!data.zarinpal_sandbox;
    zarinpalBaseUrl.value = data.zarinpal_base_url || '';
    zarinpalTomanToRial.value = data.zarinpal_toman_to_rial === null ? true : !!data.zarinpal_toman_to_rial;
    zarinpalCallbackUrl.value = data.zarinpal_callback_url || '';
    zarinpalDescription.value = data.zarinpal_description || '';

    effective.value = data.effective || {};
}

onMounted(async () => {
    try {
        apply(await papi('/site-settings'));
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'خطا در بارگذاری تنظیمات';
    } finally {
        loading.value = false;
    }
});

async function save() {
    saving.value = true;
    error.value = '';
    notice.value = '';
    try {
        const data = await papi('/site-settings', {
            method: 'PATCH',
            body: {
                sms_sandbox: smsSandbox.value,
                payment_sandbox: paymentSandbox.value,

                sms_channel: smsChannel.value || null,
                ippanel_api_key: ippanelApiKey.value.trim(), // خالی → حفظ کلید فعلی
                ippanel_originator: ippanelOriginator.value.trim() || null,
                ippanel_base_url: ippanelBaseUrl.value.trim() || null,

                payment_gateway: paymentGateway.value || null,
                zarinpal_merchant_id: zarinpalMerchantId.value.trim() || null,
                zarinpal_sandbox: zarinpalSandbox.value,
                zarinpal_base_url: zarinpalBaseUrl.value.trim() || null,
                zarinpal_toman_to_rial: zarinpalTomanToRial.value,
                zarinpal_callback_url: zarinpalCallbackUrl.value.trim() || null,
                zarinpal_description: zarinpalDescription.value.trim() || null,
            },
        });
        apply(data);
        notice.value = 'تنظیمات ذخیره شد ✓';
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'خطا در ذخیره تنظیمات';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="max-w-2xl">
        <h1 class="text-2xl font-black text-slate-800">تنظیمات سایت</h1>
        <p class="mt-1 text-sm text-slate-500">
            سندباکس بتا + پیکربندی کامل درگاه پرداخت (ZarinPal) و پیامک (IPPanel) — بدون تغییر env سرور.
        </p>

        <div v-if="loading" class="mt-6 text-slate-400">در حال بارگذاری…</div>
        <template v-else>
            <p v-if="error" class="mt-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-600">{{ error }}</p>
            <p v-if="notice" class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ notice }}</p>

            <div class="mt-6 space-y-6">
                <!-- ═══ سندباکس‌ها ═══ -->
                <section class="space-y-4">
                    <h2 class="text-sm font-bold text-slate-400">حالت سندباکس (بتا)</h2>

                    <label class="flex cursor-pointer items-start gap-4 rounded-2xl border border-slate-200 bg-white p-5 transition hover:border-violet-300">
                        <input v-model="smsSandbox" type="checkbox" class="mt-1 size-5 accent-violet-600" />
                        <span>
                            <span class="block font-bold text-slate-800">📱 سندباکس پیامک (OTP)</span>
                            <span class="mt-1 block text-sm leading-6 text-slate-500">
                                کد یک‌بارمصرف ارسال واقعی نمی‌شود و به‌جای پیامک مستقیماً در پاسخ API نمایش داده می‌شود —
                                برای تست ورود فروشگاه‌دارها و مشتری‌ها بدون اعتبار IPPanel.
                            </span>
                        </span>
                    </label>

                    <label class="flex cursor-pointer items-start gap-4 rounded-2xl border border-slate-200 bg-white p-5 transition hover:border-violet-300">
                        <input v-model="paymentSandbox" type="checkbox" class="mt-1 size-5 accent-violet-600" />
                        <span>
                            <span class="block font-bold text-slate-800">💳 سندباکس دروازه پرداخت (شبیه‌ساز داخلی)</span>
                            <span class="mt-1 block text-sm leading-6 text-slate-500">
                                به‌جای ZarinPal، صفحه پرداخت آزمایشی داخلی باز می‌شود (پرداخت موفق/ناموفق به دلخواه شما)؛
                                کل مسیر callback و فعال‌سازی اشتراک و فاکتور دقیقاً مثل دروازه واقعی اجرا می‌شود — بدون کلید واقعی.
                            </span>
                        </span>
                    </label>
                </section>

                <!-- ═══ پیامک — IPPanel ═══ -->
                <section class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
                    <h2 class="font-bold text-slate-800">پیامک — IPPanel (فراز اس‌ام‌اس)</h2>

                    <label class="block">
                        <span class="text-sm font-bold text-slate-600">درایور پیامک</span>
                        <select v-model="smsChannel" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-500 focus:outline-none">
                            <option value="">پیش‌فرض env (فعلاً: {{ effective.sms_channel || 'log' }})</option>
                            <option value="log">log — ثبت در لاگ (توسعه)</option>
                            <option value="ippanel">ippanel — ارسال واقعی با IPPanel</option>
                        </select>
                        <span class="mt-1 block text-xs text-slate-400">منبع مقدار فعال: {{ smsSource }}</span>
                    </label>

                    <label class="block">
                        <span class="text-sm font-bold text-slate-600">کلید API پنل (apikey)</span>
                        <input
                            v-model="ippanelApiKey"
                            type="password"
                            :placeholder="ippanelApiKeyMasked ? `فعلاً: ${ippanelApiKeyMasked} — برای تغییر، کلید جدید را وارد کنید` : 'کلید API از پنل IPPanel'"
                            autocomplete="new-password"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-500 focus:outline-none"
                        />
                        <span class="mt-1 block text-xs text-slate-400">خالی بگذارید تا کلید فعلی حفظ شود — کلید ذخیره‌شده فقط ماسک‌شده نمایش داده می‌شود.</span>
                    </label>

                    <label class="block">
                        <span class="text-sm font-bold text-slate-600">خط ارسال (Originator)</span>
                        <input v-model="ippanelOriginator" type="text" placeholder="+981000xxxx یا 3000xxxx" dir="ltr"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-500 focus:outline-none" />
                    </label>

                    <label class="block">
                        <span class="text-sm font-bold text-slate-600">آدرس سرویس (Base URL)</span>
                        <input v-model="ippanelBaseUrl" type="text" placeholder="https://api2.ippanel.com" dir="ltr"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-500 focus:outline-none" />
                    </label>
                </section>

                <!-- ═══ پرداخت — ZarinPal ═══ -->
                <section class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
                    <h2 class="font-bold text-slate-800">دروازه پرداخت — ZarinPal</h2>

                    <label class="block">
                        <span class="text-sm font-bold text-slate-600">دروازه</span>
                        <select v-model="paymentGateway" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-500 focus:outline-none">
                            <option value="">پیش‌فرض env (فعلاً: {{ effective.payment_gateway || 'fake' }})</option>
                            <option value="fake">fake — دروازه آزمایشی (تست)</option>
                            <option value="zarinpal">zarinpal — درگاه واقعی/سندباکس زرین‌پال</option>
                        </select>
                        <span class="mt-1 block text-xs text-slate-400">منبع مقدار فعال: {{ paymentSource }}</span>
                    </label>

                    <label class="block">
                        <span class="text-sm font-bold text-slate-600">شناسه پذیرندگی (Merchant ID)</span>
                        <input v-model="zarinpalMerchantId" type="text" placeholder="UUID ۳۶ کاراکتری از پنل زرین‌پال" dir="ltr"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-500 focus:outline-none" />
                    </label>

                    <label class="flex cursor-pointer items-start gap-4 rounded-xl bg-slate-50 p-4">
                        <input v-model="zarinpalSandbox" type="checkbox" class="mt-1 size-5 accent-violet-600" />
                        <span>
                            <span class="block font-bold text-slate-800">🧪 سندباکس رسمی زرین‌پال</span>
                            <span class="mt-1 block text-sm leading-6 text-slate-500">
                                تراکنش‌ها به محیط آزمایشگاهی خود زرین‌پال (<code class="rounded bg-slate-200 px-1" dir="ltr">sandbox.zarinpal.com</code>)
                                ارسال می‌شود — همان API نسخه ۴ و همان جریان، اما با پول آزمایشی.
                                Merchant ID سندباکس را از <code class="rounded bg-slate-200 px-1" dir="ltr">sandbox.zarinpal.com</code> بگیرید.
                            </span>
                        </span>
                    </label>

                    <label class="block">
                        <span class="text-sm font-bold text-slate-600">آدرس دروازه (Base URL)</span>
                        <input v-model="zarinpalBaseUrl" type="text" :disabled="zarinpalSandbox" placeholder="https://payment.zarinpal.com" dir="ltr"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-500 focus:outline-none disabled:bg-slate-100 disabled:text-slate-400" />
                        <span class="mt-1 block text-xs text-slate-400">
                            {{ zarinpalSandbox ? 'با سندباکس رسمی، آدرس مؤثر: ' + (effective.zarinpal_base_url || 'https://sandbox.zarinpal.com') : 'خالی = پیش‌فرض https://payment.zarinpal.com' }}
                        </span>
                    </label>

                    <label class="flex cursor-pointer items-start gap-4 rounded-xl bg-slate-50 p-4">
                        <input v-model="zarinpalTomanToRial" type="checkbox" class="mt-1 size-5 accent-violet-600" />
                        <span>
                            <span class="block font-bold text-slate-800">تبدیل تومان → ریال (×۱۰)</span>
                            <span class="mt-1 block text-sm leading-6 text-slate-500">مبالغ سیستم تومان است و زرین‌پال ریال می‌پذیرد؛ اگر مبلغ را ریالی ثبت می‌کنید خاموش کنید.</span>
                        </span>
                    </label>

                    <label class="block">
                        <span class="text-sm font-bold text-slate-600">آدرس بازگشت (Callback URL)</span>
                        <input v-model="zarinpalCallbackUrl" type="text" placeholder="خالی = پیش‌فرض /api/v1/payments/zarinpal/callback روی همین دامنه" dir="ltr"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-500 focus:outline-none" />
                    </label>

                    <label class="block">
                        <span class="text-sm font-bold text-slate-600">توضیحات تراکنش</span>
                        <input v-model="zarinpalDescription" type="text" placeholder="خرید اشتراک پلتفرم گیمیفیکیشن"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-500 focus:outline-none" />
                    </label>
                </section>

                <!-- هشدار -->
                <p class="rounded-xl bg-amber-50 px-4 py-3 text-xs leading-6 text-amber-700">
                    ⚠️ سندباکس فقط برای محیط بتا و تست است. پیش از فروش واقعی، هر دو سندباکس را خاموش کنید،
                    درایور پیامک را روی <code class="rounded bg-amber-100 px-1">ippanel</code> و دروازه را روی
                    <code class="rounded bg-amber-100 px-1">zarinpal</code> بگذارید و کلیدهای واقعی را همین‌جا ذخیره نمایید.
                    مقادیر این پنل بر env سرور مقدم‌اند؛ برای بازگشت به env، مقادیر پنل را خالی بگذارید.
                </p>

                <button
                    class="rounded-xl bg-violet-600 px-6 py-3 text-sm font-bold text-white transition hover:bg-violet-700 disabled:opacity-50"
                    :disabled="saving"
                    @click="save"
                >
                    {{ saving ? 'در حال ذخیره…' : 'ذخیره تنظیمات' }}
                </button>
            </div>
        </template>
    </div>
</template>
