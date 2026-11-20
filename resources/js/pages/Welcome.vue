<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ImageIcon, ShieldCheck, Sparkles } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatNanos } from '@/lib/pricing';
import { dashboard, login, register } from '@/routes';

const user = usePage().props.auth.user;

// A real generation from the live app (gen_01M7X8SQ4QS0DF77HNEQJCTCT3).
// The image is saved in public/images because Pixelforge's URLs expire.
const sample = {
    image: '/images/landing-sample.png',
    prompt: '[succeed] Quiet harbor lanterns, cheap demo 4',
    size: '1024×1024',
    quality: 'low',
    estMinNanos: 14_062_500,
    estMaxNanos: 21_900_000,
    chargeNanos: 14_831_250,
};

const features = [
    {
        icon: Sparkles,
        title: 'Cost before you submit',
        text: 'See an estimated charge range, including markup, as you write a prompt and choose size and quality.',
    },
    {
        icon: ImageIcon,
        title: 'Live status and history',
        text: 'Follow each generation from queued to processing, then succeeded or failed, with the image and the final charge.',
    },
    {
        icon: ShieldCheck,
        title: 'Accurate billing',
        text: 'Final charges use actual token usage. Webhooks are signature-checked, and a generation is never charged twice.',
    },
];
</script>

<template>
    <Head title="Image generation with exact pricing" />

    <div class="flex min-h-svh flex-col bg-background text-foreground">
        <header
            class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-6 py-5"
        >
            <Link href="/" class="flex items-center gap-2 font-semibold">
                <span
                    class="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground"
                >
                    <AppLogoIcon class="size-5" />
                </span>
                <span class="hidden sm:inline">Pixelforge Studio</span>
            </Link>
            <nav class="flex items-center gap-2">
                <Button v-if="user" as-child>
                    <Link :href="dashboard()">Go to dashboard</Link>
                </Button>
                <template v-else>
                    <Button variant="ghost" as-child>
                        <Link :href="login()">Log in</Link>
                    </Button>
                    <Button as-child>
                        <Link :href="register()">Get started</Link>
                    </Button>
                </template>
            </nav>
        </header>

        <main class="mx-auto w-full max-w-6xl flex-1 px-6 pt-8 pb-16 sm:pt-16">
            <section class="grid items-center gap-12 lg:grid-cols-2">
                <div class="space-y-6">
                    <Badge variant="secondary" class="rounded-full px-3 py-1">
                        Image generation, with exact pricing
                    </Badge>
                    <h1
                        class="text-4xl font-semibold tracking-tight text-balance sm:text-5xl"
                    >
                        Generate images and know what they cost before you
                        submit.
                    </h1>
                    <p
                        class="max-w-xl text-lg leading-relaxed text-muted-foreground"
                    >
                        Pixelforge Studio sends your prompt to Pixelforge,
                        tracks each generation as it runs, and records the exact
                        charge: Pixelforge's cost plus a 25% markup. Failed
                        generations are free.
                    </p>
                    <div class="flex flex-wrap items-center gap-3">
                        <Button size="lg" as-child>
                            <Link :href="user ? dashboard() : register()">
                                {{ user ? 'Go to dashboard' : 'Get started' }}
                            </Link>
                        </Button>
                        <Button size="lg" variant="outline" as-child>
                            <Link
                                :href="user ? '/generations/create' : login()"
                            >
                                {{ user ? 'Generate an image' : 'Log in' }}
                            </Link>
                        </Button>
                    </div>
                </div>

                <!-- A real generation from the live app, shown as a preview. -->
                <figure
                    class="mx-auto w-full max-w-md rounded-2xl border bg-card p-5 shadow-sm lg:mr-0"
                >
                    <img
                        :src="sample.image"
                        :alt="sample.prompt"
                        width="1024"
                        height="1024"
                        class="aspect-square w-full rounded-xl object-cover"
                    />
                    <figcaption>
                        <div
                            class="mt-4 flex items-start justify-between gap-4"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">
                                    {{ sample.prompt }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ sample.size }} ·
                                    {{ sample.quality }} quality
                                </p>
                            </div>
                            <Badge
                                class="border-transparent bg-emerald-600/15 text-emerald-700 dark:text-emerald-400"
                            >
                                Succeeded
                            </Badge>
                        </div>
                        <div
                            class="mt-4 flex flex-wrap justify-between gap-3 rounded-lg bg-muted/60 p-3 text-xs"
                        >
                            <div>
                                <p class="text-muted-foreground">Estimated</p>
                                <p
                                    class="mt-0.5 font-mono whitespace-nowrap tabular-nums"
                                >
                                    {{ formatNanos(sample.estMinNanos) }} –
                                    {{ formatNanos(sample.estMaxNanos) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-muted-foreground">Charged</p>
                                <p
                                    class="mt-0.5 font-mono font-medium tabular-nums"
                                >
                                    {{ formatNanos(sample.chargeNanos) }}
                                </p>
                            </div>
                        </div>
                    </figcaption>
                </figure>
            </section>

            <section class="mt-20 grid gap-4 sm:grid-cols-3">
                <article
                    v-for="feature in features"
                    :key="feature.title"
                    class="rounded-xl border bg-card p-5 shadow-sm"
                >
                    <span
                        class="mb-4 flex size-9 items-center justify-center rounded-lg bg-muted"
                    >
                        <component :is="feature.icon" class="size-5" />
                    </span>
                    <h2 class="font-semibold">{{ feature.title }}</h2>
                    <p
                        class="mt-2 text-sm leading-relaxed text-muted-foreground"
                    >
                        {{ feature.text }}
                    </p>
                </article>
            </section>

            <section class="mt-12 rounded-xl border bg-muted/40 p-6 sm:p-8">
                <h2 class="text-xl font-semibold tracking-tight">
                    How pricing works
                </h2>
                <ul
                    class="mt-4 list-disc space-y-3 pl-5 text-sm leading-relaxed text-muted-foreground"
                >
                    <li>
                        Provider cost comes from Pixelforge's token usage and
                        published rates. You pay that cost plus a 25% markup,
                        rounded up to the next nanodollar.
                    </li>
                    <li>
                        The price shown before you submit is an estimate. Actual
                        text and reasoning tokens are only known once a
                        generation succeeds.
                    </li>
                    <li>
                        Failed generations cost nothing. Credits are simulated
                        US dollars, so no real payment is taken.
                    </li>
                </ul>
            </section>
        </main>

        <footer class="border-t">
            <div
                class="mx-auto flex w-full max-w-6xl flex-col gap-2 px-6 py-6 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between"
            >
                <p>Pixelforge Studio · a PromptEdit take-home</p>
                <a
                    href="https://github.com/Omar-AAyman/pixelforge-studio"
                    class="hover:text-foreground"
                    target="_blank"
                    rel="noopener"
                >
                    Source on GitHub
                </a>
            </div>
        </footer>
    </div>
</template>
