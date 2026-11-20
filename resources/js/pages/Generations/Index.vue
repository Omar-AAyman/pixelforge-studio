<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { ImageOff } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'History', href: '/generations' }],
    },
});

function hasImage(generation: (typeof props.generations)[number]): boolean {
    return Boolean(
        generation.image_url &&
        !brokenImages.value.includes(generation.ulid) &&
        (!generation.image_url_expires_at ||
            Date.parse(generation.image_url_expires_at) > Date.now()),
    );
}

function statusLabel(status: string): string {
    return status.charAt(0).toUpperCase() + status.slice(1);
}

function statusClass(status: string): string {
    switch (status) {
        case 'succeeded':
            return 'border-transparent bg-emerald-600/15 text-emerald-700 dark:text-emerald-400';
        case 'failed':
            return 'border-transparent bg-destructive/15 text-destructive';
        case 'processing':
            return 'border-transparent bg-amber-500/15 text-amber-800 dark:text-amber-400';
        default:
            return '';
    }
}
</script>

<template>
    <Head title="History" />
    <div class="mx-auto w-full max-w-5xl space-y-6 p-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">History</h1>
                <p class="text-muted-foreground">
                    Live status, estimates, and the actual charge for each
                    generation.
                </p>
            </div>
            <Button as-child>
                <Link href="/generations/create">New generation</Link>
            </Button>
        </div>

        <div
            v-if="!generations.length"
            class="flex flex-col items-center justify-center rounded-xl border border-dashed bg-card px-6 py-16 text-center"
        >
            <p class="font-medium">No generations yet</p>
            <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                Submit a prompt to create your first image. You'll see status
                and pricing here as it runs.
            </p>
            <Button class="mt-4" as-child>
                <Link href="/generations/create">Generate an image</Link>
            </Button>
        </div>

        <div v-else class="overflow-hidden rounded-xl border bg-card">
            <ul class="divide-y">
                <li
                    v-for="generation in generations"
                    :key="generation.ulid"
                    class="flex gap-4 p-4"
                >
                    <div
                        class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-muted"
                    >
                        <img
                            v-if="
                                generation.status === 'succeeded' &&
                                hasImage(generation)
                            "
                            :src="generation.image_url!"
                            :alt="generation.prompt"
                            class="size-full object-cover"
                            @error="brokenImages.push(generation.ulid)"
                        />
                        <ImageOff v-else class="size-5 text-muted-foreground" />
                    </div>
                    <div class="min-w-0 flex-1 space-y-2">
                        <div
                            class="flex flex-wrap items-start justify-between gap-2"
                        >
                            <p class="font-medium break-words">
                                {{ generation.prompt }}
                            </p>
                            <Badge
                                :variant="
                                    statusClass(generation.status)
                                        ? 'outline'
                                        : 'secondary'
                                "
                                :class="statusClass(generation.status)"
                            >
                                {{ statusLabel(generation.status) }}
                            </Badge>
                        </div>
                        <p
                            v-if="generation.error_message"
                            class="text-sm text-destructive"
                        >
                            {{ generation.error_message }}
                        </p>
                        <div
                            class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-muted-foreground"
                        >
                            <span>
                                Estimated
                                <span class="font-mono tabular-nums">
                                    {{
                                        formatNanos(
                                            generation.est_charge_min_nanos,
                                        )
                                    }}
                                    –
                                    {{
                                        formatNanos(
                                            generation.est_charge_max_nanos,
                                        )
                                    }}
                                </span>
                            </span>
                            <span v-if="generation.charge_nanos !== null">
                                Actual
                                <span
                                    class="font-mono text-foreground tabular-nums"
                                >
                                    {{ formatNanos(generation.charge_nanos) }}
                                </span>
                            </span>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>
