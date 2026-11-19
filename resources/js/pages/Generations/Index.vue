<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { formatNanos } from '@/lib/pricing';

defineProps<{
    generations: {
        ulid: string;
        prompt: string;
        status: string;
        error_message: string | null;
        est_charge_min_nanos: number;
        est_charge_max_nanos: number;
    }[];
}>();
</script>
<template>
    <Head title="History" />
    <div class="mx-auto w-full max-w-2xl space-y-5 p-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">History</h1>
            <Link href="/generations/create" class="underline"
                >New generation</Link
            >
        </div>
        <p v-if="!generations.length" class="text-muted-foreground">
            No generations yet.
        </p>
        <article
            v-for="generation in generations"
            :key="generation.ulid"
            class="space-y-2 rounded-lg border p-4"
        >
            <p class="font-medium break-words">{{ generation.prompt }}</p>
            <p class="text-sm">Status: {{ generation.status }}</p>
            <p v-if="generation.error_message" class="text-sm text-destructive">
                {{ generation.error_message }}
            </p>
            <p class="text-sm text-muted-foreground">
                Estimated charge:
                {{ formatNanos(generation.est_charge_min_nanos) }} –
                {{ formatNanos(generation.est_charge_max_nanos) }}
            </p>
        </article>
    </div>
</template>
