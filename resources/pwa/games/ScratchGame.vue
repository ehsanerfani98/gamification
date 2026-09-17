<script setup>
/** کارت خراشیدنی — کارت کامل سمت سرور ساخته می‌شود؛ کلاینت فقط خراش می‌دهد (فصل ۶-۳) */
import { computed, ref } from 'vue';
import { defaultPalette } from './registry.js';

const props = defineProps({
    config: { type: Object, required: true },
    resolve: { type: Function, required: true },
});
const emit = defineEmits(['done']);

const symbols = computed(() =>
    (props.config.symbols ?? []).map((s, i) => ({ ...s, color: s.color || defaultPalette[i % defaultPalette.length] })),
);

const cells = ref(null); // بعد از پاسخ سرور پر می‌شود
const revealed = ref([]);
const busy = ref(false);
const scratching = ref(false);
const error = ref('');

const gridStyle = computed(() => {
    const rows = Number(cells.value?.rows ?? props.config.grid?.rows ?? 3);
    const cols = Number(cells.value?.cols ?? props.config.grid?.cols ?? 3);
    return { gridTemplateColumns: `repeat(${cols}, minmax(0, 1fr))`, gridTemplateRows: `repeat(${rows}, minmax(0, 1fr))` };
});

async function scratch() {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    try {
        const data = await props.resolve({ type: 'scratch', payload: {} });
        const d = data.result?.display ?? {};
        cells.value = d;
        revealed.value = d.cells?.map(() => false) ?? [];
        scratching.value = true;
        // باز شدن تدریجی خانه‌ها (انیمیشن خراش)
        d.cells?.forEach((_, i) => {
            setTimeout(() => (revealed.value[i] = true), 150 + i * (Number(d.animation_duration_ms ?? 2000) / Math.max(1, d.cells.length)) * 1.4);
        });
        setTimeout(() => {
            scratching.value = false;
            busy.value = false;
            emit('done', data);
        }, 500 + Number(d.animation_duration_ms ?? 2000) * 1.4);
    } catch (e) {
        busy.value = false;
        error.value = e.message ?? 'خطا در دریافت نتیجه';
    }
}
</script>

<template>
    <div class="flex flex-col items-center py-2">
        <div class="grid w-full gap-2" :style="gridStyle">
            <div
                v-for="(cell, i) in cells?.cells ?? []"
                :key="i"
                class="relative flex aspect-square items-center justify-center overflow-hidden rounded-xl text-2xl font-black transition-all duration-300"
                :class="revealed[i] ? 'scale-100 opacity-100' : 'scale-90 opacity-0'"
            >
                <span :style="{ color: cells?.symbols?.[cell]?.color ?? '#333' }">
                    {{ cells?.symbols?.[cell]?.label ?? '?' }}
                </span>
            </div>
        </div>

        <p v-if="cells" class="mt-3 text-xs text-slate-400">
            حد نصاب برنده‌شدن: {{ cells.match_required }} نماد یکسان
        </p>

        <button
            v-if="!cells"
            class="mt-5 w-full rounded-2xl py-3 text-lg font-bold text-white shadow-lg transition active:scale-[0.98] disabled:opacity-50"
            :style="{ background: 'var(--gm-primary, #6d28d9)' }"
            :disabled="busy"
            @click="scratch"
        >
            {{ busy ? 'در حال آماده‌سازی…' : 'خراش دادن کارت' }}
        </button>

        <p v-if="error" class="mt-4 rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-600">{{ error }}</p>
    </div>
</template>
