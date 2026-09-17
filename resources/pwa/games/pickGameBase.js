/**
 * پایه مشترک بازی‌های انتخابی (Pick a Box / Pick a Card) — فصل ۵:
 * انتخاب کلاینت فقط نمایشی است (pick_index clamp می‌شود)؛ محتوای انتخاب با
 * weighted RNG سمت سرور تعیین شده و reveal_index از سرور می‌آید.
 */
import { computed, ref } from 'vue';

export function usePickGame(props, emit) {
    const count = computed(() => Number(props.config.boxes ?? props.config.cards ?? 3));
    const possible = computed(() => (props.config.prizes ?? []).map((p) => p.label).filter(Boolean));

    const picked = ref(null);
    const display = ref(null);
    const busy = ref(false);
    const error = ref('');

    async function pick(index) {
        if (busy.value || picked.value !== null) return;
        busy.value = true;
        error.value = '';
        try {
            const data = await props.resolve({ type: 'pick', payload: { pick_index: index } });
            picked.value = index;
            display.value = data.result?.display ?? {};
            setTimeout(() => {
                busy.value = false;
                emit('done', data);
            }, 1400);
        } catch (e) {
            busy.value = false;
            error.value = e.message ?? 'خطا در دریافت نتیجه';
        }
    }

    /** وضعیت ظاهری هر گزینه بعد از نتیجه: chosen (برنده/خالی) و reveal (برجسته‌سازی) */
    function stateOf(index) {
        if (picked.value === null) return 'idle';
        if (index === picked.value) return display.value?.content === 'prize' ? 'won' : 'lost';
        if (index === Number(display.value?.reveal_index ?? -1)) return 'reveal';
        return 'dim';
    }

    return { count, possible, picked, display, busy, error, pick, stateOf };
}
