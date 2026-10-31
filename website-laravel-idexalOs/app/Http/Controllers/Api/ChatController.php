<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\GatewayException;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Services\GatewayService;
use App\Services\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ChatController extends Controller
{
    public function __construct(
        private readonly GatewayService $gateway,
        private readonly OrganizationContext $context,
    ) {}

    public function completions(Request $request): JsonResponse|StreamedResponse|Response
    {
        return $this->handle($request, 'chat/completions');
    }

    public function messages(Request $request): JsonResponse|StreamedResponse|Response
    {
        return $this->handle($request, 'messages');
    }

    private function handle(Request $request, string $endpoint): JsonResponse|StreamedResponse|Response
    {
        $input = $request->all();

        if (! is_array($input['messages'] ?? null) || $input['messages'] === []) {
            return $this->error($endpoint, 'invalid_request_error', __('ui.errors.messages_required'), 422, 'messages_required', 'messages');
        }

        $organization = $this->context->requireOrganization();
        $membership = $this->context->membership();
        $key = $request->attributes->get('api_key');

        if (! $key instanceof ApiKey) {
            return $this->error($endpoint, 'authentication_error', __('ui.errors.missing_api_key'), 401, 'invalid_api_key');
        }

        try {
            if ($request->boolean('stream') === true) {
                return $this->gateway->stream($organization, $key, $input, $endpoint, $request);
            }

            $result = $this->gateway->complete($organization, $key, $input, $endpoint, $request);

            $response = response()
                ->json($result['body'], $result['status'])
                ->header('X-Request-Id', $result['request_id'])
                ->header('X-Idexal-Request-Id', $result['request_id']);

            foreach ($result['headers'] ?? [] as $name => $value) {
                $response->header($name, $value);
            }

            return $response;
        } catch (GatewayException $exception) {
            return $this->error(
                $endpoint,
                $exception->errorType,
                $exception->getMessage(),
                $exception->status,
                $exception->errorCode,
                $exception->param,
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->error(
                $endpoint,
                'server_error',
                __('ui.errors.gateway_upstream'),
                500,
                'gateway_error',
            );
        }
    }

    private function error(
        string $endpoint,
        string $type,
        string $message,
        int $status,
        ?string $code = null,
        ?string $parameter = null,
    ): JsonResponse {
        if ($endpoint === 'messages') {
            return response()->json([
                'type' => 'error',
                'error' => [
                    'type' => $type,
                    'message' => $message,
                ],
            ], $status);
        }

        return response()->json([
            'error' => array_filter([
                'message' => $message,
                'type' => $type,
                'param' => $parameter,
                'code' => $code,
            ], static fn ($value): bool => $value !== null),
        ], $status);
    }
}
