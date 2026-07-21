<?php

namespace App\Console\Commands;

use App\Models\SystemHeartbeat;
use Illuminate\Console\Command;

class RecordSystemHeartbeat extends Command
{
    protected $signature = 'system:heartbeat {service=scheduler}';

    protected $description = 'Record an operational heartbeat for a scheduled service.';

    public function handle(): int
    {
        $service = (string) $this->argument('service');

        SystemHeartbeat::query()->updateOrCreate(
            ['service' => $service],
            [
                'status' => 'healthy',
                'last_seen_at' => now(),
                'metadata' => [
                    'environment' => app()->environment(),
                    'hostname' => gethostname() ?: null,
                ],
            ],
        );

        $this->info($service.' heartbeat recorded.');

        return self::SUCCESS;
    }
}
