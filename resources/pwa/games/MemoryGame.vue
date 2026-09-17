<script setup>
/**
 * حافظه — board از سرور می‌آید (Fisher-Yates سمت سرور)؛ چرخش کارت‌ها صرفاً
 * نمایشی است و نتیجه نهایی از تصمیم سرور می‌آید (win/no_reward).
 */
import { computed, ref } from 'vue';
import { defaultPalette } from './registry.js';

const props = defineProps({
    config: { type: Object, required: true },
    resolve: { type: Function, required: true },
});
const emit = defineEmits(['done']);

const prizes = computed(() => props.config.prizes ?? []);
const pairs = computed(() => Math.max(4, Math.min(12, Number(props.config.pairs ?? 6))));

const board = ref(null); // [{pair}] به طول pairs*2
const flipped = ref([]); // ایندکس‌های برگشته
const matched = ref([]); // ایندکس‌های جفت‌شده
const moves = ref(0);
const movesLimit = computed(() => (props.config.moves_limit ? Number(props.config.moves_limit) : null));
const started = ref(false);
const busy = ref(false);
const error = ref('');

function cardStyle(pairIndex) {
    const p = prizes.value[pairIndex % Math.max(1, prizes.value.length)];
    return { background: p?.color || defaultPalette[pairIndex % defaultPalette.length] };
}

function cardLabel(pairIndex) {
    return prizes.value[pairIndex % Math.max(1, prizes.value.length)]?.label ?? '?';
}

async function start() {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    try {
        const data = await props.resolve({ type: 'pick', payload: {} });
        const d = data.result?.display ?? {};
        board.value = (d.board ?? []).map((p) => ({ pair: p }));
        started.value = true;
        busy.value = false;
        // پاسخ سرور نگه داشته می‌شود تا پایان بازی محلی
        window.__gmMemoryResult = data;
    } catch (e) {
        busy.value = false;
        error.value = e.message ?? 'خطا در دریافت نتیجه';
    }
}

let lock = false;
function flip(i) {
    if (lock || flipped.value.includes(i) || matched.value.includes(i)) return;
    flipped.value.push(i);
    if (flipped.value.length < 2) return;

    lock = true;
    moves.value++;
    const [a, b] = flipped.value;
    if (board.value[a].pair === board.value[b].pair) {
        matched.value.push(a, b);
        flipped.value = [];
        lock = false;
        if (matched.value.length === board.value.length) finish();
    } else {
        setTimeout(() => {
            flipped.value = [];
            lock = false;
        }, 700);
        if (movesLimit.value && moves.value >= movesLimit.value) setTimeout(finish, 750);
    }
}

function finish() {
    if (window.__gmMemoryResult) {
        const data = window.__gmMemoryResult;
        window.__gmMemoryResult = null;
        emit('done', data);
    }
}
</script>

<template>
    <div class="flex flex-col items-center py-2">
        <div v-if="!started" class="flex flex-col items-center">
            <div class="flex size-32 items-center justify-center rounded-2xl bg-slate-100 text-6xl">🧩</div>
            <p class="mt-3 text-sm text-slate-500">{{ pairs }} جفت کارت را پیدا کن!</p>
            <button
                class="mt-5 w-full rounded-2xl py-3 text-lg font-bold text-white shadow-lg disabled:opacity-50"
                :style="{ background: 'var(--gm-primary, #6d28d9)' }"
                :disabled="busy"
                @click="start"
            >
                {{ busy ? 'در حال آماده‌سازی…' : 'شروع' }}
            </button>
        </div>

        <template v-else>
            <div class="mb-3 flex w-full items-center justify-between text-xs text-slate-400">
                <span>حرکت‌ها: {{ moves }}<template v-if="movesLimit"> / {{ movesLimit }}</template></span>
                <span>پیدا شده: {{ matched.length / 2 }} از {{ pairs }}</span>
            </div>

            <div class="grid w-full grid-cols-4 gap-2">
                <button
                    v-for="(card, i) in board"
                    :key="i"
                    class="flex aspect-square items-center justify-center rounded-xl text-lg font-black text-white shadow transition-all duration-300"
                    :class="flipped.includes(i) || matched.includes(i) ? 'rotate-y-0' : 'rotate-y-180 bg-gradient-to-br from-slate-500 to-slate-700'"
                    :style="flipped.includes(i) || matched.includes(i) ? cardStyle(card.pair) : {}"
                    @click="flip(i)"
                >
                    <template v-if="flipped.includes(i) || matched.includes(i)">{{ cardLabel(card.pair) }}</template>
                    <template v-else>؟</template>
                </button>
            </div>
        </template>

        <p v-if="error" class="mt-4 rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-600">{{ error }}</p>
    </div>
</template>
