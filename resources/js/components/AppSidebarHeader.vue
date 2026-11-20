<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { formatNanos } from '@/lib/pricing';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <Link
            v-if="usePage().props.credits"
            href="/credits"
            class="ml-auto inline-flex max-w-[min(100%,24rem)] items-center gap-2 truncate rounded-full border bg-background px-3 py-1 text-xs font-medium transition-colors hover:bg-accent"
        >
            <span class="text-muted-foreground">Available</span>
            <span class="font-mono tabular-nums">
                {{
                    formatNanos(
                        (usePage().props.credits as { available: number })
                            .available,
                    )
                }}
            </span>
        </Link>
    </header>
</template>
