<script setup>
/** کمپین‌ها — فهرست + Wizard دو مرحله‌ای ساخت (مشخصات → پیکربندی پویا) */
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { ApiError } from '../../shared/api.js';
import { papi } from '../client.js';
import DynamicForm from '../components/DynamicForm.vue';

const router = useRouter();

const campaigns = ref([]);
const games = ref([]);
const loading = ref(true);
const error = ref('');

/* ── Wizard ─────────────────────────────────────────── */
const step = ref(0); // 0=فهرست 1=مشخصات 2=پیکربندی
const form = ref({ title: '', game_code: '', starts_at: '', ends_at: '' });
const config = ref({});
const schema = ref(null);
const busy = ref(false);
const wizardError = ref('');

const playableGames = computed(() => games.value.filter((g) => g.is_implemented && g.plan_allowed));
const selectedGame = computed(() => games.value.find((g) => g.code === form.value.game_code));
const canNext = computed(() =>
    step.value === 1 ? form.value.title.trim().length > 2 && form.value.game_code : true,
);

const statusLabels = { draft: ['پیش‌نویس', 'bg-slate-100 text-slate-500'], published: ['منتشر شده', 'bg-emerald-100 text-emerald-700'], expired: ['منقضی', 'bg-amber-100 text-amber-700'], archived: ['بایگانی', 'bg-rose-100 text-rose-600'] };

onMounted(load);

async function load() {
    loading.value = true;
    try {
        const [camps, gameData] = await Promise.all([papi('/campaigns'), papi('/games')]);
        campaigns.value = camps.campaigns ?? camps ?? [];
        games.value = (gameData.games ?? []).filter((g) => g.is_implemented);
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

async function pickGame(code) {
    form.value.game_code = code;
    wizardError.value = '';
    busy.value = true;
    try {
        const data = await papi(`/games/${code}/config-schema`);
        schema.value = data.schema;
        // مقدار اولیه معقول برای آرایه‌های الزامی
        const initial = {};
        for (const [key, prop] of Object.entries(data.schema.properties ?? {})) {
            if (data.schema.required?.includes(key) && prop.type === 'array') initial[key] = [];
        }
        config.value = initial;
        step.value = 2;
    } catch (e) {
        wizardError.value = e.message;
    } finally {
        busy.value = false;
    }
}

async function create() {
    if (busy.value) return;
    wizardError.value = '';
    busy.value = true;
    try {
        const body = {
            title: form.value.title.trim(),
            game_code: form.value.game_code,
            config: config.value,
        };
        if (form.value.starts_at) body.starts_at = new Date(form.value.starts_at).toISOString();
        if (form.value.ends_at) body.ends_at = new Date(form.value.ends_at).toISOString();
        const data = await papi('/campaigns', { method: 'POST', body });
        const id = data.campaign?.id ?? data.id;
        router.push(`/campaigns/${id}`);
    } catch (e) {
        wizardError.value = e instanceof ApiError
            ? (e.fields ? Object.values(e.fields).flat().join(' · ') + ' — ' : '') + e.message
            : 'خطا در ساخت کمپین';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div>
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-black text-slate-800">کمپین‌ها</h1>
            <button v-if="step === 0" class="rounded-2xl bg-violet-600 px-5 py-2.5 text-sm font-bold text-white shadow" @click="step = 1; wizardError = ''">
                + کمپین جدید
            </button>
        </div>

        <div v-if="loading && step === 0" class="mt-6 text-slate-400">در حال بارگذاری…</div>
        <p v-if="error" class="mt-6 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-600">{{ error }}</p>

        <!-- مرحله ۰: فهرست -->
        <div v-if="step === 0 && !loading" class="mt-5 space-y-3">
            <p v-if="!campaigns.length" class="rounded-3xl bg-white p-8 text-center text-slate-400 shadow">
                هنوز کمپینی ندارید؛ اولین کمپین خود را بسازید!
            </p>
            <router-link
                v-for="c in campaigns"
                :key="c.id"
                :to="`/campaigns/${c.id}`"
                class="flex items-center justify-between rounded-2xl bg-white p-4 shadow transition hover:shadow-md"
            >
                <div>
                    <p class="font-bold text-slate-700">{{ c.title }}</p>
                    <p dir="ltr" class="text-right text-xs text-slate-400">{{ c.game?.name ?? c.game_code }} · /c/{{ c.slug }}</p>
                </div>
                <span class="rounded-lg px-2.5 py-1 text-xs font-bold" :class="statusLabels[c.status]?.[1]">{{ statusLabels[c.status]?.[0] ?? c.status }}</span>
            </router-link>
        </div>

        <!-- مرحله ۱: مشخصات -->
        <div v-if="step === 1" class="mx-auto mt-5 max-w-2xl rounded-3xl bg-white p-6 shadow">
            <h2 class="font-black text-slate-700">۱. مشخصات کمپین</h2>

            <div class="mt-4 space-y-4">
                <label class="block text-sm">
                    <span class="mb-1 block text-slate-500">عنوان کمپین <b class="text-rose-400">*</b></span>
                    <input v-model="form.title" placeholder="مثلاً: چرخ شانس نوروزی" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 outline-none focus:border-violet-500" />
                </label>

                <div>
                    <span class="mb-2 block text-sm text-slate-500">انتخاب بازی <b class="text-rose-400">*</b></span>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                        <button
                            v-for="g in playableGames"
                            :key="g.code"
                            class="rounded-2xl border-2 p-3 text-center transition"
                            :class="form.game_code === g.code ? 'border-violet-500 bg-violet-50' : 'border-slate-200'"
                            @click="form.game_code = g.code"
                        >
                            <p class="text-2xl">{{ { wheel: '🎡', dice: '🎲', scratch: '🎟️', 'pick-box': '🎁', 'pick-card': '🃏', 'lucky-ticket': '🎫', quiz: '🧠', memory: '🧩', reaction: '⚡', claw: '🕹️' }[g.code] }}</p>
                            <p class="mt-1 text-xs font-bold text-slate-600">{{ g.name.split(' (')[0] }}</p>
                        </button>
                    </div>
                    <p v-if="!playableGames.length" class="mt-2 text-xs text-rose-500">
                        هیچ بازی‌ای در Plan فعلی شما مجاز نیست؛ اشتراک را ارتقا دهید.
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm">
                        <span class="mb-1 block text-slate-500">شروع (اختیاری)</span>
                        <input v-model="form.starts_at" type="datetime-local" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 outline-none focus:border-violet-500" />
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block text-slate-500">پایان (اختیاری)</span>
                        <input v-model="form.ends_at" type="datetime-local" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 outline-none focus:border-violet-500" />
                    </label>
                </div>

                <p v-if="wizardError" class="rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-600">{{ wizardError }}</p>

                <div class="flex gap-2">
                    <button class="rounded-2xl bg-slate-100 px-5 py-3 font-bold text-slate-600" @click="step = 0">انصراف</button>
                    <button
                        class="flex-1 rounded-2xl bg-violet-600 py-3 font-bold text-white shadow disabled:opacity-50"
                        :disabled="!canNext"
                        @click="pickGame(form.game_code)"
                    >
                        مرحله بعد: پیکربندی بازی
                    </button>
                </div>
            </div>
        </div>

        <!-- مرحله ۲: پیکربندی پویا -->
        <div v-if="step === 2" class="mx-auto mt-5 max-w-2xl rounded-3xl bg-white p-6 shadow">
            <h2 class="font-black text-slate-700">۲. پیکربندی {{ selectedGame?.name }}</h2>
            <p class="mt-1 text-xs text-slate-400">
                این فرم به‌صورت پویا از JSON Schema پلاگین ساخته شده است. وزن‌ها و مراجع جایزه هرگز به مشتری نمایش داده نمی‌شوند.
            </p>

            <div class="mt-4">
                <DynamicForm v-if="schema" v-model="config" :schema="schema" />
            </div>

            <p v-if="wizardError" class="mt-3 rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-600">{{ wizardError }}</p>

            <div class="mt-5 flex gap-2">
                <button class="rounded-2xl bg-slate-100 px-5 py-3 font-bold text-slate-600" @click="step = 1">بازگشت</button>
                <button
                    class="flex-1 rounded-2xl bg-violet-600 py-3 font-bold text-white shadow disabled:opacity-50"
                    :disabled="busy"
                    @click="create"
                >
                    {{ busy ? 'در حال ساخت…' : 'ساخت کمپین (پیش‌نویس)' }}
                </button>
            </div>
        </div>
    </div>
</template>
