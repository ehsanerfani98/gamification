<script setup>
/** تاس — نتیجه (face_index) از سرور؛ تکان خوردن فقط انیمیشن است */
import { computed, ref } from 'vue';
import { defaultPalette } from './registry.js';

const props = defineProps({
    config: { type: Object, required: true },
    resolve: { type: Function, required: true },
});
const emit = defineEmits(['done']);

const faces = computed(() =>
    (props.config.faces ?? []).map((f, i) => ({ ...f, color: f.color || defaultPalette[i % defaultPalette.length] })),
);
const count = computed(() => (Number(props.config.dice_count) === 2 ? 2 : 1));
const duration = computed(() => Math.min(8000, Math.max(1200, Number(props.config.animation_duration_ms ?? 3000))));

const rolling = ref(false);
const busy = ref(false);
const shown = ref(null); // {face_label, color}
const error = ref('');

async function roll() {
    if (busy.value || rolling.value) return;
    busy.value = true;
    error.value = '';
    try {
        const data = await props.resolve({ type: 'spin', payload: {} });
        const d = data.result?.display ?? {};
        rolling.value = true;
        setTimeout(() => {
            rolling.value = false;
            busy.value = false;
            shown.value = { label: d.face_label ?? '', color: d.color ?? '#6d28d9' };
            setTimeout(() => emit('done', data), 900);
        }, duration.value);
    } catch (e) {
        busy.value = false;
        error.value = e.message ?? 'خطا در دریافت نتیجه';
    }
}
</script>

<template>
    <div class="flex flex-col items-center py-3">
        <div class="flex gap-3" :class="{ 'animate-bounce': rolling }">
            <div
                v-for="i in count"
                :key="i"
                class="flex size-24 items-center justify-center rounded-2xl border-4 border-white text-2xl font-black text-white shadow-2xl transition-all"
                :class="rolling ? 'rotate-12' : ''"
                :style="{ background: rolling ? '#94a3b8' : shown?.color ?? '#6d28d9' }"
            >
                <template v-if="!rolling && shown && count === 1">{{ shown.label || '🎲' }}</template>
                <template v-else-if="!rolling && shown">{{ shown.label || '🎲' }}</template>
                <template v-else>?</template>
            </div>
        </div>

        <p v-if="shown && !rolling" class="mt-4 text-lg font-bold text-slate-700">{{ shown.label }}</p>

        <button
            class="mt-5 w-full rounded-2xl py-3 text-lg font-bold text-white shadow-lg transition active:scale-[0.98] disabled:opacity-50"
            :style="{ background: 'var(--gm-primary, #6d28d9)' }"
            :disabled="busy || rolling"
            @click="roll"
        >
            {{ rolling ? 'در حال چرخش…' : 'انداختن تاس' }}
        </button>

        <p v-if="error" class="mt-4 rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-600">{{ error }}</p>
    </div>
</template>
