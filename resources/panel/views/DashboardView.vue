<script setup>
/** داشبورد — وضعیت اشتراک، Plan جاری و شروع سریع */
import { onMounted, ref } from 'vue';
import { papi } from '../client.js';

const subscription = ref(null);
const campaigns = ref([]);
const loading = ref(true);
const error = ref('');

onMounted(async () => {
    try {
        const [sub, camps] = await Promise.all([papi('/subscriptions'), papi('/campaigns')]);
        subscription.value = sub.subscription ?? sub;
        campaigns.value = camps.campaigns ?? camps ?? [];
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div>
        <h1 class="text-2xl font-black text-slate-800">داشبورد</h1>

        <div v-if="loading" class="mt-6 text-slate-400">در حال بارگذاری…</div>
        <p v-else-if="error" class="mt-6 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-600">{{ error }}</p>

        <template v-else>
            <div class="mt-5 grid gap-4 sm:grid-cols-3">
                <!-- اشتراک -->
                <div class="rounded-3xl bg-gradient-to-br from-violet-600 to-purple-700 p-5 text-white shadow-lg">
                    <p class="text-xs text-white/70">اشتراک جاری</p>
                    <p v-if="subscription?.plan" class="mt-1 text-xl font-black">
                        {{ subscription.plan.name ?? 'Plan' }}
                    </p>
                    <p v-else class="mt-1 text-lg font-bold">اشتراک فعالی ندارید</p>
                    <p v-if="subscription?.ends_at" class="mt-2 text-xs text-white/70">
                        اعتبار تا {{ new Date(subscription.ends_at).toLocaleDateString('fa-IR') }}
                    </p>
                    <router-link to="/subscription" class="mt-4 inline-block rounded-xl bg-white/20 px-4 py-2 text-xs font-bold backdrop-blur">
                        مدیریت اشتراک
                    </router-link>
                </div>

                <!-- کمپین‌ها -->
                <div class="rounded-3xl bg-white p-5 shadow">
                    <p class="text-xs text-slate-400">کمپین‌ها</p>
                    <p class="mt-1 text-3xl font-black text-slate-800">{{ campaigns.length }}</p>
                    <router-link to="/campaigns" class="mt-4 inline-block rounded-xl bg-slate-100 px-4 py-2 text-xs font-bold text-slate-600">
                        مدیریت کمپین‌ها
                    </router-link>
                </div>

                <!-- بازی‌ها -->
                <div class="rounded-3xl bg-white p-5 shadow">
                    <p class="text-xs text-slate-400">کتابخانه بازی</p>
                    <p class="mt-1 text-3xl font-black text-slate-800">۱۰</p>
                    <router-link to="/games" class="mt-4 inline-block rounded-xl bg-slate-100 px-4 py-2 text-xs font-bold text-slate-600">
                        مشاهده بازی‌ها
                    </router-link>
                </div>
            </div>

            <div class="mt-6 rounded-3xl bg-white p-5 shadow">
                <h2 class="font-bold text-slate-700">🚀 شروع سریع</h2>
                <ol class="mt-3 list-inside list-decimal space-y-1.5 text-sm text-slate-500">
                    <li>فروشگاه خود را بسازید (اگر ندارید).</li>
                    <li>از «کمپین‌ها» یک کمپین با بازی دلخواه بسازید.</li>
                    <li>جایزه‌های کمپین (کوپن، امتیاز، هدیه و…) را تعریف کنید.</li>
                    <li>کمپین را منتشر کنید و لینک عمومی را در استوری اینستاگرام بگذارید.</li>
                </ol>
            </div>
        </template>
    </div>
</template>
