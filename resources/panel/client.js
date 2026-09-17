/** کلاینت پنل — توکن فروشگاه‌دار + X-Store-Id (قلمرو جاری) */
import { api } from '../shared/api.js';

const TOKEN_KEY = 'gm:panel:token';
const STORE_KEY = 'gm:panel:store';

export const panelAuth = {
    get token() {
        try {
            return localStorage.getItem(TOKEN_KEY);
        } catch {
            return null;
        }
    },
    set(token) {
        try {
            token ? localStorage.setItem(TOKEN_KEY, token) : localStorage.removeItem(TOKEN_KEY);
        } catch { /* noop */ }
    },
};

export const panelStore = {
    get id() {
        try {
            const v = localStorage.getItem(STORE_KEY);
            return v ? Number(v) : null;
        } catch {
            return null;
        }
    },
    set(id) {
        try {
            id ? localStorage.setItem(STORE_KEY, String(id)) : localStorage.removeItem(STORE_KEY);
        } catch { /* noop */ }
    },
};

/** فراخوانی API پنل — توکن و Store جاری خودکار تزریق می‌شود */
export function papi(path, { auth = true, ...opts } = {}) {
    const headers = { ...opts.headers };
    if (auth && panelStore.id) headers['X-Store-Id'] = String(panelStore.id);
    return api(path, { ...opts, headers, token: auth ? panelAuth.token : undefined });
}
