/**
 * کلاینت API — /api/v1 (فصل ۸ سند معماری)
 * پاسخ موفق: {data} — پاسخ خطا: {error:{code,message,fields}} → ApiError
 *
 * امنیت توکن: توکن مشتری به‌ازای هر کمپین (slug) جداگانه ذخیره می‌شود تا
 * توکن محدود یک Store به صفحه کمپین Store دیگر نشت نکند.
 */
const BASE = '/api/v1';

export class ApiError extends Error {
    constructor(code, message, status, fields) {
        super(message);
        this.name = 'ApiError';
        this.code = code;
        this.status = status;
        this.fields = fields ?? {};
    }
}

export function tokenKey(slug) {
    return `gm:token:${slug}`;
}

export function getToken(slug) {
    try {
        return localStorage.getItem(tokenKey(slug));
    } catch {
        return null;
    }
}

export function setToken(slug, token) {
    try {
        if (token) localStorage.setItem(tokenKey(slug), token);
        else localStorage.removeItem(tokenKey(slug));
    } catch {
        /* private mode */
    }
}

export async function api(path, { method = 'GET', body, token, idempotencyKey } = {}) {
    const headers = { Accept: 'application/json' };
    if (body !== undefined) headers['Content-Type'] = 'application/json';
    if (token) headers.Authorization = `Bearer ${token}`;
    if (idempotencyKey) headers['Idempotency-Key'] = idempotencyKey;

    let res;
    try {
        res = await fetch(BASE + path, {
            method,
            headers,
            body: body !== undefined ? JSON.stringify(body) : undefined,
        });
    } catch {
        throw new ApiError('NETWORK', 'اتصال برقرار نشد؛ اینترنت را بررسی کنید.', 0);
    }

    let json = null;
    try {
        json = await res.json();
    } catch {
        /* پاسخ غیر JSON (مثلاً 404 HTML) */
    }

    if (!res.ok) {
        const err = json?.error ?? {};
        throw new ApiError(
            err.code ?? `HTTP_${res.status}`,
            err.message ?? 'خطای غیرمنتظره رخ داد.',
            res.status,
            err.fields,
        );
    }

    return json?.data ?? json;
}

/** شناسه یکتا برای Idempotency-Key (فصل ۸-۳) */
export function uuid() {
    if (crypto.randomUUID) return crypto.randomUUID();
    return 'idem-' + Math.random().toString(36).slice(2) + Date.now().toString(36);
}
