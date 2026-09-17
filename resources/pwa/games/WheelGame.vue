<script setup>
/** چرخ شانس — نتیجه (segment_index) از سرور؛ چرخش فقط انیمیشن است (فصل ۲-۵) */
import { computed, ref } from 'vue';
import { defaultPalette } from './registry.js';

const props = defineProps({
    config: { type: Object, required: true },
    resolve: { type: Function, required: true },
});
const emit = defineEmits(['done']);

const segments = computed(() =>
    (props.config.segments ?? []).map((s, i) => ({ ...s, color: s.color || defaultPalette[i % defaultPalette.length] })),
);
const n = computed(() => Math.max(2, segments.value.length));
const seg = computed(() => 360 / n.value);
const duration = computed(() => Math.min(8000, Math.max(1500, Number(props.config.animation_duration_ms ?? 4200))));

const rotation = ref(0);
const spinning = ref(false);
const busy = ref(false);
const error = ref('');

const gradient = computed(() => {
    const stops = segments.value
        .map((s, i) => `${s.color} ${i * seg.value}deg ${(i + 1) * seg.value}deg`)
        .join(', ');
    return `conic-gradient(from -90deg, ${stops})`;
});

async function spin() {
    if (busy.value || spinning.value) return;
    busy.value = true;
    error.value = '';
    try {
        const data = await props.resolve({ type: 'spin', payload: {} });
        const idx = Number(data.result?.display?.segment_index ?? 0);
        // مرکز Segment idx را زیر نشانگر بالا بیاور (۵ دور کامل + ادامه)
        const target = 360 * 5 + (360 - (idx * seg.value + seg.value / 2));
        rotation.value += target - (rotation.value % 360);
        spinning.value = true;
        setTimeout(() => {
            spinning.value = false;
            busy.value = false;
            emit('done', data);
        }, duration.value + 150);
    } catch (e) {
        busy.value = false;
        error.value = e.message ?? 'خطا در دریافت نتیجه';
    }
}
</script>

<template>
    <div class="flex flex-col items-center py-2">
        <div class="relative">
            <!-- نشانگر -->
            <div class="absolute -top-1 left-1/2 z-10 -translate-x-1/2 text-3xl drop-shadow">▼</div>
            <!-- چرخ -->
            <div
                class="size-72 rounded-full border-8 border-white shadow-2xl"
                :style="{
                    background: gradient,
                    transform: `rotate(${rotation}deg)`,
                    transition: spinning ? `transform ${duration}ms cubic-bezier(0.16, 0.8, 0.22, 1)` : 'none',
                }"
            >
                <div
                    v-for="(s, i) in segments"
                    :key="i"
                    class="absolute inset-0 flex items-start justify-center"
                    :style="{ transform: `rotate(${i * seg + seg / 2}deg)` }"
                >
                    <span class="mt-5 max-w-16 truncate px-1 text-center text-xs font-bold text-white drop-shadow">{{ s.label }}</span>
                </div>
            </div>
            <!-- مرکز -->
            <button
                class="absolute left-1/2 top-1/2 size-16 -translate-x-1/2 -translate-y-1/2 rounded-full border-4 border-white text-sm font-black text-white shadow-xl disabled:opacity-60"
                :style="{ background: 'var(--gm-primary, #6d28d9)' }"
                :disabled="busy || spinning"
                @click="spin"
            >
                {{ busy || spinning ? '…' : 'بچرخان' }}
            </button>
        </div>

        <p v-if="error" class="mt-4 rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-600">{{ error }}</p>
    </div>
</template>
