<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 32 32"
        fill="currentColor"
        :class="className"
        v-bind="$attrs"
        aria-hidden="true"
    >
        <path
            fill-rule="evenodd"
            clip-rule="evenodd"
            d="M8 4h12a6 6 0 0 1 6 6v12a6 6 0 0 1-6 6H8a6 6 0 0 1-6-6V10a6 6 0 0 1 6-6zm0 2.5A3.5 3.5 0 0 0 4.5 10v12A3.5 3.5 0 0 0 8 25.5h12a3.5 3.5 0 0 0 3.5-3.5V10A3.5 3.5 0 0 0 20 6.5H8z"
        />
        <path d="M6.8 22.8 12.2 16l3.3 4 3.1-3.7 6.6 6.5H6.8z" />
        <path
            d="M24.5 2.8 25.8 6l3.2 1.3-3.2 1.3-1.3 3.2-1.3-3.2-3.2-1.3 3.2-1.3 1.3-3.2z"
        />
    </svg>
</template>
