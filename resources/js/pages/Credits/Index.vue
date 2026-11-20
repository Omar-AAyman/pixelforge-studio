<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ReceiptText } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { formatNanos } from '@/lib/pricing';

defineProps<{
    transactions: {
        id: number;
        description: string;
        amount_nanos: number;
        created_at: string;
    }[];
}>();
const page = usePage<{
    credits: { balance: number; reserved: number; available: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Credits', href: '/credits' }],
    },
});
</script>

<template>
    <Head title="Credits" />
    <div class="mx-auto w-full max-w-5xl space-y-6 p-6">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Credits</h1>
            <p class="text-muted-foreground">
                Your balance and every purchase and charge.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border bg-card p-5">
                <p class="text-sm text-muted-foreground">Available</p>
                <p class="mt-1 font-mono text-xl font-semibold tabular-nums">
                    {{ formatNanos(page.props.credits.available) }}
                </p>
            </div>
            <div class="rounded-xl border bg-card p-5">
                <p class="text-sm text-muted-foreground">Balance</p>
                <p class="mt-1 font-mono text-xl tabular-nums">
                    {{ formatNanos(page.props.credits.balance) }}
                </p>
            </div>
            <div class="rounded-xl border bg-card p-5">
                <p class="text-sm text-muted-foreground">
                    Reserved for pending generations
                </p>
                <p class="mt-1 font-mono text-xl tabular-nums">
                    {{ formatNanos(page.props.credits.reserved) }}
                </p>
            </div>
        </div>

        <div
            class="flex flex-col gap-4 rounded-xl border bg-card p-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h2 class="font-semibold">Buy credits</h2>
                <p class="text-sm text-muted-foreground">
                    Purchases are simulated; no payment is taken.
                </p>
            </div>
            <div class="flex gap-2">
                <Button
                    v-for="amount in [5, 10, 20]"
                    :key="amount"
                    @click="router.post('/credits', { dollars: amount })"
                >
                    Buy ${{ amount }}
                </Button>
            </div>
        </div>

        <section class="space-y-3">
            <h2 class="text-lg font-semibold">Ledger</h2>
            <div
                v-if="!transactions.length"
                class="flex flex-col items-center rounded-xl border border-dashed px-6 py-12 text-center"
            >
                <ReceiptText class="size-6 text-muted-foreground" />
                <p class="mt-3 font-medium">No transactions yet</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Buy credits to get started. Charges appear here when a
                    generation succeeds.
                </p>
            </div>
            <div v-else class="overflow-x-auto rounded-xl border bg-card">
                <table class="w-full text-sm">
                    <thead
                        class="border-b bg-muted/50 text-left text-muted-foreground"
                    >
                        <tr>
                            <th class="px-4 py-3 font-medium">Description</th>
                            <th class="px-4 py-3 font-medium">Date</th>
                            <th class="px-4 py-3 text-right font-medium">
                                Amount
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="transaction in transactions"
                            :key="transaction.id"
                        >
                            <td class="px-4 py-3">
                                {{ transaction.description }}
                            </td>
                            <td
                                class="px-4 py-3 whitespace-nowrap text-muted-foreground"
                            >
                                {{ transaction.created_at }}
                            </td>
                            <td
                                class="px-4 py-3 text-right font-mono whitespace-nowrap tabular-nums"
                                :class="
                                    transaction.amount_nanos > 0
                                        ? 'text-emerald-700 dark:text-emerald-400'
                                        : ''
                                "
                            >
                                {{ formatNanos(transaction.amount_nanos) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
