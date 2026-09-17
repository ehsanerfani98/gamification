<script setup>
/**
 * سرعت عمل — زمان‌های کلاینت فقط برای Analytics ثبت می‌شوند (فصل ۵)؛
 * برنده/باخت با weighted RNG و آستانه سمت سرور تعیین می‌شود.
 */
import { computed, ref } from 'vue';
import { defaultPalette } from './registry.js';

const props = defineProps({
    config: { type: Object, required: true },
    resolve: { type: Function, required: true },
});
const emit = defineEmits(['done']);

const rounds = computed(() => Math.max(1, Math.min(5, Number(props.config.rounds ?? 3))));
const possible = computed(() => (props.config.prizes ?? []).map((p) => p.label).filter(Boolean));

const phase = ref('idle'); // idle | wait | hit | sending | done
const roundNo = ref(0);
const times = ref([]);
const display = ref(null);
const error = ref('');
let waitTimer = null;
let shownAt = 0;

function begin() {
    roundNo.value = 0;
    times.value = [];
    display.value = null;
    nextRound();
}

function nextRound() {
    roundNo.value++;
    phase.value = 'wait';
    // تاخیر تصادفی برای جلوگیری از تقلب
    const delay = 900 + Math.random() * 2600;
    waitTimer = setTimeout(() => {
        shownAt = performance.now();
        phase.value = 'hit';
    }, delay);
}

async function tap() {
    if (phase.value === 'wait') {
        clearTimeout(waitTimer);
        times.value.push(99999); // زود زد — رکورد برای سرور
        nextOrSubmit();
        return;
    }
    if (phase.value === 'hit') {
        times.value.push(Math.round(performance.now() - shownAt));
        nextOrSubmit();
        return;
    }
}

async function nextOrSubmit() {
    if (roundNo.value < rounds.value) {
        nextRound();
        return;
    }
    phase.value = 'sending';
    error.value = '';
    try {
        const data = await props.resolve({ type: 'tap', payload: { times_ms: times.value } });
        display.value = data.result?.display ?? {};
        phase.value = 'done';
        setTimeout(() => emit('done', data), 1600);
    } catch (e) {
        phase.value = 'idle';
        error.value = e.message ?? 'خطا در ارسال نتایج';
    }
}
</script>

<template>
    <div class="flex flex-col items-center py-2">
        <p class="mb-2 text-xs text-slate-400">دور {{ Math.min(roundNo + (phase === 'idle' ? 0 : 1), rounds) }} از {{ rounds }}</p>

        <!-- دکمه اصلی -->
        <button
            class="flex size-56 items-center justify-center rounded-full text-2xl font-black text-white shadow-2xl transition-all active:scale-95"
            :class="{
                'animate-pulse bg-slate-400': phase === 'wait',
                'bg-emerald-500': phase === 'hit',
                'bg-violet-600': phase === 'idle' || phase === 'done' || phase === 'sending',
            }"
            :style="phase === 'idle' || phase === 'done' ? { background: 'var(--gm-primary, #6d28d9)' } : {}"
            :disabled="phase === 'sending'"
            @click="phase === 'idle' ? begin() : tap()"
        >
            <template v-if="phase === 'idle'">شروع</template>
            <template v-else-if="phase === 'wait'">صبر کن…</template>
            <template v-else-if="phase === 'hit'">بزن!</template>
            <template v-else>در حال بررسی…</template>
        </button>

        <!-- تایم‌های راند‌ها -->
        <div v-if="times.length" class="mt-4 flex flex-wrap justify-center gap-2">
            <span v-for="(t, i) in times" :key="i" dir="ltr" class="rounded-lg bg-slate-100 px-2 py-1 text-xs text-slate-600">
                {{ t }}ms
            </span>
        </div>

        <div v-if="phase === 'idle' && possible.length" class="mt-5 w-full rounded-2xl bg-slate-50 p-3">
            <p class="mb-1 text-xs text-slate-400">جایزه‌های ممکن:</p>
            <p class="text-xs text-slate-600">{{ possible.join(' · ') }}</p>
        </div>

        <p v-if="error" class="mt-4 rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-600">{{ error }}</p>
    </div>
</template>
