import { createApp } from 'vue';
import App from './App.vue';
import './pwa.css';

createApp(App).mount('#app');

// Service Worker فقط در production — در dev تداخل با HMR ندارد
if (import.meta.env.PROD && 'serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
