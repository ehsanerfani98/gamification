import { defineAsyncComponent } from 'vue';

/**
 * Frontend Registry — فصل ۵-۵ و ۹ سند معماری.
 * کد بازی (سمت سرور) → کامپوننت UI با defineAsyncComponent (lazy-load).
 * افزودن بازی جدید = یک کامپوننت + یک خط در این Map؛ بدون تغییر هسته.
 */
export const gameRegistry = {
    wheel: defineAsyncComponent(() => import('./WheelGame.vue')),
    dice: defineAsyncComponent(() => import('./DiceGame.vue')),
    scratch: defineAsyncComponent(() => import('./ScratchGame.vue')),
    'pick-box': defineAsyncComponent(() => import('./PickBoxGame.vue')),
    'pick-card': defineAsyncComponent(() => import('./PickCardGame.vue')),
    'lucky-ticket': defineAsyncComponent(() => import('./LuckyTicketGame.vue')),
    quiz: defineAsyncComponent(() => import('./QuizGame.vue')),
    memory: defineAsyncComponent(() => import('./MemoryGame.vue')),
    reaction: defineAsyncComponent(() => import('./ReactionGame.vue')),
    claw: defineAsyncComponent(() => import('./ClawGame.vue')),
};

/** پالت پیش‌فرض Segmentها وقتی فروشگاه رنگی انتخاب نکرده است */
export const defaultPalette = [
    '#6d28d9', '#db2777', '#2563eb', '#ea580c',
    '#059669', '#d97706', '#dc2626', '#7c3aed',
    '#0891b2', '#4d7c0f', '#be185d', '#1d4ed8',
];
