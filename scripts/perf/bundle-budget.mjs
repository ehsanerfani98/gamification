#!/usr/bin/env node
/**
 * بودجه عملکرد فرانت — Sprint 4 (فصل ۹ سند معماری):
 *   «JS لندینگ < 150KB فشرده (gzip) برای هر نقطه ورود»
 *
 * روش: از manifest.json تولیدی Vite، گراف import استاتیک هر Entry را
 * دنبال می‌کنیم (chunkهای به‌اشتراک‌گذاشته‌شده مثل vendor) و مجموع gzip
 * فایل‌های JS گراف را با بودجه مقایسه می‌کنیم. chunkهای داینامیک
 * (بازی‌های lazy) در لندینگ بارگذاری نمی‌شوند و شمرده نمی‌شوند.
 *
 * اجرا: node scripts/perf/bundle-budget.mjs  (پس از npm run build)
 * خروجی: غیر صفر در صورت نقض بودجه (برای CI).
 */
import { existsSync, readFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { gzipSync } from 'node:zlib';

const BUILD_DIR = resolve(process.cwd(), 'public/build');
const MANIFEST = join(BUILD_DIR, 'manifest.json');
const BUDGET_BYTES = 150 * 1024; // 150KB فشرده — بودجه سند معماری

const ENTRIES = ['resources/pwa/main.js', 'resources/panel/main.js'];

if (!existsSync(MANIFEST)) {
    console.error(`✗ manifest پیدا نشد: ${MANIFEST} — ابتدا npm run build اجرا شود.`);
    process.exit(1);
}

const manifest = JSON.parse(readFileSync(MANIFEST, 'utf8'));

/** حجم gzip یک فایل ساخته‌شده */
function gzipSize(file) {
    const raw = readFileSync(join(BUILD_DIR, file));
    return gzipSync(raw, { level: 9 }).length;
}

/** دنبال‌کردن import استاتیک از داخل manifest — chunkهای داینامیک شمرده نمی‌شوند */
function collectInitialFiles(entryKey, seen = new Set()) {
    if (seen.has(entryKey)) return seen;
    seen.add(entryKey);

    const entry = manifest[entryKey];
    if (!entry) {
        console.error(`✗ کلید «${entryKey}» در manifest نیست.`);
        process.exit(1);
    }

    for (const imported of entry.imports ?? []) {
        collectInitialFiles(imported, seen);
    }

    return seen;
}

let failed = false;
const results = [];

for (const entrySrc of ENTRIES) {
    if (!manifest[entrySrc]) {
        console.error(`✗ نقطه ورود «${entrySrc}» در manifest نیست.`);
        failed = true;
        continue;
    }

    const files = collectInitialFiles(entrySrc);
    let total = 0;
    const lines = [];

    for (const key of files) {
        const file = manifest[key].file;
        if (!file.endsWith('.js')) continue;
        const size = gzipSize(file);
        total += size;
        lines.push(`      ${key.padEnd(34)} ${format(size)}`);
    }

    const ok = total <= BUDGET_BYTES;
    if (!ok) failed = true;

    results.push({ entrySrc, total, ok, lines });
}

console.log('\n📊 بودجه JS لندینگ (gzip، سطح فشرده‌سازی ۹):');
for (const { entrySrc, total, ok, lines } of results) {
    const label = ok ? '✓' : '✗';
    console.log(`  ${label} ${entrySrc} → ${format(total)} از سقف ${format(BUDGET_BYTES)}`);
    for (const line of lines) console.log(line);
}

// حجم کل همه JSهای تولیدشده — صرفاً گزارشی (بازی‌ها lazy هستند)
let allJs = 0;
let allJsCount = 0;
for (const entry of Object.values(manifest)) {
    if (entry.file?.endsWith('.js')) {
        allJs += gzipSize(entry.file);
        allJsCount += 1;
    }
}
console.log(`\n  ℹ️  کل ${allJsCount} chunk JS ساخته‌شده: ${format(allJs)} gzip (بازی‌ها lazy-load می‌شوند و در بودجه لندینگ نیستند)`);

if (failed) {
    console.error('\n✗ نقض بودجه عملکرد — خروجی vite را کوچک‌تر کنید.');
    process.exit(1);
}

console.log('\n✓ بودجه JS لندینگ رعایت شد.');

function format(bytes) {
    return `${(bytes / 1024).toFixed(1)}KB`;
}
