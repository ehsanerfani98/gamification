<script setup>
/** اشتراک و Plan — مشاهده Plan جاری و ارتقا (Feature Gating فصل ۷) */
import { onMounted, ref } from 'vue';
import { ApiError } from '../../shared/api.js';
import { papi } from '../client.js';

const plans = ref([]);
const subscription = ref(null);
const loading = ref(true);
const busyId = ref(null);
const error = ref('');
const notice = ref('');

onMounted(load);

async function load() {
    loading.value = true;
    try {
        const [p, s] = await Promise.all([papi('/plans'), papi('/subscriptions')]);
        plans.value = p.plans ?? p ?? [];
        subscription.value = s.subscription ?? s;
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

async function subscribe(planId) {
    busyId.value = planId;
    error.value = '';
    notice.value = '';
    try {
        const data = await papi('/subscriptions', { method: 'POST', body: { plan_id: planId } });
        if (data.payment?.redirect_url) {
            notice.value = 'در حال انتقال به دروازه پرداخت… (در محیط MVP دروازه Fake فعال است)';
            window.location.href = data.payment.redirect_url;
        } else {
            notice.value = 'اشتراک ثبت شد ✓';
            await load();
        }
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'خطا در ثبت اشتراک';
    } finally {
        busyId.value = null;
    }
}
</script>

<template>
    <div>
        <h1 class="text-2xl font-black text-slate-800">اشتراک و Plan</h1>

        <div v-if="loading" class="mt-6 text-slate-400">در حال بارگذاری…</div>
        <template v-else>
            <p v-if="error" class="mt-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-600">{{ error }}</p>
            <p v-if="notice" class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ notice }}</p>

            <p v-if="subscription?.plan" class="mt-4 text-sm text-slate-500">
                Plan فعلی: <b class="text-slate-700">{{ subscription.plan.name }}</b>
                <template v-if="subscription.ends_at"> — اعتبار تا {{ new Date(subscription.ends_at).toLocaleDateString('fa-IR') }}</template>
            </p>

            <div class="mt-5 grid gap-4 md:grid-cols-3">
                <div
                    v-for="p in plans"
                    :key="p.id"
                    class="flex flex-col rounded-3xl bg-white p-6 shadow"
                    :class="{ 'ring-2 ring-violet-500': subscription?.plan && (p.id === subscription.plan.id || p.slug === subscription.plan.slug) }"
                >
                    <h2 class="text-lg font-black text-slate-800">{{ p.name }}</h2>
                    <p v-if="p.price_irt !== undefined" class="mt-1 text-2xl font-black text-violet-600">
                        {{ p.price_irt === 0 ? 'رایگان' : p.price_irt + ' هزار تومان/ماه' }}
                    </p>
                    <p v-if="p.description" class="mt-2 text-xs text-slate-400">{{ p.description }}</p>

                    <ul class="mt-4 flex-1 space-y-1.5 text-sm text-slate-600">
                        <li v-if="p.features?.campaign_quota" class="flex gap-2"><span>✓</span> تا {{ p.features.campaign_quota }} کمپین فعال هم‌زمان</li>
                        <li v-if="p.features?.max_rewards" class="flex gap-2"><span>✓</span> تا {{ p.features.max_rewards }} جایزه در هر کمپین</li>
                        <li v-if="p.features?.games" class="flex gap-2"><span>✓</span> {{ Array.isArray(p.features.games) ? p.features.games.length + ' بازی' : 'همه بازی‌ها' }}</li>
                        <li v-if="p.features?.analytics !== undefined" class="flex gap-2"><span>✓</span> {{ p.features.analytics ? 'آنالیتیکس کامل' : 'آنالیتیکس پایه' }}</li>
                    </ul>

                    <button
                        class="mt-5 rounded-2xl py-3 font-bold shadow transition active:scale-[0.98] disabled:opacity-50"
                        :class="subscription?.plan && (p.id === subscription.plan.id || p.slug === subscription.plan.slug) ? 'bg-slate-100 text-slate-400' : 'bg-violet-600 text-white'"
                        :disabled="busyId !== null || (subscription?.plan && (p.id === subscription.plan.id || p.slug === subscription.plan.slug))"
                        @click="subscribe(p.id)"
                    >
                        {{ subscription?.plan && (p.id === subscription.plan.id || p.slug === subscription.plan.slug) ? 'Plan فعلی شما' : busyId === p.id ? 'در حال ثبت…' : 'انتخاب Plan' }}
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>
