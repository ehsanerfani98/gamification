<script setup>
/** شل پنل — نوار کناری RTL + انتخاب Store + محتوای route */
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { panelAuth, panelStore, papi } from './client.js';

const route = useRoute();
const stores = ref([]);
const currentStore = ref(null);

const nav = [
    { to: '/', label: 'داشبورد', icon: '🏠' },
    { to: '/campaigns', label: 'کمپین‌ها', icon: '🎯' },
    { to: '/games', label: 'کتابخانه بازی', icon: '🎮' },
    { to: '/coupons', label: 'ثبت استفاده کوپن', icon: '🎟️' },
    { to: '/subscription', label: 'اشتراک و Plan', icon: '💎' },
    { to: '/stores', label: 'فروشگاه‌ها', icon: '🏬' },
];

async function loadStores() {
    try {
        const data = await papi('/stores');
        stores.value = data.stores ?? data;
        const id = panelStore.id;
        currentStore.value = stores.value.find((s) => s.id === id) ?? stores.value[0] ?? null;
        if (currentStore.value) panelStore.set(currentStore.value.id);
    } catch { /* login view handles auth errors */ }
}

function pick(s) {
    currentStore.value = s;
    panelStore.set(s.id);
}

function logout() {
    panelAuth.set(null);
    panelStore.set(null);
    window.location.hash = '#/login';
}

onMounted(() => {
    if (panelAuth.token) loadStores();
    // پس از ورود موفق در LoginView، فروشگاه‌ها بارگذاری می‌شوند
    window.addEventListener('gm:login', loadStores);
});
</script>

<template>
    <!-- صفحه ورود بدون شل -->
    <router-view v-if="route.meta.public" />

    <div v-else class="flex min-h-dvh">
        <!-- نوار کناری -->
        <aside class="sticky top-0 hidden h-dvh w-64 shrink-0 flex-col bg-slate-900 text-slate-300 md:flex">
            <div class="border-b border-white/10 p-5">
                <p class="text-lg font-black text-white">🎮 گیمیفیکیشن</p>
                <p class="mt-0.5 text-xs text-slate-400">پنل فروشگاه‌دار</p>
            </div>

            <nav class="flex-1 space-y-1 p-3">
                <router-link
                    v-for="item in nav"
                    :key="item.to + item.label"
                    :to="item.to"
                    class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm transition hover:bg-white/10"
                    :class="route.path === item.to ? 'bg-violet-600 font-bold text-white' : ''"
                >
                    <span>{{ item.icon }}</span>
                    {{ item.label }}
                </router-link>
            </nav>

            <button class="m-3 rounded-xl bg-white/5 px-4 py-2.5 text-right text-sm text-rose-300 hover:bg-white/10" @click="logout">
                خروج ←
            </button>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <!-- سربرگ: انتخاب فروشگاه -->
            <header class="flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-5 py-3">
                <select
                    v-if="stores.length"
                    class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-bold"
                    :value="currentStore?.id"
                    @change="pick(stores.find((s) => s.id === Number($event.target.value)))"
                >
                    <option v-for="s in stores" :key="s.id" :value="s.id">🏬 {{ s.name }}</option>
                </select>
                <router-link v-else to="/stores" class="rounded-xl bg-violet-600 px-4 py-2 text-sm font-bold text-white">
                    + ساخت فروشگاه اول
                </router-link>

                <button class="rounded-xl bg-slate-100 px-3 py-2 text-xs text-slate-500 md:hidden" @click="logout">خروج</button>
            </header>

            <!-- ناوبری موبایل -->
            <nav class="flex gap-2 overflow-x-auto border-b border-slate-200 bg-white px-3 py-2 md:hidden">
                <router-link
                    v-for="item in nav"
                    :key="'m' + item.to + item.label"
                    :to="item.to"
                    class="whitespace-nowrap rounded-lg px-3 py-1.5 text-xs"
                    :class="route.path === item.to ? 'bg-violet-600 font-bold text-white' : 'bg-slate-100 text-slate-600'"
                >
                    {{ item.icon }} {{ item.label }}
                </router-link>
            </nav>

            <main class="flex-1 p-5">
                <router-view @stores-changed="loadStores" />
            </main>
        </div>
    </div>
</template>
