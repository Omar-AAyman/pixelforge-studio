<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

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
</script>

<template>
    <Head title="Admin report" />
    <div class="space-y-8 p-6">
        <div>
            <h1 class="text-2xl font-semibold">Admin report</h1>
            <p class="text-sm text-muted-foreground">
                Completed generations by UTC date. Amounts are exact USD.
            </p>
        </div>
        <form
            class="flex flex-wrap items-end gap-3"
            @submit.prevent="form.get('/admin/report')"
        >
            <label
                >From
                <input
                    v-model="form.start_date"
                    type="date"
                    required
                    class="block rounded border p-2"
            /></label>
            <label
                >To
                <input
                    v-model="form.end_date"
                    type="date"
                    required
                    class="block rounded border p-2"
            /></label>
            <button
                class="rounded bg-primary px-4 py-2 text-primary-foreground"
            >
                Show report
            </button>
        </form>
        <p
            v-if="form.errors.start_date || form.errors.end_date"
            class="text-red-600"
        >
            {{ form.errors.start_date || form.errors.end_date }}
        </p>
        <p v-if="!usageAvailable" class="text-red-600">
            Pixelforge usage is unavailable. Reconciliation could not be
            checked.
        </p>
        <section class="space-y-3">
            <h2 class="text-xl font-semibold">By day</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr>
                            <th>Date (UTC)</th>
                            <th>Credit spend</th>
                            <th>Provider cost</th>
                            <th>Profit</th>
                            <th>Pixelforge usage</th>
                            <th>Match</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="day in days"
                            :key="day.date"
                            class="border-t"
                        >
                            <td>{{ day.date }}</td>
                            <td>${{ day.charge }}</td>
                            <td>${{ day.cost }}</td>
                            <td>${{ day.profit }}</td>
                            <td>
                                {{
                                    day.provider === null
                                        ? 'Unavailable'
                                        : `$${day.provider}`
                                }}
                            </td>
                            <td>
                                {{
                                    day.matches === null
                                        ? 'Unavailable'
                                        : day.matches
                                          ? '✓ Match'
                                          : '✗ Mismatch'
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
        <section class="space-y-3">
            <h2 class="text-xl font-semibold">By user</h2>
            <p v-if="!users.length">No completed generations in this period.</p>
            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Credit spend</th>
                            <th>Provider cost</th>
                            <th>Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="user in users"
                            :key="user.id"
                            class="border-t"
                        >
                            <td>{{ user.name }} ({{ user.email }})</td>
                            <td>${{ user.charge }}</td>
                            <td>${{ user.cost }}</td>
                            <td>${{ user.profit }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
