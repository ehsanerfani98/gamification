<script setup>
/** لِی خوش‌شانسی — grabbed از سرور؛ حرکت دستگیره فقط انیمیشن است */
import { computed, ref } from 'vue';
import { defaultPalette } from './registry.js';

const props = defineProps({
    config: { type: Object, required: true },
    resolve: { type: Function, required: true },
});
const emit = defineEmits(['done']);

const prizes = computed(() => (props.config.prizes ?? []).map((p, i) => ({
    label: p.label,
    color: p.color || defaultPalette[i % defaultPalette.length],
    x: 18 + (i % 3) * 30 + ((i * 7) % 9),
    y: 22 + Math.floor(i / 3) * 30 + ((i * 5) % 8),
})));
const duration = computed(() => Math.min(10000, Math.max(3000, Number(props.config.duration_ms ?? 5000))));

const phase = ref('idle'); // idle | anim | done
const clawX = ref(50);
const clawY = ref(8);
const display = ref(null);
const busy = ref(false);
const error = ref('');

async function grab() {
    if (busy.value || phase.value === 'anim') return;
    busy.value = true;
    error.value = '';
    try {
        const data = await props.resolve({ type: 'tap', payload: {} });
        display.value = data.result?.display ?? {};
        phase.value = 'anim';
        clawX.value = Number(display.value.target_x ?? 50);
        clawY.value = Number(display.value.target_y ?? 50);

        setTimeout(() => {
            // بازگشت دستگیره و نمایش نتیجه
            setTimeout(() => {
                clawX.value = 50;
                clawY.value = 8;
                phase.value = 'done';
                busy.value = false;
                emit('done', data);
            }, 900);
        }, duration.value);
    } catch (e) {
        busy.value = false;
        error.value = e.message ?? 'خطا در دریافت نتیجه';
    }
}
</script>

<template>
    <div class="flex flex-col items-center py-2">
        <!-- دستگاه لِی -->
        <div class="relative h-64 w-full overflow-hidden rounded-2xl bg-gradient-to-b from-sky-100 to-indigo-100">
            <!-- دستگیره -->
            <div
                class="absolute z-10 -translate-x-1/2 text-4xl transition-all ease-in-out"
                :style="{ left: `${clawX}%`, top: `${clawY}%`, transitionDuration: `${phase === 'anim' ? duration : 600}ms` }"
            >
                🕹️
            </div>

            <!-- جایزه‌ها -->
            <div
                v-for="(p, i) in prizes"
                :key="i"
                class="absolute -translate-x-1/2 -translate-y-1/2 rounded-xl px-2 py-1 text-xs font-bold text-white shadow"
                :style="{ left: `${p.x}%`, top: `${p.y}%`, background: p.color }"
                :class="{ 'opacity-30': phase === 'done' && !display?.grabbed }"
            >
                {{ p.label }}
            </div>

            <!-- نتیجه -->
            <div v-if="phase === 'done'" class="absolute inset-0 flex items-center justify-center bg-black/30 backdrop-blur-[2px]">
                <p class="rounded-2xl bg-white px-5 py-3 text-lg font-black" :class="display?.grabbed ? 'text-emerald-600' : 'text-slate-500'">
                    {{ display?.grabbed ? 'گرفتیش! 🏆' : 'لِی خالی بود!' }}
                </p>
            </div>
        </div>

        <button
            v-if="phase !== 'anim'"
            class="mt-5 w-full rounded-2xl py-3 text-lg font-bold text-white shadow-lg transition active:scale-[0.98] disabled:opacity-50"
            :style="{ background: 'var(--gm-primary, #6d28d9)' }"
            :disabled="busy"
            @click="grab"
        >
            {{ busy || phase === 'anim' ? 'در حال حرکت…' : 'گرفتن جایزه' }}
        </button>

        <p v-if="error" class="mt-4 rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-600">{{ error }}</p>
    </div>
</template>
