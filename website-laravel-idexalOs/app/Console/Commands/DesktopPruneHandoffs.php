<?php

namespace App\Console\Commands;

use App\Models\DesktopHandoff;
use App\Models\DesktopSession;
use Illuminate\Console\Command;

class DesktopPruneHandoffs extends Command
{
    protected $signature = 'desktop:prune-handoffs';

    protected $description = 'Delete expired desktop handoffs and revoked desktop sessions';

    public function handle(): int
    {
        $handoffs = DesktopHandoff::query()
            ->where('expires_at', '<', now()->subDay())
            ->orWhere(fn ($query) => $query
                ->whereNotNull('consumed_at')
                ->where('consumed_at', '<', now()->subDays(7)))
            ->delete();

        $sessions = DesktopSession::query()
            ->whereNotNull('revoked_at')
            ->where('revoked_at', '<', now()->subDays(90))
            ->delete();

        $this->info(__('ui.admin.pruned_desktop', ['handoffs' => $handoffs, 'sessions' => $sessions]));

        return self::SUCCESS;
    }
}
