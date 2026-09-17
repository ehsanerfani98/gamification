<script setup>
/**
 * فرم پویا از JSON Schema پلاگین — فصل ۵-۳ سند معماری.
 * پشتیبانی از زیرمجموعه‌ای که GameInterface ها استفاده می‌کنند:
 * string/integer/boolean/enum، آبجکت تودرتو، آرایه آبجکت (تکرارشونده) و آرایه رشته.
 * اعتبارسنجی نهایی همیشه سمت سرور است؛ این فرم فقط ساخت داده تمیز را تضمین می‌کند.
 */
import { computed } from 'vue';

const props = defineProps({
    schema: { type: Object, required: true },
    modelValue: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['update:modelValue']);

const model = computed(() => props.modelValue ?? {});

function clone(v) {
    return v && typeof v === 'object' ? JSON.parse(JSON.stringify(v)) : v;
}

function set(path, value) {
    const next = clone(model.value);
    const keys = path.split('.');
    let cur = next;
    for (let i = 0; i < keys.length - 1; i++) {
        if (cur[keys[i]] === undefined) cur[keys[i]] = {};
        cur = cur[keys[i]];
    }
    if (value === undefined || value === '' || value === null) delete cur[keys.at(-1)];
    else cur[keys.at(-1)] = value;
    emit('update:modelValue', next);
}

function baseType(propSchema) {
    const t = propSchema?.type;
    const types = Array.isArray(t) ? t : [t];
    return { base: types.find((x) => x && x !== 'null') ?? 'string', nullable: types.includes('null') };
}

function rowDefault(itemSchema) {
    const out = {};
    for (const [key, sub] of Object.entries(itemSchema?.properties ?? {})) {
        const { base } = baseType(sub);
        if (Array.isArray(sub.type) && sub.type.includes('null')) out[key] = null;
        else if (sub.enum) out[key] = sub.enum[0];
        else if (base === 'integer' || base === 'number') out[key] = sub.minimum ?? 1;
        else if (base === 'boolean') out[key] = false;
        else if (base === 'array') out[key] = [];
        else out[key] = '';
    }
    return out;
}

function addRow(path, itemSchema) {
    const arr = Array.isArray(model.value[path]) ? clone(model.value[path]) : [];
    arr.push(rowDefault(itemSchema));
    set(path, arr);
}

function removeRow(path, index) {
    const arr = clone(model.value[path] ?? []);
    arr.splice(index, 1);
    set(path, arr);
}

/** کلیدهای آرایه‌رشته داخل آیتم‌های یک آرایه آبجکت (مثل options در questions) */
function stringArrayKeys(itemSchema) {
    return Object.entries(itemSchema?.properties ?? {})
        .filter(([, sub]) => baseType(sub).base === 'array' && sub.items?.type === 'string')
        .map(([key]) => key);
}

function addListItem(path, rowIndex, listKey) {
    const arr = clone(model.value[path] ?? []);
    if (!Array.isArray(arr[rowIndex][listKey])) arr[rowIndex][listKey] = [];
    arr[rowIndex][listKey].push('');
    set(path, arr);
}

function removeListItem(path, rowIndex, listKey, itemIndex) {
    const arr = clone(model.value[path] ?? []);
    arr[rowIndex][listKey].splice(itemIndex, 1);
    set(path, arr);
}

const fieldLabels = {
    label: 'برچسب', weight: 'وزن', reward_ref: 'مرجع جایزه (reward_ref)', color: 'رنگ',
    segments: 'سگمنت‌های چرخ', faces: 'وجه‌های تاس', prizes: 'جایزه‌ها', symbols: 'نمادها',
    questions: 'سؤال‌ها', options: 'گزینه‌ها', text: 'متن سؤال', grid: 'شبکه', rows: 'سطر', cols: 'ستون',
    pairs: 'تعداد جفت', moves_limit: 'حد حرکت', time_limit_seconds: 'محدودیت زمان (ثانیه)',
    match_required: 'حد نصاب نماد یکسان', probability_mode: 'مد احتمال', spins_per_user: 'چرخ هر کاربر',
    daily: 'روزانه', total: 'کل', animation_duration_ms: 'مدت انیمیشن (ms)', sound_enabled: 'صدا',
    boxes: 'تعداد جعبه', cards: 'تعداد کارت', dice_count: 'تعداد تاس', rounds: 'تعداد دور',
    threshold_ms: 'آستانه (ms)', duration_ms: 'مدت (ms)', difficulty: 'درجه سختی',
    pass_score: 'نمره قبولی', show_answers: 'مرور پاسخ‌ها', empty_weight: 'وزن حالت خالی',
};

function labelFor(key) {
    return fieldLabels[key] ?? key;
}
</script>

<template>
    <div class="space-y-5">
        <template v-for="(prop, key) in schema.properties ?? {}" :key="key">
            <!-- آبجکت تودرتو (مثل grid یا spins_per_user) -->
            <fieldset v-if="baseType(prop).base === 'object'" class="rounded-2xl border border-slate-200 p-4">
                <legend class="px-1 text-sm font-bold text-slate-600">{{ labelFor(key) }}</legend>
                <div class="grid gap-3 sm:grid-cols-3">
                    <label v-for="(sub, subKey) in prop.properties ?? {}" :key="subKey" class="block text-sm">
                        <span class="mb-1 block text-xs text-slate-500">{{ labelFor(subKey) }}</span>
                        <input
                            type="number"
                            :value="model[key]?.[subKey] ?? ''"
                            :min="sub.minimum"
                            :max="sub.maximum"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 outline-none focus:border-violet-500"
                            @input="set(`${key}.${subKey}`, $event.target.value === '' ? undefined : Number($event.target.value))"
                        />
                    </label>
                </div>
            </fieldset>

            <!-- آرایه آبجکت تکرارشونده (segments، faces، prizes، questions و…) -->
            <div v-else-if="baseType(prop).base === 'array' && prop.items?.type === 'object'" class="rounded-2xl border border-slate-200 p-4">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-bold text-slate-600">
                        {{ labelFor(key) }}
                        <span v-if="prop.minItems" class="text-xs font-normal text-rose-400">(حداقل {{ prop.minItems }})</span>
                    </p>
                    <button type="button" class="rounded-lg bg-violet-50 px-3 py-1 text-xs font-bold text-violet-600" @click="addRow(key, prop.items)">
                        + افزودن
                    </button>
                </div>

                <div v-for="(row, i) in model[key] ?? []" :key="i" class="mt-3 rounded-xl bg-slate-50 p-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-400">ردیف #{{ i + 1 }}</span>
                        <button type="button" class="text-xs text-rose-500" @click="removeRow(key, i)">حذف</button>
                    </div>
                    <div class="mt-2 grid gap-2 sm:grid-cols-4">
                        <label
                            v-for="(sub, subKey) in prop.items.properties ?? {}"
                            :key="subKey"
                            v-show="baseType(sub).base !== 'array'"
                            class="block text-sm"
                        >
                            <span class="mb-1 block text-xs text-slate-500">
                                {{ labelFor(subKey) }}
                                <b v-if="prop.items.required?.includes(subKey)" class="text-rose-400">*</b>
                            </span>

                            <select
                                v-if="sub.enum"
                                :value="row[subKey] ?? sub.enum[0]"
                                class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-sm"
                                @change="set(`${key}.${i}.${subKey}`, $event.target.value)"
                            >
                                <option v-for="opt in sub.enum" :key="opt" :value="opt">{{ opt }}</option>
                            </select>

                            <input
                                v-else-if="subKey === 'color'"
                                type="color"
                                :value="row[subKey] || '#6d28d9'"
                                class="w-full rounded-lg border border-slate-200 bg-white px-1 py-1"
                                @input="set(`${key}.${i}.${subKey}`, $event.target.value)"
                            />

                            <input
                                v-else
                                :type="baseType(sub).base === 'integer' || baseType(sub).base === 'number' ? 'number' : 'text'"
                                :value="row[subKey] ?? (Array.isArray(sub.type) && sub.type.includes('null') ? '' : '')"
                                :min="sub.minimum"
                                :max="sub.maximum"
                                :maxlength="sub.maxLength"
                                class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-sm outline-none focus:border-violet-500"
                                @input="set(`${key}.${i}.${subKey}`, baseType(sub).base === 'integer' ? Number($event.target.value) : $event.target.value)"
                            />
                        </label>
                    </div>

                    <!-- آرایه رشته داخل ردیف (گزینه‌های سؤال کوییز) -->
                    <div v-for="listKey in stringArrayKeys(prop.items)" :key="listKey" class="mt-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-500">{{ labelFor(listKey) }}</span>
                            <button type="button" class="rounded bg-white px-2 py-0.5 text-xs text-violet-600 shadow-sm" @click="addListItem(key, i, listKey)">
                                + افزودن
                            </button>
                        </div>
                        <div v-for="(item, j) in row[listKey] ?? []" :key="j" class="mt-1 flex gap-2">
                            <input
                                :value="item"
                                class="flex-1 rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-sm"
                                @input="set(`${key}.${i}.${listKey}.${j}`, $event.target.value)"
                            />
                            <button type="button" class="text-xs text-rose-400" @click="removeListItem(key, i, listKey, j)">✕</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- فیلدهای ساده: رشته/عدد/بولین/enum -->
            <label v-else class="block text-sm">
                <span class="mb-1 block text-xs text-slate-500">
                    {{ labelFor(key) }}
                    <b v-if="schema.required?.includes(key)" class="text-rose-400">*</b>
                </span>

                <select
                    v-if="prop.enum"
                    :value="model[key] ?? prop.enum[0]"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 outline-none focus:border-violet-500"
                    @change="set(key, $event.target.value)"
                >
                    <option v-for="opt in prop.enum" :key="opt" :value="opt">{{ opt }}</option>
                </select>

                <input
                    v-else-if="baseType(prop).base === 'boolean'"
                    type="checkbox"
                    :checked="model[key] ?? false"
                    class="mt-1"
                    @change="set(key, $event.target.checked)"
                />

                <input
                    v-else
                    :type="baseType(prop).base === 'integer' || baseType(prop).base === 'number' ? 'number' : 'text'"
                    :value="model[key] ?? ''"
                    :min="prop.minimum"
                    :max="prop.maximum"
                    :maxlength="prop.maxLength"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 outline-none focus:border-violet-500"
                    @input="set(key, baseType(prop).base === 'integer' ? ($event.target.value === '' ? undefined : Number($event.target.value)) : $event.target.value)"
                />
            </label>
        </template>
    </div>
</template>
