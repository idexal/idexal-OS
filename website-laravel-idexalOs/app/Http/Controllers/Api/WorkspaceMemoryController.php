<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkspaceMemory;
use App\Services\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceMemoryController extends Controller
{
    public function __construct(private readonly OrganizationContext $context) {}

    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workspaceId' => ['required', 'string', 'max:190'],
        ]);
        $organization = $this->context->requireOrganization();
        $memory = WorkspaceMemory::query()
            ->where('organization_id', $organization->getKey())
            ->where('workspace_id', $validated['workspaceId'])
            ->first();

        return $this->payload($validated['workspaceId'], $memory);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workspaceId' => ['required', 'string', 'max:190'],
            'content' => ['present', 'string', 'max:524288'],
        ]);
        $organization = $this->context->requireOrganization();
        $content = $validated['content'];
        $memory = WorkspaceMemory::query()->updateOrCreate(
            [
                'organization_id' => $organization->getKey(),
                'workspace_id' => $validated['workspaceId'],
            ],
            [
                'content' => $content,
                'checksum' => hash('sha256', $content),
            ],
        );

        return $this->payload($validated['workspaceId'], $memory);
    }

    private function payload(string $workspaceId, ?WorkspaceMemory $memory): JsonResponse
    {
        return response()->json([
            'workspaceId' => $workspaceId,
            'content' => $memory?->content,
            'checksum' => $memory?->checksum,
            'updatedAt' => $memory?->updated_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
        ]);
    }
}
