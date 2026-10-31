<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\ConnectorGrant;
use App\Models\Organization;
use App\Support\ConnectorGrantPolicy;
use App\Support\DesktopSync;
use Illuminate\Console\Command;

/**
 * Sweeps up connector approvals whose lease has run out.
 *
 * An expired approval is refused by the desktop the moment its deadline
 * passes, because the delivered status is computed from the lease. This
 * command is the durable half of that: it flips the stored row to `expired`,
 * bumps the organization's set revision, and writes an audit line, so the
 * change is recorded and a desktop that reconnects sees a newer revision
 * rather than one it can ignore. Running it twice changes nothing the second
 * time.
 */
class ConnectorsExpireGrants extends Command
{
    protected $signature = 'connectors:expire-grants';

    protected $description = 'Flip approved connector grants whose expiry has passed to expired';

    public function handle(): int
    {
        $due = ConnectorGrant::query()
            ->where('status', ConnectorGrant::STATUS_ACTIVE)
            ->whereNotNull('lease_expires_at')
            ->where('lease_expires_at', '<=', now())
            ->orderBy('organization_id')
            ->get();

        if ($due->isEmpty()) {
            $this->info(__('ui.admin.expired_connector_grants', ['count' => 0]));

            return self::SUCCESS;
        }

        $expired = 0;

        foreach ($due->groupBy('organization_id') as $organizationId => $grants) {
            $organization = Organization::query()->find($organizationId);

            if (! $organization instanceof Organization) {
                continue;
            }

            // One new revision for the whole sweep of this organization, the
            // same way an operator's save moves every grant at once.
            $revision = ConnectorGrant::bumpRevision((string) $organizationId);

            foreach ($grants as $grant) {
                $before = ConnectorGrantPolicy::summary($grant);

                $grant->status = ConnectorGrant::STATUS_EXPIRED;
                $grant->revision = $revision;
                $grant->save();

                $change = [(string) $grant->connector_slug => [
                    'from' => $before,
                    'to' => ConnectorGrantPolicy::summary($grant),
                ]];

                AuditLog::record(
                    'admin.connector_grant.expired',
                    $organization,
                    $grant,
                    ConnectorGrantPolicy::describe($change),
                    ['connector_grants' => $change],
                );

                $expired++;
            }

            DesktopSync::announce('connector_grants_updated', ['organizationId' => (string) $organizationId]);
        }

        $this->info(__('ui.admin.expired_connector_grants', ['count' => $expired]));

        return self::SUCCESS;
    }
}
