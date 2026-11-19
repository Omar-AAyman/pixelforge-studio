export type Pricing = {
    rates: Record<string, string>;
    tokens: {
        characters_per_text_token: number;
        text_token_variance: number;
        per_reference_image: number;
        output: Record<string, Record<string, number>>;
        reasoning: Record<string, { min: number; max: number }>;
    };
};

function rateNanos(rate: string): number {
    const [whole, fraction = ''] = rate.split('.');
    return Number(whole) * 1000 + Number(fraction.padEnd(3, '0'));
}

export function estimate(
    prompt: string,
    size: string,
    quality: string,
    references: number,
    pricing: Pricing,
    markup: number,
) {
    const tokens = pricing.tokens;
    // Pixelforge counts Unicode code points, including parts of combined emoji.
    const text = Math.ceil(
        // eslint-disable-next-line typescript/no-misused-spread
        [...prompt].length / tokens.characters_per_text_token,
    );
    const fraction = String(tokens.text_token_variance).split('.')[1] ?? '';
    const scale = 10 ** fraction.length;
    const variance = Number(fraction);
    const common =
        references *
            tokens.per_reference_image *
            rateNanos(pricing.rates.input_image_tokens) +
        tokens.output[quality][size] * rateNanos(pricing.rates.output_tokens);
    const cost = (textTokens: number, reasoning: number) =>
        common +
        textTokens * rateNanos(pricing.rates.input_text_tokens) +
        reasoning * rateNanos(pricing.rates.reasoning_tokens);
    const charge = (value: number) => value + Math.ceil((value * markup) / 100);
    const min = charge(
        cost(
            Math.floor((text * (scale - variance)) / scale),
            tokens.reasoning[quality].min,
        ),
    );
    const max = charge(
        cost(
            Math.ceil((text * (scale + variance)) / scale),
            tokens.reasoning[quality].max,
        ),
    );
    return { min, max, typical: Math.floor((min + max) / 2) };
}

export function formatNanos(value: number): string {
    if (value < 0) return `-${formatNanos(-value)}`;
    return `$${Math.floor(value / 1_000_000_000)}.${String(value % 1_000_000_000).padStart(9, '0')}`;
}
