<?php

namespace App\Console\Commands;

use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgeException;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;

class PixelforgeCheck extends Command
{
    protected $signature = 'pixelforge:check';

    protected $description = 'Check Pixelforge pricing and authenticated usage';

    public function handle(PixelforgeClient $client): int
    {
        try {
            $pricing = $client->pricing();
            $usage = $client->usage();
        } catch (PixelforgeException|ConnectionException $e) {
            $this->error('Pixelforge connection failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Rates (USD per 1M tokens):');
        $rates = [];
        foreach ($pricing['rates'] as $type => $rate) {
            $rates[] = [$type, $rate];
        }
        $this->table(['Token type', 'Rate'], $rates);
        $this->info(sprintf('Usage %s → %s: %d billed generations, provider cost $%s',
            $usage['start_date'], $usage['end_date'], $usage['total']['generations'], $usage['total']['cost_usd']));

        return self::SUCCESS;
    }
}
