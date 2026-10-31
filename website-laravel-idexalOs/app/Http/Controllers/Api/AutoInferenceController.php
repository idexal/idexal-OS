<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\GatewayException;
use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\ApiKey;
use App\Services\FreeInferenceService;
use App\Services\GatewayService;
use App\Services\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AutoInferenceController extends Controller
{
    public function __construct(
        private readonly FreeInferenceService $free,
        private readonly GatewayService $gateway,
        private readonly OrganizationContext $context,
    ) {}

    public function credential(Request $request): JsonResponse
    {
        if (! $this->free->enabled()) {
            return response()->json([
                'error' => 'free_not_enrolled',
                'message' => 'Auto is not enabled on this platform.',
            ], 403);
        }

        $organization = $this->context->requireOrganization();
        $user = Auth::user();

        if ($user === null) {
            return response()->json(['error' => 'free_principal_rejected', 'message' => 'Sign in to use Auto.'], 401);
        }

        return response()->json($this->free->credential($organization, $user));
    }

    public function status(): JsonResponse
    {
        if (! $this->free->enabled()) {
            return response()->json([
                'state' => 'unavailable',
                'code' => 'free_disabled',
                'error' => 'free_disabled',
                'allowance' => null,
            ], 403);
        }

        $organization = $this->context->requireOrganization();
        $user = Auth::user();

        if ($user === null) {
            return response()->json(['state' => 'unavailable', 'code' => 'free_principal_rejected'], 401);
        }

        return response()->json($this->free->status($organization, $user));
    }

    public function models(): JsonResponse
    {
        $organization = $this->context->requireOrganization();
        $model = $this->free->autoModel($organization);

        return response()->json([
            'object' => 'list',
            'data' => [[
                'id' => FreeInferenceService::MODEL_ID,
                'object' => 'model',
                'created' => $model?->created_at?->timestamp ?? now()->timestamp,
                'owned_by' => FreeInferenceService::PROVIDER_ID,
                'name' => 'Auto',
                'display_name' => 'Auto',
                'context_window' => $model?->context_window,
            ]],
        ]);
    }

    public function chat(Request $request): JsonResponse|StreamedResponse|Response
    {
        $input = $request->all();

        if (! is_array($input['messages'] ?? null) || $input['messages'] === []) {
            return $this->error('messages_required', 'Messages are required.', 422, 'messages_required');
        }

        $organization = $this->context->requireOrganization();
        $key = $request->attributes->get('api_key');

        if (! $key instanceof ApiKey) {
            return $this->error('invalid_api_key', 'A valid Auto credential is required.', 401, 'invalid_api_key');
        }

        $model = $this->free->autoModel($organization);

        if (! $model instanceof AiModel) {
            return $this->error('free_member_unavailable', 'Auto has no usable model right now.', 503, 'free_member_unavailable');
        }

        // The desktop selected the stable Auto alias; the gateway receives the
        // real upstream model and still meters it under this member's key.
        $input['model'] = (string) $model->model_id;

        try {
            if ($request->boolean('stream') === true) {
                return $this->gateway->stream($organization, $key, $input, 'chat/completions', $request);
            }

            $result = $this->gateway->complete($organization, $key, $input, 'chat/completions', $request);
            $result['body']['model'] = FreeInferenceService::MODEL_ID;

            return response()
                ->json($result['body'], $result['status'])
                ->header('X-Request-Id', $result['request_id'])
                ->header('X-Idexal-Request-Id', $result['request_id']);
        } catch (GatewayException $exception) {
            return $this->error($exception->errorType, $exception->getMessage(), $exception->status, $exception->errorCode, $exception->param);
        } catch (Throwable $exception) {
            report($exception);

            return $this->error('server_error', 'Auto could not reach its upstream model.', 500, 'gateway_error');
        }
    }

    private function error(string $type, string $message, int $status, ?string $code = null, ?string $param = null): JsonResponse
    {
        return response()->json([
            'error' => array_filter([
                'message' => $message,
                'type' => $type,
                'param' => $param,
                'code' => $code,
            ], static fn ($value): bool => $value !== null),
        ], $status);
    }
}
