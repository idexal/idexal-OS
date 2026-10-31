<?php

namespace App\Services;

use App\Models\Membership;
use App\Models\Organization;

class OrganizationContext
{
    private ?Organization $organization = null;

    private ?Membership $membership = null;

    public function set(Organization $organization, Membership $membership): void
    {
        $this->organization = $organization;
        $this->membership = $membership;
    }

    public function organization(): ?Organization
    {
        return $this->organization;
    }

    public function membership(): ?Membership
    {
        return $this->membership;
    }

    public function organizationId(): ?string
    {
        return $this->organization?->getKey();
    }

    public function role(): ?string
    {
        return $this->membership?->role;
    }

    public function canManageMembers(): bool
    {
        return $this->membership?->canManageMembers() ?? false;
    }

    public function canManageBilling(): bool
    {
        return $this->membership?->canManageBilling() ?? false;
    }

    public function canManageApiKeys(): bool
    {
        return $this->membership?->canManageApiKeys() ?? false;
    }

    public function requireOrganization(): Organization
    {
        if ($this->organization === null) {
            abort(403, __('ui.errors.no_organization'));
        }

        return $this->organization;
    }
}
