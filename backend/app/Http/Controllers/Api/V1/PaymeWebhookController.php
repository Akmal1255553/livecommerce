<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PaymeRpcException;
use App\Http\Controllers\Controller;
use App\Services\Payment\PaymeMerchantService;
use App\Services\Payment\PaymePaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Payme Merchant API endpoint. The protocol answers HTTP 200 for everything, including
 * failures, which travel as a JSON-RPC error object.
 */
class PaymeWebhookController extends Controller
{
    public function __construct(
        private readonly PaymePaymentGateway $payme,
        private readonly PaymeMerchantService $merchant,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $body */
        $body = $request->all();
        $id = $body['id'] ?? null;

        if (! $this->payme->verifyBasicAuth($request->header('Authorization'))) {
            return $this->error(PaymeRpcException::insufficientPrivilege(), $id);
        }

        $method = (string) ($body['method'] ?? '');
        $params = is_array($body['params'] ?? null) ? $body['params'] : [];

        try {
            $result = $this->merchant->handle($method, $params);
        } catch (PaymeRpcException $e) {
            return $this->error($e, $id);
        }

        return response()->json(['result' => $result, 'id' => $id]);
    }

    private function error(PaymeRpcException $e, mixed $id): JsonResponse
    {
        return response()->json(['error' => $e->toRpcError(), 'id' => $id]);
    }
}
