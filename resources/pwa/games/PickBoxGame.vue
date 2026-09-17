<script setup>
/** Pick a Box — انتخاب جعبه؛ محتوا همیشه سمت سرور تعیین می‌شود */
import { usePickGame } from './pickGameBase.js';

const props = defineProps({
    config: { type: Object, required: true },
    resolve: { type: Function, required: true },
});
const emit = defineEmits(['done']);

const { count, possible, picked, display, busy, error, pick, stateOf } = usePickGame(props, emit);
</script>

<template>
    <div class="flex flex-col items-center py-2">
        <p class="mb-4 text-sm text-slate-500">
            {{ picked === null ? 'یکی از جعبه‌ها را انتخاب کن!' : '' }}
        </p>

        <div class="grid w-full grid-cols-3 gap-3">
            <button
                v-for="i in count"
                :key="i"
                class="flex aspect-square items-center justify-center rounded-2xl text-4xl shadow-md transition-all duration-500"
                :class="{
                    'ring-4 ring-emerald-400 scale-105': stateOf(i - 1) === 'won',
                    'ring-4 ring-rose-300': stateOf(i - 1) === 'lost',
                    'ring-2 ring-amber-300 opacity-80': stateOf(i - 1) === 'reveal',
                    'opacity-40': stateOf(i - 1) === 'dim',
                }"
                :disabled="picked !== null || busy"
                @click="pick(i - 1)"
            >
                <span v-if="stateOf(i - 1) === 'won'">🏆</span>
                <span v-else-if="stateOf(i - 1) === 'lost'">📭</span>
                <span v-else-if="stateOf(i - 1) === 'reveal'">✨</span>
                <span v-else>🎁</span>
            </button>
        </div>

        <p v-if="picked !== null && display" class="mt-4 text-center text-sm text-slate-600">
            {{ display.content === 'prize' ? `جعبه‌ات «${display.prize_label}» داشت!` : 'جعبه‌ات خالی بود؛ دفعه بعد!' }}
        </p>

        <div v-if="picked === null && possible.length" class="mt-5 w-full rounded-2xl bg-slate-50 p-3">
            <p class="mb-1 text-xs text-slate-400">جایزه‌های ممکن:</p>
            <p class="text-xs text-slate-600">{{ possible.join(' · ') }}</p>
        </div>

        <p v-if="error" class="mt-4 rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-600">{{ error }}</p>
    </div>
</template>
