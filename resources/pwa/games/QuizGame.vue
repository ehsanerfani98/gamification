<script setup>
/**
 * کوییز — تصحیح کامل سمت سرور (Server-Side Grading، فصل ۶-۳).
 * کلید پاسخ هرگز به کلاینت نمی‌رسد؛ کلاینت فقط انتخاب‌ها را می‌فرستد.
 */
import { computed, ref } from 'vue';

const props = defineProps({
    config: { type: Object, required: true },
    resolve: { type: Function, required: true },
});
const emit = defineEmits(['done']);

const questions = computed(() => props.config.questions ?? []);
const current = ref(0);
const answers = ref([]);
const submitting = ref(false);
const finished = ref(false);
const display = ref(null);
const error = ref('');

function choose(optionIndex) {
    if (submitting.value || finished.value) return;
    answers.value[current.value] = optionIndex;
    if (current.value < questions.value.length - 1) {
        current.value++;
    }
}

async function submit() {
    if (submitting.value || answers.value.length < questions.value.length) return;
    submitting.value = true;
    error.value = '';
    try {
        const data = await props.resolve({ type: 'answer', payload: { answers: answers.value.map((a) => Number(a)) } });
        display.value = data.result?.display ?? {};
        finished.value = true;
        setTimeout(() => {
            submitting.value = false;
            emit('done', data);
        }, 1800);
    } catch (e) {
        submitting.value = false;
        error.value = e.message ?? 'خطا در ارسال پاسخ‌ها';
    }
}

function back() {
    if (current.value > 0) current.value--;
}
</script>

<template>
    <div class="py-2">
        <template v-if="!finished">
            <!-- نوار پیشرفت -->
            <div class="mb-4 flex items-center gap-2">
                <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                    <div
                        class="h-full rounded-full transition-all"
                        :style="{ width: `${((current + 1) / questions.length) * 100}%`, background: 'var(--gm-primary, #6d28d9)' }"
                    ></div>
                </div>
                <span class="text-xs text-slate-400">{{ current + 1 }} از {{ questions.length }}</span>
            </div>

            <h3 class="text-lg font-bold text-slate-800">{{ questions[current]?.text }}</h3>

            <div class="mt-4 space-y-2">
                <button
                    v-for="(opt, i) in questions[current]?.options ?? []"
                    :key="i"
                    class="w-full rounded-2xl border-2 p-3 text-right transition"
                    :class="answers[current] === i ? 'border-violet-500 bg-violet-50 font-bold' : 'border-slate-200 bg-white'"
                    :disabled="submitting"
                    @click="choose(i)"
                >
                    {{ opt }}
                </button>
            </div>

            <div class="mt-4 flex gap-2">
                <button v-if="current > 0" class="rounded-2xl bg-slate-100 px-5 py-3 font-bold text-slate-600" @click="back">
                    قبلی
                </button>
                <button
                    v-if="current === questions.length - 1"
                    class="flex-1 rounded-2xl py-3 text-lg font-bold text-white shadow-lg disabled:opacity-50"
                    :style="{ background: 'var(--gm-primary, #6d28d9)' }"
                    :disabled="submitting || answers.length < questions.length"
                    @click="submit"
                >
                    {{ submitting ? 'در حال تصحیح…' : 'پایان و ثبت پاسخ‌ها' }}
                </button>
            </div>
        </template>

        <!-- نتیجه کوییز -->
        <div v-else class="py-8 text-center">
            <div class="text-6xl">{{ display?.passed ? '🎉' : '😮' }}</div>
            <p class="mt-3 text-2xl font-black text-slate-800">{{ display?.score }} از {{ display?.total }}</p>
            <p class="mt-1 text-sm" :class="display?.passed ? 'text-emerald-600' : 'text-slate-400'">
                {{ display?.passed ? 'قبول شدی! جایزه در راه است…' : 'مردودی؛ دفعه بعد بهتره!' }}
            </p>
        </div>

        <p v-if="error" class="mt-4 rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-600">{{ error }}</p>
    </div>
</template>
