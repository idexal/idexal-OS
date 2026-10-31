<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Organization;
use App\Models\User;

class NotificationService
{
    public function send(
        User $user,
        string $type,
        string $title,
        ?string $body = null,
        ?string $url = null,
        ?Organization $organization = null,
    ): Notification {
        return Notification::query()->create([
            'user_id' => $user->getKey(),
            'organization_id' => $organization?->getKey(),
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'created_at' => now(),
        ]);
    }

    public function notifyManagers(
        Organization $organization,
        string $type,
        string $title,
        ?string $body = null,
        ?string $url = null,
    ): void {
        $managers = $organization->memberships()
            ->with('user')
            ->where('status', 'active')
            ->whereIn('role', ['owner', 'admin', 'billing'])
            ->get();

        foreach ($managers as $membership) {
            if ($membership->user instanceof User) {
                $this->send($membership->user, $type, $title, $body, $url, $organization);
            }
        }
    }
}
