<?php

namespace App\Console\Commands;

use App\Models\GatewayRequest;
use App\Models\UsageCounter;
use Illuminate\Console\Command;

class GatewayPruneLogs extends Command
{
    protected $signature = 'gateway:prune-logs {--days= : Override the configured retention window}';

    protected $description = 'Delete gateway request logs older than the configured retention window';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('idexal.gateway.log_retention_days', 90));
        $cutoff = now()->subDays(max(1, $days));

        $deleted = GatewayRequest::query()->where('created_at', '<', $cutoff)->delete();
        UsageCounter::query()->where('period', '<', now()->subMonths(24)->format('Y-m'))->delete();

        $this->info(__('ui.admin.pruned_logs', ['count' => $deleted]));

        return self::SUCCESS;
    }
}
