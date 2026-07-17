<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\PaymentWebhookProcessorInterface;
use App\DTOs\Payment\PaymentWebhookData;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Mobile / WebView sandbox: buyer completes or fails payment without a real gateway.
 */
class SandboxPaymentController extends Controller
{
    public function __construct(
        private readonly PaymentWebhookProcessorInterface $webhooks,
    ) {}

    public function complete(Request $request, string $id): JsonResponse
    {
        if (! config('payment.sandbox_enabled')) {
            abort(404);
        }

        $validated = $request->validate([
            'result' => ['required', 'in:success,failed'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $order = Order::query()->whereKey($id)->where('user_id', $user->id)->first();

        if ($order === null) {
            abort(404, 'Order not found.');
        }

        if ($order->status !== OrderStatus::AwaitingPayment) {
            throw ValidationException::withMessages([
                'order' => ['Order is not awaiting payment.'],
            ]);
        }

        $transactionId = $order->payment_transaction_id
            ?? $order->payment_reference
            ?? 'sandbox-'.$order->id;

        $event = $validated['result'] === 'success' ? 'payment.success' : 'payment.failed';

        $this->webhooks->apply(new PaymentWebhookData(
            event: $event,
            transactionId: (string) $transactionId,
            orderId: $order->id,
            amount: (int) $order->total,
            currency: (string) ($order->currency ?? 'UZS'),
        ));

        $order = $order->fresh(['items.product', 'transitions', 'store', 'activeRefund']);

        return ApiResponse::success([
            'order' => new \App\Http\Resources\OrderResource($order),
        ]);
    }
}
