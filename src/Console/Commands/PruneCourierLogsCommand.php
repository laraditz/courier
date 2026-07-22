<?php

namespace Laraditz\Courier\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Laraditz\Courier\Models\CourierApiLog;

class PruneCourierLogsCommand extends Command
{
    protected $signature = 'courier:prune-logs';

    protected $description = 'Prune courier API/webhook logs older than the configured retention period';

    public function handle(): int
    {
        $retentionDays = config('courier.logging.retention_days');

        if ($retentionDays === null) {
            $this->info('Retention is disabled (courier.logging.retention_days is null) — nothing pruned.');

            return self::SUCCESS;
        }

        $cutoff = Carbon::now()->subDays($retentionDays);

        CourierApiLog::where('created_at', '<', $cutoff)->chunkById(500, function ($logs) {
            $logs->each->delete();
        });

        return self::SUCCESS;
    }
}
