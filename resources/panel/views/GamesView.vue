<script setup>
/** کتابخانه بازی — نشان دسترسی Plan و Schema پویا (فصل ۵-۵) */
import { onMounted, ref } from 'vue';
import { papi } from '../client.js';

const games = ref([]);
const categories = ref({});
const loading = ref(true);
const error = ref('');
const schemaOf = ref(null); // {game, schema}
const loadingSchema = ref(false);

const categoryNames = {
    chance: 'شانسی', skill: 'مهارتی', knowledge: 'دانش', memory: 'حافظه',
};

onMounted(load);

async function load() {
    loading.value = true;
    try {
        const data = await papi('/games');
        games.value = data.games ?? [];
        categories.value = data.categories ?? {};
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

async function showSchema(code) {
    loadingSchema.value = true;
    schemaOf.value = null;
    try {
        schemaOf.value = await papi(`/games/${code}/config-schema`);
    } catch (e) {
        error.value = e.message;
    } finally {
        loadingSchema.value = false;
    }
}
</script>

<template>
    <div>
        <h1 class="text-2xl font-black text-slate-800">کتابخانه بازی</h1>
        <p class="mt-1 text-sm text-slate-400">۱۰ بازی MVP — افزودن بازی جدید بدون تغییر هسته (پلاگین GameInterface)</p>

        <div v-if="loading" class="mt-6 text-slate-400">در حال بارگذاری…</div>
        <p v-else-if="error" class="mt-6 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-600">{{ error }}</p>

        <div v-else class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="g in games" :key="g.code" class="rounded-3xl bg-white p-5 shadow" :class="{ 'opacity-60': !g.plan_allowed }">
                <div class="flex items-start justify-between">
                    <p class="text-3xl">{{ { wheel: '🎡', dice: '🎲', scratch: '🎟️', 'pick-box': '🎁', 'pick-card': '🃏', 'lucky-ticket': '🎫', quiz: '🧠', memory: '🧩', reaction: '⚡', claw: '🕹️' }[g.code] ?? '🎮' }}</p>
                    <span v-if="g.plan_allowed" class="rounded-lg bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700">مجاز در Plan شما</span>
                    <span v-else class="rounded-lg bg-rose-100 px-2 py-0.5 text-xs text-rose-600">نیازمند ارتقا</span>
                </div>
                <h2 class="mt-3 font-bold text-slate-700">{{ g.name }}</h2>
                <p class="mt-1 line-clamp-2 min-h-10 text-xs text-slate-400">{{ g.description }}</p>
                <div class="mt-3 flex items-center justify-between">
                    <span class="rounded-lg bg-slate-100 px-2 py-0.5 text-xs text-slate-500">
                        {{ categoryNames[g.category] ?? g.category }}
                    </span>
                    <button class="text-xs font-bold text-violet-600" @click="showSchema(g.code)">مشاهده پیکربندی</button>
                </div>
            </div>
        </div>

        <!-- مودال Schema -->
        <div v-if="schemaOf" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="schemaOf = null">
            <div class="max-h-[80dvh] w-full max-w-2xl overflow-y-auto rounded-3xl bg-white p-6">
                <div class="flex items-center justify-between">
                    <h3 class="font-black text-slate-800">پیکربندی {{ schemaOf.game?.name }}</h3>
                    <button class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-500" @click="schemaOf = null">بستن</button>
                </div>
                <pre dir="ltr" class="mt-4 max-h-96 overflow-auto rounded-2xl bg-slate-900 p-4 text-left text-xs leading-5 text-emerald-300">{{ JSON.stringify(schemaOf.schema, null, 2) }}</pre>
                <p class="mt-3 text-xs text-slate-400">
                    این Schema در Wizard ساخت کمپین به‌صورت فرم پویا رندر می‌شود (فصل ۵-۳ سند معماری).
                </p>
            </div>
        </div>
    </div>
</template>
