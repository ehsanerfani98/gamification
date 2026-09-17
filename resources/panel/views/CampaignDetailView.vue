<script setup>
/**
 * جزئیات کمپین — ویرایش پیکربندی، مدیریت جایزه‌ها، انتشار و لینک عمومی.
 * جریان فصل ۱-۳: Draft → (تعریف جایزه) → Publish با Feature Gating.
 */
import { computed, onMounted, ref } from 'vue';
import { ApiError } from '../../shared/api.js';
import { papi } from '../client.js';
import DynamicForm from '../components/DynamicForm.vue';

const props = defineProps({ id: { type: String, required: true } });

const campaign = ref(null);
const schema = ref(null);
const config = ref({});
const rewards = ref([]);
const rewardTypes = ref([]);
const loading = ref(true);
const error = ref('');
const notice = ref('');

/* ویرایش و انتشار */
const busy = ref(false);

/* فرم جایزه جدید */
const rewardForm = ref({ ref: '', type: 'percentage', name: '', value: '', points: '', instructions: '', gift_ref: '', weight: 10, total_qty: '' });
const rewardError = ref('');

const publicLink = computed(() => (campaign.value?.slug ? `${window.location.origin}/c/${campaign.value.slug}` : ''));
const statusLabels = { draft: ['پیش‌نویس', 'bg-slate-100 text-slate-500'], published: ['منتشر شده', 'bg-emerald-100 text-emerald-700'], expired: ['منقضی', 'bg-amber-100 text-amber-700'], archived: ['بایگانی', 'bg-rose-100 text-rose-600'] };
const typeLabels = { percentage: 'تخفیف درصدی', fixed: 'تخفیف نقدی', free_shipping: 'ارسال رایگان', free_product: 'محصول رایگان', gift: 'هدیه', points: 'امتیاز', store_coupon: 'کوپن فروشگاه', custom: 'اختصاصی', none: 'بدون جایزه' };

onMounted(load);

async function load() {
    loading.value = true;
    try {
        const data = await papi(`/campaigns/${props.id}`);
        campaign.value = data.campaign ?? data;
        config.value = campaign.value.configuration?.config ?? {};
        const [rw, sch] = await Promise.all([
            papi(`/campaigns/${props.id}/rewards`),
            papi(`/games/${campaign.value.game?.code}/config-schema`),
        ]);
        rewards.value = rw.rewards ?? [];
        rewardTypes.value = rw.types ?? [];
        schema.value = sch.schema;
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

async function saveConfig() {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    notice.value = '';
    try {
        await papi(`/campaigns/${props.id}`, { method: 'PATCH', body: { config: config.value } });
        notice.value = 'پیکربندی ذخیره شد ✓';
    } catch (e) {
        error.value = e instanceof ApiError ? (e.fields ? Object.values(e.fields).flat().join(' · ') + ' — ' : '') + e.message : e.message;
    } finally {
        busy.value = false;
    }
}

async function publish() {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    try {
        await papi(`/campaigns/${props.id}/publish`, { method: 'POST' });
        notice.value = 'کمپین منتشر شد 🎉 لینک عمومی آماده است.';
        await load();
    } catch (e) {
        error.value = e.message;
    } finally {
        busy.value = false;
    }
}

async function addReward() {
    if (busy.value) return;
    rewardError.value = '';
    busy.value = true;
    try {
        const f = rewardForm.value;
        const params = {};
        if (f.type === 'percentage' || f.type === 'fixed' || f.type === 'store_coupon') params.value = Number(f.value);
        if (f.type === 'points') params.points = Number(f.points);
        if (f.type === 'gift') { params.gift_ref = f.gift_ref || null; params.instructions = f.instructions || null; }
        if (f.type === 'custom' || f.type === 'free_product') params.instructions = f.instructions || null;

        const body = {
            ref: f.ref.trim(),
            type: f.type,
            name: f.name.trim(),
            params,
            weight: Number(f.weight) || 10,
        };
        if (f.total_qty) body.total_qty = Number(f.total_qty);

        await papi(`/campaigns/${props.id}/rewards`, { method: 'POST', body });
        rewardForm.value = { ref: '', type: f.type, name: '', value: '', points: '', instructions: '', gift_ref: '', weight: 10, total_qty: '' };
        await load();
    } catch (e) {
        rewardError.value = e instanceof ApiError ? (e.fields ? Object.values(e.fields).flat().join(' · ') + ' — ' : '') + e.message : e.message;
    } finally {
        busy.value = false;
    }
}

function copyLink() {
    navigator.clipboard?.writeText(publicLink.value).catch(() => {});
    notice.value = 'لینک عمومی کپی شد ✓';
}
</script>

<template>
    <div>
        <div v-if="loading" class="text-slate-400">در حال بارگذاری…</div>
        <p v-else-if="error && !campaign" class="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-600">{{ error }}</p>

        <template v-else>
            <!-- سربرگ -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <router-link to="/campaigns" class="text-xs text-slate-400">← کمپین‌ها</router-link>
                    <h1 class="mt-1 text-2xl font-black text-slate-800">{{ campaign.title }}</h1>
                    <p class="mt-0.5 text-sm text-slate-400">{{ campaign.game?.name }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="rounded-lg px-3 py-1.5 text-xs font-bold" :class="statusLabels[campaign.status]?.[1]">
                        {{ statusLabels[campaign.status]?.[0] ?? campaign.status }}
                    </span>
                    <button
                        v-if="campaign.status === 'draft'"
                        class="rounded-2xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white shadow disabled:opacity-50"
                        :disabled="busy"
                        @click="publish"
                    >
                        انتشار کمپین
                    </button>
                </div>
            </div>

            <p v-if="notice" class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ notice }}</p>
            <p v-if="error" class="mt-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-600">{{ error }}</p>

            <!-- لینک عمومی -->
            <div v-if="campaign.status === 'published'" class="mt-5 flex items-center gap-2 rounded-2xl bg-white p-4 shadow">
                <span class="text-xs font-bold text-slate-500">لینک عمومی:</span>
                <code dir="ltr" class="flex-1 truncate rounded-lg bg-slate-100 px-3 py-1.5 text-left text-xs text-violet-700">{{ publicLink }}</code>
                <button class="rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-bold text-white" @click="copyLink">کپی</button>
            </div>

            <div class="mt-5 grid gap-5 lg:grid-cols-2">
                <!-- پیکربندی بازی -->
                <div class="rounded-3xl bg-white p-5 shadow">
                    <h2 class="font-bold text-slate-700">پیکربندی بازی</h2>
                    <div class="mt-4">
                        <DynamicForm v-if="schema" v-model="config" :schema="schema" />
                    </div>
                    <button
                        class="mt-4 w-full rounded-2xl bg-violet-600 py-3 font-bold text-white shadow disabled:opacity-50"
                        :disabled="busy"
                        @click="saveConfig"
                    >
                        ذخیره پیکربندی
                    </button>
                </div>

                <!-- جایزه‌ها -->
                <div class="space-y-5">
                    <div class="rounded-3xl bg-white p-5 shadow">
                        <h2 class="font-bold text-slate-700">جایزه‌های کمپین ({{ rewards.length }})</h2>
                        <p v-if="!rewards.length" class="mt-3 text-sm text-slate-400">
                            هنوز جایزه‌ای تعریف نکرده‌اید؛ برای انتشار، حداقل یک جایزه لازم است.
                        </p>
                        <ul class="mt-3 space-y-2">
                            <li v-for="r in rewards" :key="r.id" class="flex items-center justify-between rounded-xl bg-slate-50 p-3">
                                <div>
                                    <p class="text-sm font-bold text-slate-700">{{ r.name }}</p>
                                    <p dir="ltr" class="text-right text-xs text-slate-400">{{ r.ref }} · وزن {{ r.weight }} · موجودی {{ r.inventory?.remaining_qty ?? '∞' }}</p>
                                </div>
                                <span class="rounded-lg bg-violet-100 px-2 py-0.5 text-xs text-violet-700">{{ typeLabels[r.type] ?? r.type }}</span>
                            </li>
                        </ul>
                    </div>

                    <div class="rounded-3xl bg-white p-5 shadow">
                        <h2 class="font-bold text-slate-700">جایزه جدید</h2>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <label class="block text-sm">
                                <span class="mb-1 block text-xs text-slate-500">مرجع (ref، لاتین) <b class="text-rose-400">*</b></span>
                                <input v-model="rewardForm.ref" dir="ltr" placeholder="r-coupon-20" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-left outline-none focus:border-violet-500" />
                            </label>
                            <label class="block text-sm">
                                <span class="mb-1 block text-xs text-slate-500">نوع جایزه <b class="text-rose-400">*</b></span>
                                <select v-model="rewardForm.type" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 outline-none focus:border-violet-500">
                                    <option v-for="t in rewardTypes" :key="t" :value="t">{{ typeLabels[t] ?? t }}</option>
                                </select>
                            </label>
                            <label class="block text-sm sm:col-span-2">
                                <span class="mb-1 block text-xs text-slate-500">نام نمایشی <b class="text-rose-400">*</b></span>
                                <input v-model="rewardForm.name" placeholder="کوپن ۲۰٪ تخفیف" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 outline-none focus:border-violet-500" />
                            </label>

                            <label v-if="['percentage', 'fixed', 'store_coupon'].includes(rewardForm.type)" class="block text-sm">
                                <span class="mb-1 block text-xs text-slate-500">مقدار ({{ rewardForm.type === 'percentage' ? 'درصد' : 'هزار تومان' }})</span>
                                <input v-model="rewardForm.value" type="number" min="1" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 outline-none focus:border-violet-500" />
                            </label>
                            <label v-if="rewardForm.type === 'points'" class="block text-sm">
                                <span class="mb-1 block text-xs text-slate-500">امتیاز</span>
                                <input v-model="rewardForm.points" type="number" min="1" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 outline-none focus:border-violet-500" />
                            </label>
                            <label v-if="['gift', 'custom', 'free_product'].includes(rewardForm.type)" class="block text-sm sm:col-span-2">
                                <span class="mb-1 block text-xs text-slate-500">راهنمای دریافت</span>
                                <input v-model="rewardForm.instructions" placeholder="با نمایش این پیام به صندوق مراجعه کنید" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 outline-none focus:border-violet-500" />
                            </label>

                            <label class="block text-sm">
                                <span class="mb-1 block text-xs text-slate-500">وزن در Reward Engine</span>
                                <input v-model="rewardForm.weight" type="number" min="1" max="1000" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 outline-none focus:border-violet-500" />
                            </label>
                            <label class="block text-sm">
                                <span class="mb-1 block text-xs text-slate-500">موجودی کل (خالی = بی‌نهایت)</span>
                                <input v-model="rewardForm.total_qty" type="number" min="1" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 outline-none focus:border-violet-500" />
                            </label>
                        </div>

                        <p v-if="rewardError" class="mt-3 rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-600">{{ rewardError }}</p>

                        <button
                            class="mt-4 w-full rounded-2xl bg-slate-800 py-3 font-bold text-white shadow disabled:opacity-50"
                            :disabled="busy || !rewardForm.ref.trim() || !rewardForm.name.trim()"
                            @click="addReward"
                        >
                            افزودن جایزه
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
