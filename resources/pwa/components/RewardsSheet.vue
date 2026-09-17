<script setup>
/** کیف جایزه‌های مشتری — GET /me/rewards (کوپن‌ها + امتیاز) */
import { ref } from 'vue';
import { api, ApiError } from '../../shared/api.js';

const props = defineProps({
    token: { type: String, required: true },
});
const emit = defineEmits(['close']);

const loading = ref(true);
const error = ref('');
const data = ref(null);

const typeLabels = {
    percentage: 'تخفیف درصدی', fixed: 'تخفیف نقدی', free_shipping: 'ارسال رایگان',
    free_product: 'محصول رایگان', gift: 'هدیه', points: 'امتیاز',
    store_coupon: 'کوپن فروشگاه', custom: 'اختصاصی', none: '—',
};

const statusLabels = { active: 'معتبر', used: 'استفاده شده', expired: 'منقضی', revoked: 'باطل' };

async function load() {
    loading.value = true;
    error.value = '';
    try {
        data.value = await api('/me/rewards', { token: props.token });
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'بارگذاری ناموفق بود.';
    } finally {
        loading.value = false;
    }
}

load();
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 backdrop-blur-sm" @click.self="emit('close')">
        <div class="max-h-[85dvh] w-full max-w-md overflow-y-auto rounded-t-3xl bg-white p-6 shadow-2xl">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-bold text-slate-800">جایزه‌های من</h2>
                <button class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-500" @click="emit('close')">بستن</button>
            </div>

            <!-- امتیاز -->
            <div v-if="data" class="mb-5 rounded-2xl bg-gradient-to-l from-amber-400 to-amber-500 p-4 text-center text-white">
                <p class="text-sm text-white/80">امتیاز فعلی</p>
                <p class="text-3xl font-black">{{ data.points }}</p>
            </div>

            <div v-if="loading" class="py-10 text-center text-slate-400">در حال بارگذاری…</div>
            <p v-else-if="error" class="rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-600">{{ error }}</p>

            <template v-else>
                <p v-if="!data.coupons.length" class="py-8 text-center text-slate-400">
                    هنوز کوپنی ندارید؛ بازی کن و جایزه ببر!
                </p>

                <ul v-else class="space-y-3">
                    <li v-for="c in data.coupons" :key="c.code" class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                        <div class="flex items-center justify-between">
                            <span class="rounded-lg bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-700">
                                {{ typeLabels[c.type] ?? c.type }}
                            </span>
                            <span class="text-xs" :class="c.status === 'active' ? 'text-emerald-600' : 'text-slate-400'">
                                {{ statusLabels[c.status] ?? c.status }}
                            </span>
                        </div>
                        <p dir="ltr" class="mt-2 text-center text-xl font-black tracking-widest text-slate-800">{{ c.code }}</p>
                        <p class="mt-1 text-center text-xs text-slate-400">
                            {{ c.store }}<template v-if="c.expires_at"> · اعتبار تا {{ new Date(c.expires_at).toLocaleDateString('fa-IR') }}</template>
                        </p>
                    </li>
                </ul>
            </template>
        </div>
    </div>
</template>
