<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { estimate, formatNanos, type Pricing } from '@/lib/pricing';

const props = defineProps<{ pricing: Pricing; markupPercent: number }>();
const form = useForm({
    prompt: '',
    size: '1024x1024',
    quality: 'medium',
    reference_images: [] as string[],
});
const price = computed(() =>
    estimate(
        form.prompt,
        form.size,
        form.quality,
        form.reference_images.length,
        props.pricing,
        props.markupPercent,
    ),
);
const promptLength = computed(() => Array.from(form.prompt).length);

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Generate', href: '/generations/create' }],
    },
});

// Shared look for the native selects and URL inputs (matches the UI kit's Input).
const fieldClass =
    'border-input focus-visible:border-ring focus-visible:ring-ring/50 h-9 w-full rounded-md border bg-background px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px] dark:bg-input/30';
</script>

<template>
    <Head title="Generate" />
    <div class="mx-auto w-full max-w-5xl space-y-6 p-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Generate an image
                </h1>
                <p class="text-muted-foreground">
                    Describe your image and see an estimate before submitting.
                </p>
            </div>
            <Button variant="outline" as-child>
                <Link href="/generations">View history</Link>
            </Button>
        </div>

        <form
            class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]"
            @submit.prevent="form.post('/generations')"
        >
            <div class="space-y-5">
                <div class="space-y-2">
                    <div class="flex items-center justify-between gap-3">
                        <Label for="prompt">Prompt</Label>
                        <span class="text-xs text-muted-foreground">
                            {{ promptLength }} / 4000
                        </span>
                    </div>
                    <textarea
                        id="prompt"
                        v-model="form.prompt"
                        maxlength="4000"
                        required
                        rows="7"
                        placeholder="Describe the image you want to generate…"
                        class="w-full rounded-md border border-input bg-transparent p-3 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                    />
                    <InputError :message="form.errors.prompt" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="size">Size</Label>
                        <select
                            id="size"
                            v-model="form.size"
                            :class="fieldClass"
                        >
                            <option>1024x1024</option>
                            <option>1536x1024</option>
                            <option>1024x1536</option>
                        </select>
                        <InputError :message="form.errors.size" />
                    </div>
                    <div class="space-y-2">
                        <Label for="quality">Quality</Label>
                        <select
                            id="quality"
                            v-model="form.quality"
                            :class="fieldClass"
                        >
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                        <InputError :message="form.errors.quality" />
                    </div>
                </div>
                <div class="space-y-2">
                    <Label>Reference image URLs (optional, up to 4)</Label>
                    <div
                        v-for="(_, index) in form.reference_images"
                        :key="index"
                        class="space-y-1"
                    >
                        <div class="flex gap-2">
                            <input
                                v-model="form.reference_images[index]"
                                :aria-label="`Reference image URL ${index + 1}`"
                                type="url"
                                required
                                placeholder="https://example.com/image.png"
                                :class="fieldClass"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                @click="form.reference_images.splice(index, 1)"
                            >
                                Remove
                            </Button>
                        </div>
                        <InputError
                            :message="form.errors[`reference_images.${index}`]"
                        />
                    </div>
                    <InputError :message="form.errors.reference_images" />
                    <Button
                        v-if="form.reference_images.length < 4"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="form.reference_images.push('')"
                    >
                        <Plus />
                        Add reference URL
                    </Button>
                </div>
                <InputError :message="usePage().props.errors.balance" />
                <Button
                    type="submit"
                    size="lg"
                    class="w-full sm:w-auto"
                    :disabled="form.processing"
                >
                    <Spinner v-if="form.processing" />
                    Submit generation
                </Button>
            </div>

            <Card
                class="border-primary/20 bg-muted/40 lg:sticky lg:top-20"
                aria-live="polite"
            >
                <CardHeader>
                    <CardDescription>Estimated charge</CardDescription>
                    <CardTitle
                        class="font-mono text-2xl leading-snug font-semibold tracking-tight tabular-nums"
                    >
                        {{ formatNanos(price.min) }}
                        <span class="text-muted-foreground">–</span>
                        {{ formatNanos(price.max) }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-3 text-sm text-muted-foreground">
                    <p>
                        Typical midpoint:
                        <span class="font-mono text-foreground tabular-nums">
                            {{ formatNanos(price.typical) }}
                        </span>
                    </p>
                    <p>
                        Includes {{ markupPercent }}% markup. Actual usage may
                        vary; failed generations are free.
                    </p>
                </CardContent>
            </Card>
        </form>
    </div>
</template>
