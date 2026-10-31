<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GatewayService;
use App\Services\OrganizationContext;
use Illuminate\Http\JsonResponse;

class ModelsController extends Controller
{
    public function __construct(
        private readonly GatewayService $gateway,
        private readonly OrganizationContext $context,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->gateway->catalog($this->context->requireOrganization()));
    }
}
