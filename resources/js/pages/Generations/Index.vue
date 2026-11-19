<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { formatNanos } from '@/lib/pricing';

const props = defineProps<{
    generations: {
        ulid: string;
        prompt: string;
        status: string;
        error_message: string | null;
        est_charge_min_nanos: number;
        est_charge_max_nanos: number;
        charge_nanos: number | null;
        image_url: string | null;
        image_url_expires_at: string | null;
    }[];
}>();

const hasActive = computed(() =>
    props.generations.some((g) => !['succeeded', 'failed'].includes(g.status)),
);
const { start, stop } = usePoll(
    3000,
    { only: ['generations'] },
    { autoStart: false },
);
watch(hasActive, (active) => (active ? start() : stop()), { immediate: true });
const brokenImages = ref<string[]>([]);
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
            <template v-if="generation.status === 'succeeded'">
                <img
                    v-if="
                        generation.image_url &&
                        !brokenImages.includes(generation.ulid) &&
                        (!generation.image_url_expires_at ||
                            Date.parse(generation.image_url_expires_at) >
                                Date.now())
                    "
                    :src="generation.image_url"
                    :alt="generation.prompt"
                    class="w-full rounded-lg"
                    @error="brokenImages.push(generation.ulid)"
                />
                <p v-else class="text-sm text-muted-foreground">
                    Image unavailable or expired.
                </p>
            </template>
            <p v-if="generation.error_message" class="text-sm text-destructive">
                {{ generation.error_message }}
            </p>
            <p class="text-sm text-muted-foreground">
                Estimated charge:
                {{ formatNanos(generation.est_charge_min_nanos) }} –
                {{ formatNanos(generation.est_charge_max_nanos) }}
            </p>
            <p v-if="generation.charge_nanos !== null" class="text-sm">
                Actual charge: {{ formatNanos(generation.charge_nanos) }}
            </p>
        </article>
    </div>
</template>
