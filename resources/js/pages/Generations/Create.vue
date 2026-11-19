<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
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
</script>

<template>
    <Head title="Generate" />
    <div class="mx-auto w-full max-w-2xl space-y-6 p-6">
        <div>
            <h1 class="text-2xl font-semibold">Generate an image</h1>
            <p class="text-muted-foreground">
                Describe your image and see an estimate before submitting.
            </p>
        </div>
        <form class="space-y-5" @submit.prevent="form.post('/generations')">
            <div class="space-y-2">
                <label for="prompt" class="font-medium">Prompt</label
                ><textarea
                    id="prompt"
                    v-model="form.prompt"
                    maxlength="4000"
                    required
                    rows="5"
                    class="w-full rounded-md border bg-transparent p-3"
                /><InputError :message="form.errors.prompt" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                    <label for="size" class="font-medium">Size</label
                    ><select
                        id="size"
                        v-model="form.size"
                        class="w-full rounded-md border bg-background p-2"
                    >
                        <option>1024x1024</option>
                        <option>1536x1024</option>
                        <option>1024x1536</option></select
                    ><InputError :message="form.errors.size" />
                </div>
                <div class="space-y-2">
                    <label for="quality" class="font-medium">Quality</label
                    ><select
                        id="quality"
                        v-model="form.quality"
                        class="w-full rounded-md border bg-background p-2"
                    >
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option></select
                    ><InputError :message="form.errors.quality" />
                </div>
            </div>
            <div class="space-y-2">
                <p class="font-medium">
                    Reference image URLs (optional, up to 4)
                </p>
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
                            class="min-w-0 flex-1 rounded-md border bg-transparent p-2"
                        /><button
                            type="button"
                            class="underline"
                            @click="form.reference_images.splice(index, 1)"
                        >
                            Remove
                        </button>
                    </div>
                    <InputError
                        :message="form.errors[`reference_images.${index}`]"
                    />
                </div>
                <InputError :message="form.errors.reference_images" /><button
                    v-if="form.reference_images.length < 4"
                    type="button"
                    class="underline"
                    @click="form.reference_images.push('')"
                >
                    Add reference URL
                </button>
            </div>
            <div class="rounded-lg border p-4" aria-live="polite">
                <p class="font-medium">
                    Estimated charge: {{ formatNanos(price.min) }} –
                    {{ formatNanos(price.max) }}
                </p>
                <p class="text-sm text-muted-foreground">
                    Typical midpoint: {{ formatNanos(price.typical) }}. Includes
                    {{ markupPercent }}% markup. Actual usage may vary; failed
                    generations are free.
                </p>
            </div>
            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-md bg-primary px-4 py-2 text-primary-foreground disabled:opacity-50"
            >
                Submit generation
            </button>
            <Link href="/generations" class="ml-4 underline">View history</Link>
        </form>
    </div>
</template>
