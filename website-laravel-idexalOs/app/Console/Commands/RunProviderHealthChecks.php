<?php

namespace App\Console\Commands;

use App\Services\ProviderHealthMonitorService;
use Illuminate\Console\Command;

class RunProviderHealthChecks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'providers:health-checks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Probe every active provider once and record its health';

    public function __construct(
        private readonly ProviderHealthMonitorService $healthMonitor,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $results = $this->healthMonitor->checkAllProviders();

        if ($results->isEmpty()) {
            $this->components->info('No active providers to check.');

            return self::SUCCESS;
        }

        $this->table(
            ['Provider', 'Status', 'Latency', 'Notes'],
            $results->map(fn (array $result): array => [
                substr($result['provider_id'], 0, 12),
                $result['status'],
                $result['response_time_ms'] !== null ? $result['response_time_ms'].' ms' : '—',
                $result['error_message'] ?? '',
            ])->all(),
        );

        $unhealthy = $results->where('status', '!=', 'healthy')->count();
        $this->components->info(sprintf('Checked %d providers; %d need attention.', $results->count(), $unhealthy));

        return self::SUCCESS;
    }
}
