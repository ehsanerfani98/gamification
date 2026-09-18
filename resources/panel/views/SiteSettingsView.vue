<script setup>
/** تنظیمات سایت (فقط Admin) — سندباکس پیامک و پرداخت برای بتای ۵ فروشگاه (Sprint 8) */
import { onMounted, ref } from 'vue';
import { ApiError } from '../../shared/api.js';
import { papi } from '../client.js';

const loading = ref(true);
const saving = ref(false);
const error = ref('');
const notice = ref('');

const smsSandbox = ref(false);
const paymentSandbox = ref(false);

onMounted(async () => {
    try {
        const data = await papi('/site-settings');
        smsSandbox.value = !!data.sms_sandbox;
        paymentSandbox.value = !!data.payment_sandbox;
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
            body: { sms_sandbox: smsSandbox.value, payment_sandbox: paymentSandbox.value },
        });
        smsSandbox.value = !!data.sms_sandbox;
        paymentSandbox.value = !!data.payment_sandbox;
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
        <p class="mt-1 text-sm text-slate-500">حالت سندباکس برای تست بتا بدون سرویس واقعی پیامک و پرداخت.</p>

        <div v-if="loading" class="mt-6 text-slate-400">در حال بارگذاری…</div>
        <template v-else>
            <p v-if="error" class="mt-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-600">{{ error }}</p>
            <p v-if="notice" class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ notice }}</p>

            <div class="mt-6 space-y-4">
                <!-- سندباکس پیامک -->
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

                <!-- سندباکس پرداخت -->
                <label class="flex cursor-pointer items-start gap-4 rounded-2xl border border-slate-200 bg-white p-5 transition hover:border-violet-300">
                    <input v-model="paymentSandbox" type="checkbox" class="mt-1 size-5 accent-violet-600" />
                    <span>
                        <span class="block font-bold text-slate-800">💳 سندباکس دروازه پرداخت</span>
                        <span class="mt-1 block text-sm leading-6 text-slate-500">
                            به‌جای ZarinPal، صفحه پرداخت آزمایشی داخلی باز می‌شود (پرداخت موفق/ناموفق به دلخواه شما)؛
                            کل مسیر callback و فعال‌سازی اشتراک و فاکتور دقیقاً مثل دروازه واقعی اجرا می‌شود — بدون کلید واقعی.
                        </span>
                    </span>
                </label>

                <!-- هشدار -->
                <p class="rounded-xl bg-amber-50 px-4 py-3 text-xs leading-6 text-amber-700">
                    ⚠️ سندباکس فقط برای محیط بتا و تست است. پیش از فروش واقعی، هر دو کلید را خاموش کنید و
                    <code class="rounded bg-amber-100 px-1">SMS_CHANNEL=ippanel</code> و
                    <code class="rounded bg-amber-100 px-1">PAYMENT_GATEWAY=zarinpal</code> را با کلیدهای واقعی در env تنظیم نمایید.
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
