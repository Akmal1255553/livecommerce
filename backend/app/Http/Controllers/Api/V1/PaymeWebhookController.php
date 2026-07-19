<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\PaymentWebhookProcessorInterface;
use App\DTOs\Payment\PaymentWebhookData;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payment\PaymePaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Minimal Payme Merchant JSON-RPC webhook (CheckPerformTransaction / PerformTransaction).
 */
class PaymeWebhookController extends Controller
{
    public function __construct(
        private readonly PaymePaymentGateway $payme,
        private readonly PaymentWebhookProcessorInterface $processor,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->payme->verifyBasicAuth($request->header('Authorization'))) {
            return response()->json([
                'error' => [
                    'code' => -32504,
                    'message' => 'Insufficient privilege',
                ],
            ]);
        }

        /** @var array<string, mixed> $body */
        $body = $request->all();
        $method = (string) ($body['method'] ?? '');
        $params = is_array($body['params'] ?? null) ? $body['params'] : [];
        $id = $body['id'] ?? null;

        if ($method === 'CheckPerformTransaction') {
            $orderId = (string) ($params['account']['order_id'] ?? '');
            $order = Order::query()->find($orderId);
            $amount = (int) ($params['amount'] ?? 0);
            $expected = $order !== null ? (int) $order->total * 100 : -1;

            if ($order === null || $amount !== $expected) {
                return response()->json([
                    'error' => ['code' => -31050, 'message' => 'Order not found or amount mismatch'],
                    'id' => $id,
                ]);
            }

            return response()->json(['result' => ['allow' => true], 'id' => $id]);
        }

        if ($method === 'PerformTransaction') {
            $orderId = (string) ($params['account']['order_id'] ?? '');
            $order = Order::query()->find($orderId);
            $paymeId = (string) ($params['id'] ?? '');
            $amountTiyin = (int) ($params['amount'] ?? 0);

            if ($order === null) {
                return response()->json([
                    'error' => ['code' => -31050, 'message' => 'Order not found'],
                    'id' => $id,
                ]);
            }

            try {
                $this->processor->apply(new PaymentWebhookData(
                    event: 'payment.success',
                    transactionId: $paymeId !== '' ? $paymeId : 'payme-'.$orderId,
                    orderId: $orderId,
                    amount: (int) round($amountTiyin / 100),
                    currency: (string) ($order->currency ?? 'UZS'),
                ));
            } catch (\Throwable $e) {
                Log::warning('payment.payme.webhook.rejected', ['message' => $e->getMessage()]);

                return response()->json([
                    'error' => ['code' => -31008, 'message' => $e->getMessage()],
                    'id' => $id,
                ]);
            }

            return response()->json([
                'result' => [
                    'transaction' => $paymeId,
                    'perform_time' => (int) round(microtime(true) * 1000),
                    'state' => 2,
                ],
                'id' => $id,
            ]);
        }

        return response()->json([
            'error' => ['code' => -32601, 'message' => 'Method not found'],
            'id' => $id,
        ]);
    }
}
