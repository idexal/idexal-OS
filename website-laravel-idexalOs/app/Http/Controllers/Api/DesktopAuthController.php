<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DesktopHandoffException;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Services\DesktopAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class DesktopAuthController extends Controller
{
    public function __construct(private readonly DesktopAuthService $desktopAuth) {}

    public function exchange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'grant' => ['required', 'string', 'max:128'],
        ]);

        try {
            $result = $this->desktopAuth->exchange($validated['grant'], $request);
        } catch (DesktopHandoffException $exception) {
            return response()->json([
                'error' => 'invalid_grant',
                'message' => $exception->getMessage(),
            ], $exception->status());
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'error' => 'handoff_failed',
                'message' => __('ui.errors.grant_invalid'),
            ], 500);
        }

        $user = $result['user'];
        $organization = $result['organization'];

        if (! $user instanceof User || ! $organization instanceof Organization) {
            return response()->json([
                'error' => 'handoff_failed',
                'message' => __('ui.errors.grant_invalid'),
            ], 500);
        }

        return response()->json([
            'user' => [
                'id' => $user->getKey(),
                'email' => (string) $user->email,
                'name' => $user->name,
            ],
            'token' => $result['token'],
            'organization' => [
                'id' => $organization->getKey(),
                'slug' => $organization->slug,
                'name' => $organization->name,
            ],
            'connectEnabled' => false,
        ]);
    }
}
