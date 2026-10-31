<?php

use App\Http\Controllers\Api\ApiMetaController;
use App\Http\Controllers\Api\AutoInferenceController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\CloudProviderController;
use App\Http\Controllers\Api\DesktopAuthController;
use App\Http\Controllers\Api\GatewayUsageController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\ModelsController;
use App\Http\Controllers\Api\RuntimeConfigController;
use App\Http\Controllers\Api\WorkspaceMemoryController;
use Illuminate\Support\Facades\Route;

Route::get('/api/runtime-config', RuntimeConfigController::class)->name('api.runtime-config');

Route::prefix('v1')->group(function (): void {
    Route::get('/health', [ApiMetaController::class, 'health'])->name('api.health');
    Route::get('/features', [ApiMetaController::class, 'features'])->name('api.features');
    Route::get('/app-version', [ApiMetaController::class, 'appVersion'])->name('api.app-version');

    Route::post('/auth/desktop-handoff/exchange', [DesktopAuthController::class, 'exchange'])
        ->middleware('throttle:desktop-handoff')
        ->name('api.desktop.exchange');

    // The desktop's signed-in Auto flow exchanges its desktop key for a
    // short-lived free-inference credential, then calls the Auto surface.
    Route::post('/inference/free/credential', [AutoInferenceController::class, 'credential'])
        ->middleware(['api.key', 'api.ability:desktop'])
        ->name('api.auto.credential');

    Route::middleware(['api.key', 'throttle:gateway'])->group(function (): void {
        Route::get('/me', [MeController::class, 'show'])->name('api.me');
        Route::get('/me/orgs', [MeController::class, 'orgs'])->name('api.me.orgs');
        Route::post('/me/active-organization', [MeController::class, 'activeOrganization'])->name('api.me.active-organization');
        Route::get('/me/desktop-config', [MeController::class, 'desktopConfig'])->name('api.me.desktop-config');
        Route::post('/me/connector-requests', [MeController::class, 'storeConnectorRequest'])
            ->name('api.me.connector-requests');
        Route::get('/workspace-memory', [WorkspaceMemoryController::class, 'show'])->name('api.workspace-memory.show');
        Route::put('/workspace-memory', [WorkspaceMemoryController::class, 'update'])->name('api.workspace-memory.update');
        Route::get('/org', [MeController::class, 'organization'])->name('api.org');
        Route::get('/resources', [MeController::class, 'resources'])->name('api.resources');
        Route::get('/me/library', [MeController::class, 'library'])->name('api.me.library');
        Route::get('/resources/marketplace-capabilities', [MeController::class, 'marketplaceCapabilities'])
            ->name('api.resources.marketplace-capabilities');

        // Hosted marketplace: one marketplace, one-click installables, and an
        // inventory revision the desktop polls to react to admin changes.
        Route::get('/marketplaces', [MeController::class, 'marketplaces'])->name('api.marketplaces');
        Route::get('/marketplaces/{marketplaceId}/resolved', [MeController::class, 'marketplaceResolved'])
            ->name('api.marketplaces.resolved');
        Route::get('/resolved-plugins', [MeController::class, 'resolvedPlugins'])->name('api.resolved-plugins');
        Route::get('/plugins', [MeController::class, 'plugins'])->name('api.plugins');
        Route::get('/plugins/{pluginId}/access', [MeController::class, 'pluginAccess'])->name('api.plugins.access');
        Route::post('/plugins/{pluginId}/access', [MeController::class, 'grantPluginAccess'])->name('api.plugins.access.grant');
        Route::delete('/plugins/{pluginId}/access/{grantId}', [MeController::class, 'revokePluginAccess'])->name('api.plugins.access.revoke');
        Route::get('/plugins/{pluginId}/resolved', [MeController::class, 'pluginResolved'])
            ->name('api.plugins.resolved');
        Route::get('/org/inventory-revision', [MeController::class, 'inventoryRevision'])
            ->name('api.org.inventory-revision');

        Route::get('/mcp-connections', [MeController::class, 'mcpConnections'])->name('api.mcp-connections');
        Route::get('/mcp-connections/presets', [MeController::class, 'mcpConnectionPresets'])->name('api.mcp-connections.presets');

        Route::get('/models', [ModelsController::class, 'index'])
            ->middleware('api.ability:gateway:models')
            ->name('api.models');

        Route::get('/llm-providers', [CloudProviderController::class, 'llmProviders'])
            ->middleware('api.ability:gateway:models')
            ->name('api.llm-providers');

        Route::get('/llm-providers/{provider}/connect', [CloudProviderController::class, 'connectLlmProvider'])
            ->middleware('api.ability:gateway:models')
            ->name('api.llm-providers.connect');

        Route::get('/inference-providers', [CloudProviderController::class, 'inferenceProviders'])
            ->middleware('api.ability:gateway:models')
            ->name('api.inference-providers');

        Route::get('/inference-providers/inventory/events', [CloudProviderController::class, 'inventoryEvents'])
            ->middleware('api.ability:gateway:models')
            ->name('api.inference-providers.inventory-events');

        Route::get('/inference-providers/{provider}/connect', [CloudProviderController::class, 'connectInferenceProvider'])
            ->middleware('api.ability:gateway:models')
            ->name('api.inference-providers.connect');

        Route::post('/chat/completions', [ChatController::class, 'completions'])
            ->middleware('api.ability:gateway:chat')
            ->name('api.chat.completions');

        Route::post('/messages', [ChatController::class, 'messages'])
            ->middleware('api.ability:gateway:chat')
            ->name('api.messages');

        Route::get('/gateway/usage-limits/me', [GatewayUsageController::class, 'show'])
            ->name('api.gateway.usage-limits.me');

        Route::post('/gateway/usage-limit-reset-requests', [GatewayUsageController::class, 'storeResetRequest'])
            ->name('api.gateway.usage-limit-reset-requests');
    });
});

// Den-compatible Auto surface used by the desktop's free-inference relay.
// The credential is an ow_inf_ key; these routes are the only extra prefix
// the desktop expects from a hosted control plane.
Route::prefix('api/v1')->group(function (): void {
    Route::middleware(['api.key', 'throttle:gateway'])->group(function (): void {
        Route::get('/auto/status', [AutoInferenceController::class, 'status'])
            ->middleware('api.ability:gateway:models')
            ->name('api.auto.status');

        Route::get('/models', [AutoInferenceController::class, 'models'])
            ->middleware('api.ability:gateway:models')
            ->name('api.auto.models');

        Route::post('/chat/completions', [AutoInferenceController::class, 'chat'])
            ->middleware('api.ability:gateway:chat')
            ->name('api.auto.chat');
    });
});
