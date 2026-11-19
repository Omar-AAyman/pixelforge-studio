<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
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
</script>

<template>
    <Head title="Credits" />
    <div class="mx-auto w-full max-w-2xl space-y-6 p-6">
        <h1 class="text-2xl font-semibold">Credits</h1>
        <p>Balance: {{ formatNanos(page.props.credits.balance) }}</p>
        <p>
            Reserved for pending generations:
            {{ formatNanos(page.props.credits.reserved) }}
        </p>
        <p>Available: {{ formatNanos(page.props.credits.available) }}</p>
        <div class="space-x-2">
            <button
                v-for="amount in [5, 10, 20]"
                :key="amount"
                class="rounded-md bg-primary px-4 py-2 text-primary-foreground"
                @click="router.post('/credits', { dollars: amount })"
            >
                Buy ${{ amount }}
            </button>
        </div>
        <p class="text-sm text-muted-foreground">
            Purchases are simulated; no payment is taken.
        </p>
        <h2 class="text-xl font-semibold">Ledger</h2>
        <p v-if="!transactions.length">No transactions yet.</p>
        <ul class="divide-y">
            <li
                v-for="transaction in transactions"
                :key="transaction.id"
                class="flex justify-between gap-4 py-3"
            >
                <span
                    >{{ transaction.description }}
                    <span class="text-muted-foreground">{{
                        transaction.created_at
                    }}</span></span
                >
                <span>{{ formatNanos(transaction.amount_nanos) }}</span>
            </li>
        </ul>
    </div>
</template>
