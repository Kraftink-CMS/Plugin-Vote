<?php

namespace KraftInk\Plugins\Vote\Console\Commands;

use Illuminate\Console\Command;
use KraftInk\Plugins\Vote\Services\Rewards\RewardEngine;

class ProcessRewards extends Command
{
    protected $signature = 'vote:rewards {--limit=200}';

    protected $description = 'Process confirmed vote rewards and closed monthly rankings';

    public function handle(RewardEngine $engine): int
    {
        $count = $engine->tick(max(1, min(1000, (int) $this->option('limit'))));
        $this->line((string) $count);

        return self::SUCCESS;
    }
}
