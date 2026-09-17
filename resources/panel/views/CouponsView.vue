<script setup>
/** ثبت استفاده واقعی کوپن در صندوق فروشگاه — ROI (فصل ۶) */
import { ref } from 'vue';
import { ApiError } from '../../shared/api.js';
import { papi } from '../client.js';

const code = ref('');
const amount = ref('');
const orderRef = ref('');
const busy = ref(false);
const result = ref(null);
const error = ref('');

async function redeem() {
    if (!code.value.trim() || busy.value) return;
    busy.value = true;
    error.value = '';
    result.value = null;
    try {
        const body = { code: code.value.trim() };
        if (amount.value) body.amount_irt = Number(amount.value);
        if (orderRef.value.trim()) body.order_ref = orderRef.value.trim();
        result.value = await papi('/coupons/redeem', { method: 'POST', body });
        code.value = '';
        amount.value = '';
        orderRef.value = '';
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'خطا در ثبت';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-xl">
        <h1 class="text-2xl font-black text-slate-800">ثبت استفاده کوپن</h1>
        <p class="mt-1 text-sm text-slate-400">هنگام استفاده مشتری در فروشگاه، کد را اینجا ثبت کنید تا ROI کمپین محاسبه شود.</p>

        <div class="mt-5 rounded-3xl bg-white p-6 shadow">
            <div class="space-y-3">
                <label class="block text-sm">
                    <span class="mb-1 block text-slate-500">کد کوپن <b class="text-rose-400">*</b></span>
                    <input v-model="code" dir="ltr" placeholder="XXXX-XXXX" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-center text-lg font-bold tracking-widest outline-none focus:border-violet-500" />
                </label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm">
                        <span class="mb-1 block text-slate-500">مبلغ فاکتور (هزار تومان — اختیاری)</span>
                        <input v-model="amount" type="number" min="0" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 outline-none focus:border-violet-500" />
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block text-slate-500">شماره سفارش (اختیاری)</span>
                        <input v-model="orderRef" dir="ltr" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-left outline-none focus:border-violet-500" />
                    </label>
                </div>

                <p v-if="error" class="rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-600">{{ error }}</p>
                <p v-if="result" class="rounded-xl bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                    کوپن «{{ result.coupon?.code }}» با موفقیت ثبت شد ✓
                    <template v-if="result.discount_irt"> — تخفیف: {{ result.discount_irt }} هزار تومان</template>
                </p>

                <button
                    class="w-full rounded-2xl bg-violet-600 py-3.5 font-bold text-white shadow disabled:opacity-50"
                    :disabled="busy || !code.trim()"
                    @click="redeem"
                >
                    {{ busy ? 'در حال ثبت…' : 'ثبت استفاده' }}
                </button>
            </div>
        </div>
    </div>
</template>
