import { createApp } from 'vue';
import { createRouter, createWebHashHistory } from 'vue-router';
import App from './App.vue';
import './panel.css';

import LoginView from './views/LoginView.vue';
import DashboardView from './views/DashboardView.vue';
import StoresView from './views/StoresView.vue';
import GamesView from './views/GamesView.vue';
import CampaignsView from './views/CampaignsView.vue';
import CampaignDetailView from './views/CampaignDetailView.vue';
import CouponsView from './views/CouponsView.vue';
import SubscriptionView from './views/SubscriptionView.vue';

const router = createRouter({
    history: createWebHashHistory('/panel'),
    routes: [
        { path: '/login', component: LoginView, meta: { public: true } },
        { path: '/', component: DashboardView },
        { path: '/stores', component: StoresView },
        { path: '/games', component: GamesView },
        { path: '/campaigns', component: CampaignsView },
        { path: '/campaigns/:id', component: CampaignDetailView, props: true },
        { path: '/coupons', component: CouponsView },
        { path: '/subscription', component: SubscriptionView },
    ],
});

router.beforeEach((to) => {
    const token = localStorage.getItem('gm:panel:token');
    if (!to.meta.public && !token) return '/login';
    if (to.path === '/login' && token) return '/';
});

createApp(App).use(router).mount('#app');
