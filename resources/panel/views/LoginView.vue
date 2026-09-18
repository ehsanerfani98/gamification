<script setup>
/** ورود پنل — موبایل + OTP (purpose=auth، فصل ۸-۳) */
import { computed, ref } from 'vue';
import { api, ApiError } from '../../shared/api.js';
import { panelAuth } from '../client.js';

const phone = ref('');
const code = ref('');
const otpSent = ref(false);
const busy = ref(false);
const error = ref('');
// سندباکس پیامک (بتا): کد در پاسخ API برمی‌گردد و مستقیماً نمایش داده می‌شود
const debugCode = ref('');

const phoneValid = computed(() => /^09\d{9}$/.test(phone.value.trim()));

async function requestOtp() {
    if (!phoneValid.value || busy.value) return;
    busy.value = true;
    error.value = '';
    try {
        const data = await api('/auth/otp/request', { method: 'POST', body: { phone: phone.value.trim(), purpose: 'auth' } });
        otpSent.value = true;
        debugCode.value = data?.debug_code || '';
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'خطا';
    } finally {
        busy.value = false;
    }
}

async function verify() {
    if (code.value.trim().length !== 6 || busy.value) return;
    busy.value = true;
    error.value = '';
    try {
        const data = await api('/auth/otp/verify', {
            method: 'POST',
            body: { phone: phone.value.trim(), code: code.value.trim(), purpose: 'auth' },
        });
        panelAuth.set(data.token);
        window.dispatchEvent(new Event('gm:login'));
        window.location.hash = '#/';
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'خطا';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="flex min-h-dvh items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-violet-900 p-4">
        <div class="w-full max-w-sm rounded-3xl bg-white p-8 shadow-2xl">
            <p class="text-center text-3xl">🎮</p>
            <h1 class="mt-2 text-center text-xl font-black text-slate-800">ورود به پنل فروشگاه‌دار</h1>

            <form class="mt-6 space-y-4" @submit.prevent="otpSent ? verify() : requestOtp()">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-600" for="phone">شماره موبایل</label>
                    <input
                        id="phone"
                        v-model="phone"
                        inputmode="numeric"
                        dir="ltr"
                        placeholder="09xxxxxxxxx"
                        class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-center text-lg tracking-widest outline-none focus:border-violet-500"
                        :disabled="otpSent"
                    />
                </div>

                <div v-if="otpSent">
                    <label class="mb-1 block text-sm font-medium text-slate-600" for="code">کد تأیید ۶ رقمی</label>
                    <input
                        id="code"
                        v-model="code"
                        inputmode="numeric"
                        dir="ltr"
                        maxlength="6"
                        placeholder="------"
                        class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-center text-2xl tracking-[0.5em] outline-none focus:border-violet-500"
                    />
                    <p v-if="debugCode" class="mt-2 rounded-xl bg-emerald-50 px-3 py-2 text-center text-sm text-emerald-700">
                        🧪 سندباکس پیامک فعال است — کد آزمایشی:
                        <button type="button" class="font-black tracking-widest underline" dir="ltr" @click="code = debugCode">{{ debugCode }}</button>
                    </p>
                </div>

                <p v-if="error" class="rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-600">{{ error }}</p>

                <button
                    type="submit"
                    class="w-full rounded-2xl bg-violet-600 py-3.5 font-bold text-white shadow-lg transition active:scale-[0.98] disabled:opacity-50"
                    :disabled="busy || (otpSent ? code.trim().length !== 6 : !phoneValid)"
                >
                    {{ otpSent ? 'ورود' : 'دریافت کد تأیید' }}
                </button>
            </form>
        </div>
    </div>
</template>
