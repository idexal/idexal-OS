<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class RuntimeConfigController extends Controller
{
    /**
     * Desktop clients probe this endpoint before exchanging a handoff grant.
     *
     * Returning the same origin as the API base tells the desktop app to call
     * `/v1/*` here instead of the hosted `api.os.idexal.com` convention.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'denApiUrl' => url('/'),
        ]);
    }
}
