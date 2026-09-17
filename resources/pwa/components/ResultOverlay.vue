<script setup>
/** Overlay نتیجه — برد: نمایش جایزه صادرشده / بی‌جایزه: پیام محترمانه (فصل ۶-۲) */
import { computed } from 'vue';

const props = defineProps({
    data: { type: Object, required: true }, // پاسخ کامل action: {result, signature, rules_summary}
    gameName: { type: String, default: '' },
});
const emit = defineEmits(['close']);

const result = computed(() => props.data?.result ?? {});
const win = computed(() => result.value.outcome === 'win');
const reward = computed(() => result.value.reward?.reward ?? null);
const issuance = computed(() => result.value.reward?.issuance ?? null);
const kind = computed(() => issuance.value?.kind ?? null);
const couponCode = computed(() => (kind.value === 'coupon' ? issuance.value?.code : null));
const points = computed(() => (kind.value === 'points' ? issuance.value?.points : null));

const typeLabels = {
    percentage: 'تخفیف درصدی', fixed: 'تخفیف نقدی', free_shipping: 'ارسال رایگان',
    free_product: 'محصول رایگان', gift: 'هدیه', points: 'امتیاز',
    store_coupon: 'کوپن فروشگاه', custom: 'جایزه اختصاصی', none: '—',
};

function copyCode() {
    if (couponCode.value) navigator.clipboard?.writeText(couponCode.value).catch(() => {});
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 p-4 backdrop-blur-sm sm:items-center" @click.self="emit('close')">
        <div class="w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl">
            <!-- سربرگ رنگی -->
            <div class="px-6 py-8 text-center text-white" :style="{ background: win ? 'linear-gradient(135deg, #059669, #10b981)' : 'linear-gradient(135deg, #475569, #64748b)' }">
                <div class="text-6xl">{{ win ? '🎉' : '🤞' }}</div>
                <h2 class="mt-3 text-2xl font-black">{{ win ? 'آفرین! برنده شدی' : 'این بار نشد!' }}</h2>
                <p v-if="win && reward" class="mt-1 text-white/90">{{ reward.name }}</p>
                <p v-else class="mt-1 text-white/80">شرط بندی بعدی رو ببندی، شانس با توئه!</p>
            </div>

            <div class="space-y-4 p-6">
                <!-- کد کوپن -->
                <div v-if="couponCode" class="rounded-2xl border-2 border-dashed border-emerald-300 bg-emerald-50 p-4 text-center">
                    <p class="text-xs text-emerald-700">کد جایزه شما (روی آن بزنید تا کپی شود)</p>
                    <button dir="ltr" class="mt-1 w-full text-2xl font-black tracking-widest text-emerald-800" @click="copyCode">
                        {{ couponCode }}
                    </button>
                    <p v-if="issuance?.expires_at" class="mt-2 text-xs text-emerald-600">
                        اعتبار تا: {{ new Date(issuance.expires_at).toLocaleDateString('fa-IR') }}
                    </p>
                </div>

                <!-- امتیاز -->
                <div v-else-if="points !== null" class="rounded-2xl border-2 border-dashed border-amber-300 bg-amber-50 p-4 text-center">
                    <p class="text-3xl font-black text-amber-700">+{{ points }} امتیاز</p>
                    <p v-if="issuance?.balance !== undefined" class="mt-1 text-xs text-amber-600">موجودی کل: {{ issuance.balance }}</p>
                </div>

                <!-- هدیه / اختصاصی -->
                <div v-else-if="kind === 'gift' || kind === 'custom'" class="rounded-2xl bg-violet-50 p-4 text-center text-sm text-violet-700">
                    {{ issuance?.instructions ?? 'جایزه شما ثبت شد؛ برای دریافت با فروشگاه هماهنگ کنید.' }}
                </div>

                <!-- نوع جایزه به‌صورت برچسب -->
                <p v-if="win && reward" class="text-center text-sm text-slate-500">
                    نوع جایزه: <b>{{ typeLabels[reward.type] ?? reward.type }}</b>
                </p>

                <!-- کد بلیط بازی Lucky Ticket -->
                <p v-if="result.display?.code" dir="ltr" class="text-center text-sm text-slate-400">
                    Ticket: {{ result.display.code }}
                </p>

                <button
                    class="w-full rounded-2xl py-3.5 text-lg font-bold text-white shadow-lg transition active:scale-[0.98]"
                    :style="{ background: 'var(--gm-primary, #6d28d9)' }"
                    @click="emit('close')"
                >
                    باشه
                </button>
            </div>
        </div>
    </div>
</template>
