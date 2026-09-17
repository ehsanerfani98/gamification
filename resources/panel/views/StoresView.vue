<script setup>
/** فروشگاه‌ها — فهرست و ساخت Store جدید (slug خودکار از نام) */
import { onMounted, ref } from 'vue';
import { papi } from '../client.js';

const emit = defineEmits(['stores-changed']);

const stores = ref([]);
const loading = ref(true);
const error = ref('');
const name = ref('');
const busy = ref(false);
const created = ref(null);

function slugify(text) {
    return text
        .trim()
        .replace(/[\s_]+/g, '-')
        .replace(/[^\w\u0600-\u06FF-]/g, '')
        .toLowerCase() || 'store';
}

onMounted(load);

async function load() {
    loading.value = true;
    try {
        const data = await papi('/stores');
        stores.value = data.stores ?? data ?? [];
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

async function create() {
    if (!name.value.trim() || busy.value) return;
    busy.value = true;
    error.value = '';
    try {
        const data = await papi('/stores', {
            method: 'POST',
            body: { name: name.value.trim(), slug: slugify(name.value) + '-' + Math.random().toString(36).slice(2, 6) },
        });
        created.value = data.store ?? data;
        name.value = '';
        await load();
        emit('stores-changed');
    } catch (e) {
        error.value = e.message;
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div>
        <h1 class="text-2xl font-black text-slate-800">فروشگاه‌ها</h1>

        <div class="mt-5 grid gap-5 lg:grid-cols-2">
            <!-- فهرست -->
            <div class="rounded-3xl bg-white p-5 shadow">
                <h2 class="font-bold text-slate-700">فروشگاه‌های من</h2>
                <div v-if="loading" class="mt-4 text-sm text-slate-400">…</div>
                <p v-else-if="!stores.length" class="mt-4 text-sm text-slate-400">هنوز فروشگاهی نساخته‌اید.</p>
                <ul v-else class="mt-3 divide-y divide-slate-100">
                    <li v-for="s in stores" :key="s.id" class="flex items-center justify-between py-3">
                        <div>
                            <p class="font-bold text-slate-700">{{ s.name }}</p>
                            <p dir="ltr" class="text-right text-xs text-slate-400">/c/{{ s.slug }}</p>
                        </div>
                        <span class="rounded-lg bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700">{{ s.status === 'active' ? 'فعال' : s.status }}</span>
                    </li>
                </ul>
            </div>

            <!-- ساخت -->
            <div class="rounded-3xl bg-white p-5 shadow">
                <h2 class="font-bold text-slate-700">ساخت فروشگاه جدید</h2>
                <div class="mt-4 space-y-3">
                    <input
                        v-model="name"
                        placeholder="مثلاً: کافه ما"
                        class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 outline-none focus:border-violet-500"
                    />
                    <p v-if="error" class="rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-600">{{ error }}</p>
                    <p v-if="created" class="rounded-xl bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                        فروشگاه «{{ created.name }}» ساخته شد ✓
                    </p>
                    <button
                        class="w-full rounded-2xl bg-violet-600 py-3 font-bold text-white shadow disabled:opacity-50"
                        :disabled="busy || !name.trim()"
                        @click="create"
                    >
                        {{ busy ? 'در حال ساخت…' : 'ساخت فروشگاه' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
