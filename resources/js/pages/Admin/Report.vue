<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Amounts {
    cost: string;
    charge: string;
    profit: string;
}
const props = defineProps<{
    startDate: string;
    endDate: string;
    days: (Amounts & {
        date: string;
        provider: string | null;
        matches: boolean | null;
    })[];
    users: (Amounts & { id: number; name: string; email: string })[];
    usageAvailable: boolean;
}>();
const form = useForm({ start_date: props.startDate, end_date: props.endDate });

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Admin report', href: '/admin/report' }],
    },
});

const th = 'px-4 py-3 font-medium whitespace-nowrap';
const td = 'px-4 py-2.5 whitespace-nowrap';
</script>

<template>
    <Head title="Admin report" />
    <div class="mx-auto w-full max-w-6xl space-y-6 p-6">
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Admin report
                </h1>
                <p class="text-muted-foreground">
                    Completed generations by UTC date, checked against
                    Pixelforge's usage. Amounts are exact USD.
                </p>
            </div>
            <form
                class="flex flex-wrap items-end gap-3"
                @submit.prevent="form.get('/admin/report')"
            >
                <div class="space-y-1.5">
                    <Label for="start_date">From</Label>
                    <Input
                        id="start_date"
                        v-model="form.start_date"
                        type="date"
                        required
                    />
                </div>
                <div class="space-y-1.5">
                    <Label for="end_date">To</Label>
                    <Input
                        id="end_date"
                        v-model="form.end_date"
                        type="date"
                        required
                    />
                </div>
                <Button type="submit">Show report</Button>
            </form>
        </div>
        <InputError :message="form.errors.start_date || form.errors.end_date" />
        <p
            v-if="!usageAvailable"
            class="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
        >
            Pixelforge usage is unavailable. Reconciliation could not be
            checked.
        </p>

        <section class="space-y-3">
            <h2 class="text-lg font-semibold">By day</h2>
            <div class="overflow-x-auto rounded-xl border bg-card">
                <table class="w-full text-sm">
                    <thead
                        class="border-b bg-muted/50 text-left text-muted-foreground"
                    >
                        <tr>
                            <th :class="th">Date (UTC)</th>
                            <th :class="[th, 'text-right']">Credit spend</th>
                            <th :class="[th, 'text-right']">Provider cost</th>
                            <th :class="[th, 'text-right']">Profit</th>
                            <th :class="[th, 'text-right']">
                                Pixelforge usage
                            </th>
                            <th :class="th">Match</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y font-mono tabular-nums">
                        <tr v-for="day in days" :key="day.date">
                            <td :class="td">{{ day.date }}</td>
                            <td :class="[td, 'text-right']">
                                ${{ day.charge }}
                            </td>
                            <td :class="[td, 'text-right']">${{ day.cost }}</td>
                            <td :class="[td, 'text-right']">
                                ${{ day.profit }}
                            </td>
                            <td :class="[td, 'text-right']">
                                {{
                                    day.provider === null
                                        ? 'Unavailable'
                                        : `$${day.provider}`
                                }}
                            </td>
                            <td :class="[td, 'font-sans']">
                                <Badge
                                    v-if="day.matches === null"
                                    variant="secondary"
                                >
                                    Unavailable
                                </Badge>
                                <Badge
                                    v-else-if="day.matches"
                                    class="border-transparent bg-emerald-600/15 text-emerald-700 dark:text-emerald-400"
                                >
                                    Match
                                </Badge>
                                <Badge
                                    v-else
                                    class="border-transparent bg-destructive/15 text-destructive"
                                >
                                    Mismatch
                                </Badge>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="text-lg font-semibold">By user</h2>
            <p
                v-if="!users.length"
                class="rounded-xl border border-dashed px-6 py-10 text-center text-sm text-muted-foreground"
            >
                No completed generations in this period.
            </p>
            <div v-else class="overflow-x-auto rounded-xl border bg-card">
                <table class="w-full text-sm">
                    <thead
                        class="border-b bg-muted/50 text-left text-muted-foreground"
                    >
                        <tr>
                            <th :class="th">User</th>
                            <th :class="[th, 'text-right']">Credit spend</th>
                            <th :class="[th, 'text-right']">Provider cost</th>
                            <th :class="[th, 'text-right']">Profit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="user in users" :key="user.id">
                            <td :class="td">
                                <p class="font-medium">{{ user.name }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ user.email }}
                                </p>
                            </td>
                            <td
                                :class="[
                                    td,
                                    'text-right font-mono tabular-nums',
                                ]"
                            >
                                ${{ user.charge }}
                            </td>
                            <td
                                :class="[
                                    td,
                                    'text-right font-mono tabular-nums',
                                ]"
                            >
                                ${{ user.cost }}
                            </td>
                            <td
                                :class="[
                                    td,
                                    'text-right font-mono tabular-nums',
                                ]"
                            >
                                ${{ user.profit }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
