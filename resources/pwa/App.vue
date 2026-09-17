<script setup>
import { computed, onMounted, ref } from 'vue';
import { api, ApiError, getToken, setToken } from '../shared/api.js';
import GameStage from './components/GameStage.vue';
import ResultOverlay from './components/ResultOverlay.vue';
import RewardsSheet from './components/RewardsSheet.vue';

/**
 * PWA مشتری — جریان فصل ۹-۱:
 * بارگذاری کمپین → ورود با موبایل+OTP → بازی (Stage) → نتیجه (Overlay) → کیف جایزه‌ها.
 * نتیجه بازی هرگز سمت کلاینت تولید نمی‌شود؛ فقط «نمایش» داده سرور را دارد (فصل ۲-۵).
 */
const slug = window.location.pathname.match(/^\/c\/([a-z0-9-]+)/)?.[1] ?? '';

const stage = ref('loading'); // loading | error | gate | ready
const loadError = ref('');
const campaign = ref(null);
const customer = ref(null);
const remaining = ref(null);
const playResult = ref(null);
const showRewards = ref(false);

const token = computed(() => getToken(slug));

/* ── ورود ─────────────────────────────────────────────── */
const phone = ref('');
const code = ref('');
const otpSent = ref(false);
const sending = ref(false);
const entering = ref(false);
const gateError = ref('');

const phoneValid = computed(() => /^09\d{9}$/.test(phone.value.trim()));

function applyTheme(theme) {
    if (!theme) return;
    const root = document.documentElement.style;
    if (theme.primary) root.setProperty('--gm-primary', theme.primary);
    if (theme.accent) root.setProperty('--gm-accent', theme.accent);
    if (theme.background) root.setProperty('--gm-bg', theme.background);
}

async function loadCampaign() {
    stage.value = 'loading';
    try {
        const data = await api(`/c/${slug}`, { token: getToken(slug) });
        campaign.value = data;
        applyTheme(data.theme);
        if (data.rules_summary?.remaining !== undefined) {
            remaining.value = data.rules_summary.remaining;
            stage.value = 'ready';
        } else {
            stage.value = 'gate';
        }
    } catch (e) {
        loadError.value = e instanceof ApiError ? e.message : 'کمپین پیدا نشد.';
        stage.value = 'error';
    }
}

async function requestOtp() {
    if (!phoneValid.value || sending.value) return;
    gateError.value = '';
    sending.value = true;
    try {
        await api(`/c/${slug}/otp`, { method: 'POST', body: { phone: phone.value.trim() } });
        otpSent.value = true;
    } catch (e) {
        gateError.value = e.message;
    } finally {
        sending.value = false;
    }
}

async function enter() {
    if (code.value.trim().length !== 6 || entering.value) return;
    gateError.value = '';
    entering.value = true;
    try {
        const data = await api(`/c/${slug}/enter`, {
            method: 'POST',
            body: { phone: phone.value.trim(), code: code.value.trim() },
        });
        setToken(slug, data.token);
        customer.value = data.customer;
        await loadCampaign();
    } catch (e) {
        gateError.value = e.message;
    } finally {
        entering.value = false;
    }
}

function logout() {
    setToken(slug, null);
    customer.value = null;
    remaining.value = null;
    code.value = '';
    otpSent.value = false;
    stage.value = 'gate';
}

onMounted(loadCampaign);
</script>

<template>
    <div
        class="min-h-dvh"
        :style="{ background: 'var(--gm-bg, linear-gradient(to bottom, var(--gm-primary, #6d28d9), var(--gm-accent, #ec4899)))' }"
    >
        <!-- بارگذاری -->
        <div v-if="stage === 'loading'" class="flex min-h-dvh items-center justify-center">
            <div class="size-12 animate-spin rounded-full border-4 border-white/30 border-t-white" aria-label="در حال بارگذاری"></div>
        </div>

        <!-- خطا -->
        <div v-else-if="stage === 'error'" class="flex min-h-dvh flex-col items-center justify-center gap-4 p-6 text-center text-white">
            <div class="text-6xl">🎲</div>
            <h1 class="text-xl font-bold">{{ loadError }}</h1>
            <button class="rounded-2xl bg-white/20 px-6 py-3 font-bold backdrop-blur" @click="loadCampaign">تلاش دوباره</button>
        </div>

        <template v-else>
            <!-- سربرگ کمپین -->
            <header class="px-5 pt-8 pb-4 text-center text-white">
                <h1 class="text-2xl font-black drop-shadow">{{ campaign?.title }}</h1>
                <p v-if="campaign?.game" class="mt-1 text-sm text-white/80">بازی: {{ campaign.game.name }}</p>
            </header>

            <main class="mx-auto w-full max-w-md px-4 pb-16">
                <!-- ورود با OTP -->
                <section v-if="stage === 'gate'" class="rounded-3xl bg-white p-6 shadow-2xl">
                    <h2 class="text-lg font-bold text-slate-800">برای شرکت در بازی وارد شوید</h2>
                    <p class="mt-1 text-sm text-slate-500">شماره موبایل خود را وارد کنید؛ کد تأیید برای شما ارسال می‌شود.</p>

                    <form class="mt-5 space-y-4" @submit.prevent="otpSent ? enter() : requestOtp()">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-600" for="phone">شماره موبایل</label>
                            <input
                                id="phone"
                                v-model="phone"
                                inputmode="numeric"
                                autocomplete="tel"
                                dir="ltr"
                                placeholder="09xxxxxxxxx"
                                class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-center text-lg tracking-widest outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-200"
                                :disabled="otpSent"
                            />
                        </div>

                        <div v-if="otpSent">
                            <label class="mb-1 block text-sm font-medium text-slate-600" for="code">کد تأیید ۶ رقمی</label>
                            <input
                                id="code"
                                v-model="code"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                dir="ltr"
                                maxlength="6"
                                placeholder="------"
                                class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-center text-2xl tracking-[0.5em] outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-200"
                            />
                        </div>

                        <p v-if="gateError" class="rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-600">{{ gateError }}</p>

                        <button
                            type="submit"
                            class="w-full rounded-2xl py-3.5 text-lg font-bold text-white shadow-lg transition active:scale-[0.98] disabled:opacity-50"
                            :style="{ background: 'var(--gm-primary, #6d28d9)' }"
                            :disabled="sending || entering || (otpSent ? code.trim().length !== 6 : !phoneValid)"
                        >
                            {{ sending ? 'در حال ارسال کد…' : entering ? 'در حال ورود…' : otpSent ? 'ورود به بازی' : 'دریافت کد تأیید' }}
                        </button>

                        <button v-if="otpSent" type="button" class="w-full text-sm text-slate-500 underline" @click="otpSent = false; gateError = ''">
                            تغییر شماره موبایل
                        </button>
                    </form>
                </section>

                <!-- محیط بازی -->
                <template v-else>
                    <GameStage
                        :campaign="campaign"
                        :remaining="remaining"
                        @remaining="(v) => (remaining = v)"
                        @played="(r) => (playResult = r)"
                    />

                    <div class="mt-4 flex justify-center gap-3">
                        <button class="rounded-full bg-white/20 px-5 py-2.5 text-sm font-bold text-white backdrop-blur" @click="showRewards = true">
                            🎁 جایزه‌های من
                        </button>
                        <button class="rounded-full bg-white/10 px-5 py-2.5 text-sm text-white/80" @click="logout">خروج</button>
                    </div>
                </template>
            </main>
        </template>

        <!-- Overlay نتیجه -->
        <ResultOverlay
            v-if="playResult"
            :data="playResult"
            @close="playResult = null"
        />

        <!-- کیف جایزه‌ها -->
        <RewardsSheet
            v-if="showRewards && token"
            :token="token"
            @close="showRewards = false"
        />
    </div>
</template>
