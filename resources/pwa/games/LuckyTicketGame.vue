<script setup>
/** بلیط خوش‌شانسی — کد بلیط از سرور می‌آید؛ کلاینت هیچ چیزی تولید نمی‌کند */
import { computed, ref } from 'vue';

const props = defineProps({
    config: { type: Object, required: true },
    resolve: { type: Function, required: true },
});
const emit = defineEmits(['done']);

const possible = computed(() => (props.config.prizes ?? []).map((p) => p.label).filter(Boolean));

const ticket = ref(null); // {code, prize_label}
const busy = ref(false);
const error = ref('');

async function draw() {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    try {
        const data = await props.resolve({ type: 'spin', payload: {} });
        const d = data.result?.display ?? {};
        ticket.value = { code: d.code ?? '', prize_label: d.prize_label ?? null };
        setTimeout(() => {
            busy.value = false;
            emit('done', data);
        }, 1600);
    } catch (e) {
        busy.value = false;
        error.value = e.message ?? 'خطا در دریافت نتیجه';
    }
}
</script>

<template>
    <div class="flex flex-col items-center py-2">
        <!-- بلیط -->
        <div
            v-if="ticket"
            class="relative w-full overflow-hidden rounded-2xl border-2 border-dashed border-violet-300 bg-gradient-to-l from-violet-50 to-pink-50 p-5 text-center transition-all duration-700"
            :class="ticket ? 'scale-100 opacity-100' : 'scale-90 opacity-0'"
        >
            <div class="absolute -right-3 top-1/2 size-6 -translate-y-1/2 rounded-full bg-white"></div>
            <div class="absolute -left-3 top-1/2 size-6 -translate-y-1/2 rounded-full bg-white"></div>
            <p class="text-xs text-slate-400">بلیط شما</p>
            <p dir="ltr" class="mt-2 text-2xl font-black tracking-[0.3em] text-violet-800">{{ ticket.code }}</p>
            <p class="mt-2 text-sm" :class="ticket.prize_label ? 'font-bold text-emerald-600' : 'text-slate-400'">
                {{ ticket.prize_label ? `برنده: ${ticket.prize_label}` : 'این بلیط برنده نبود' }}
            </p>
        </div>

        <div v-else class="flex size-32 items-center justify-center rounded-2xl bg-slate-100 text-6xl">🎫</div>

        <button
            v-if="!ticket"
            class="mt-5 w-full rounded-2xl py-3 text-lg font-bold text-white shadow-lg transition active:scale-[0.98] disabled:opacity-50"
            :style="{ background: 'var(--gm-primary, #6d28d9)' }"
            :disabled="busy"
            @click="draw"
        >
            {{ busy ? 'در حال قرعه‌کشی…' : 'دریافت بلیط' }}
        </button>

        <div v-if="!ticket && possible.length" class="mt-5 w-full rounded-2xl bg-slate-50 p-3">
            <p class="mb-1 text-xs text-slate-400">جایزه‌های ممکن:</p>
            <p class="text-xs text-slate-600">{{ possible.join(' · ') }}</p>
        </div>

        <p v-if="error" class="mt-4 rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-600">{{ error }}</p>
    </div>
</template>
