<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { BarChart3, Images, Sparkles, Wallet } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatNanos } from '@/lib/pricing';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const page = usePage();
const credits = page.props.credits as {
    balance: number;
    reserved: number;
    available: number;
} | null;
const user = page.props.auth.user;
</script>

<template>
    <Head title="Dashboard" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-8 p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
        >
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight">
                    Welcome{{ user?.name ? `, ${user.name}` : '' }}
                </h1>
                <p class="text-muted-foreground">
                    Generate an image, review history, or add credits. Estimates
                    include a 25% markup; failed generations are free.
                </p>
            </div>
            <Button as-child>
                <Link href="/generations/create">
                    <Sparkles class="size-4" />
                    Generate an image
                </Link>
            </Button>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <Card>
                <CardHeader>
                    <CardDescription>Available</CardDescription>
                    <CardTitle class="font-mono text-xl tabular-nums">
                        {{
                            credits
                                ? formatNanos(credits.available)
                                : formatNanos(0)
                        }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-sm text-muted-foreground">
                    Balance minus amounts reserved for generations still in
                    progress.
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardDescription>Balance</CardDescription>
                    <CardTitle class="font-mono text-xl tabular-nums">
                        {{
                            credits
                                ? formatNanos(credits.balance)
                                : formatNanos(0)
                        }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-sm text-muted-foreground">
                    Ledger total from purchases and completed charges.
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardDescription>Reserved</CardDescription>
                    <CardTitle class="font-mono text-xl tabular-nums">
                        {{
                            credits
                                ? formatNanos(credits.reserved)
                                : formatNanos(0)
                        }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-sm text-muted-foreground">
                    Maximum estimates held for pending, queued, and processing
                    work.
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                href="/generations/create"
                class="rounded-xl border bg-card p-5 shadow-sm transition-colors hover:bg-accent/40"
            >
                <Sparkles class="mb-3 size-5 text-primary" />
                <h2 class="font-semibold">Generate</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Write a prompt, review the estimate, and submit.
                </p>
            </Link>
            <Link
                href="/generations"
                class="rounded-xl border bg-card p-5 shadow-sm transition-colors hover:bg-accent/40"
            >
                <Images class="mb-3 size-5 text-primary" />
                <h2 class="font-semibold">History</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Follow status, images, estimates, and actual charges.
                </p>
            </Link>
            <Link
                href="/credits"
                class="rounded-xl border bg-card p-5 shadow-sm transition-colors hover:bg-accent/40"
            >
                <Wallet class="mb-3 size-5 text-primary" />
                <h2 class="font-semibold">Credits</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Buy simulated credits and review the ledger.
                </p>
            </Link>
            <Link
                v-if="user.is_admin"
                href="/admin/report"
                class="rounded-xl border bg-card p-5 shadow-sm transition-colors hover:bg-accent/40"
            >
                <BarChart3 class="mb-3 size-5 text-primary" />
                <h2 class="font-semibold">Admin report</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Reconcile daily provider costs with Pixelforge usage.
                </p>
            </Link>
        </div>
    </div>
</template>
