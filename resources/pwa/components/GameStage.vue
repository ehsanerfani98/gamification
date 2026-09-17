<script setup>
/**
 * Stage مدیریت Session — فصل ۵-۴:
 * Start (توکن یک‌بارمصرف + Idempotency-Key) → اکشن کلاینت → نتیجه امضاشده.
 * کامپوننت بازی فقط «نمایش‌دهنده» است؛ تصمیم همیشه از سرور می‌آید.
 */
import { computed, ref } from 'vue';
import { api, ApiError, uuid } from '../../shared/api.js';
import { gameRegistry } from '../games/registry.js';

const props = defineProps({
    campaign: { type: Object, required: true },
    remaining: { type: Number, default: null },
});
const emit = defineEmits(['played', 'remaining']);

const session = ref(null);
const busy = ref(false);
const error = ref('');

const gameComponent = computed(() => gameRegistry[props.campaign?.game?.code] ?? null);
const canPlay = computed(() => props.remaining === null || props.remaining > 0);

const gameEmoji = computed(
    () =>
        ({
            wheel: '🎡', dice: '🎲', scratch: '🎟️', 'pick-box': '🎁', 'pick-card': '🃏',
            'lucky-ticket': '🎫', quiz: '🧠', memory: '🧩', reaction: '⚡', claw: '🕹️',
        })[props.campaign?.game?.code] ?? '🎮',
);

async function start() {
    if (busy.value || session.value) return;
    error.value = '';
    busy.value = true;
    try {
        const data = await api('/play/sessions', {
            method: 'POST',
            token: localStorage.getItem(`gm:token:${props.campaign.slug}`),
            idempotencyKey: uuid(),
            body: { campaign_slug: props.campaign.slug },
        });
        session.value = data.session;
        if (data.rules_summary?.remaining !== undefined) emit('remaining', data.rules_summary.remaining);
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'شروع بازی ناموفق بود.';
    } finally {
        busy.value = false;
    }
}

/** توسط کامپوننت بازی صدا زده می‌شود؛ خروجی: نتیجه کامل سرور */
async function resolve(action) {
    return api(`/play/sessions/${session.value.play_token}/action`, {
        method: 'POST',
        token: localStorage.getItem(`gm:token:${props.campaign.slug}`),
        body: action,
    });
}

function onDone(data) {
    if (data.rules_summary?.remaining !== undefined) emit('remaining', data.rules_summary.remaining);
    emit('played', data);
}

function reset() {
    session.value = null;
}
</script>

<template>
    <div class="w-full">
        <!-- پیش از شروع -->
        <div v-if="!session" class="rounded-3xl bg-white p-6 text-center shadow-2xl">
            <p class="text-4xl">{{ gameEmoji }}</p>
            <h2 class="mt-3 text-lg font-bold text-slate-800">{{ campaign.game?.name }}</h2>

            <p v-if="remaining !== null && remaining <= 0" class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-700">
                فرصت امروز شما تمام شده است؛ فردا دوباره سر بزنید!
            </p>
            <p v-else-if="remaining !== null" class="mt-4 text-sm text-slate-500">فرصت باقی‌مانده امروز: <b>{{ remaining }}</b></p>

            <p v-if="error" class="mt-4 rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-600">{{ error }}</p>

            <button
                class="mt-5 w-full rounded-2xl py-3.5 text-lg font-bold text-white shadow-lg transition active:scale-[0.98] disabled:opacity-50"
                :style="{ background: 'var(--gm-primary, #6d28d9)' }"
                :disabled="busy || !canPlay"
                @click="start"
            >
                {{ busy ? 'در حال آماده‌سازی…' : 'شروع بازی' }}
            </button>
        </div>

        <!-- صحنه بازی -->
        <div v-else class="rounded-3xl bg-white p-4 shadow-2xl">
            <component
                :is="gameComponent"
                v-if="gameComponent"
                :config="campaign.config ?? {}"
                :resolve="resolve"
                @done="onDone"
            />
            <p v-else class="py-10 text-center text-slate-400">این بازی هنوز پشتیبانی نمی‌شود.</p>
        </div>
    </div>
</template>
