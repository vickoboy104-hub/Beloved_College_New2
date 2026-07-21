<?php

namespace App\Console\Commands;

use App\Services\Communication\CommunicationService;
use Illuminate\Console\Command;

class DispatchScheduledAnnouncements extends Command
{
    protected $signature = 'communications:dispatch-scheduled';

    protected $description = 'Dispatch due scheduled announcements and expire outdated announcements.';

    public function handle(CommunicationService $communication): int
    {
        $count = $communication->dispatchDue();
        $this->info($count.' notification deliveries were queued.');

        return self::SUCCESS;
    }
}
