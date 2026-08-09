<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\PaymentWebhookProcessorInterface;
use App\DTOs\Payment\PaymentWebhookData;
use App\Http\Controllers\Controller;
use App\Services\Payment\ClickPaymentGateway;
use App\Services\Payment\PaymentSubjectLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Click Shop API prepare (action=0) + complete (action=1).
 */
class ClickWebhookController extends Controller
{
    public function __construct(
        private readonly ClickPaymentGateway $click,
        private readonly PaymentWebhookProcessorInterface $processor,
        private readonly PaymentSubjectLookup $subjects,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->all();
        $action = (int) ($payload['action'] ?? -1);

        if (! $this->click->verifyClickSign($payload)) {
            return response()->json([
                'error' => -1,
                'error_note' => 'Invalid sign_string',
            ]);
        }

        $reference = (string) ($payload['merchant_trans_id'] ?? '');
        $subject = $this->subjects->find($reference);

        if ($subject === null) {
            return response()->json([
                'error' => -5,
                'error_note' => 'Order not found',
                'click_trans_id' => $payload['click_trans_id'] ?? null,
                'merchant_trans_id' => $reference,
            ]);
        }

        $amount = (int) round((float) ($payload['amount'] ?? 0));
        if ($amount !== $subject->amount) {
            return response()->json([
                'error' => -2,
                'error_note' => 'Incorrect amount',
                'click_trans_id' => $payload['click_trans_id'] ?? null,
                'merchant_trans_id' => $reference,
            ]);
        }

        if ($action === 0) {
            return response()->json([
                'click_trans_id' => $payload['click_trans_id'] ?? null,
                'merchant_trans_id' => $reference,
                'merchant_prepare_id' => crc32($reference) & 0x7FFFFFFF,
                'error' => 0,
                'error_note' => 'Success',
            ]);
        }

        if ($action === 1) {
            $error = (int) ($payload['error'] ?? 0);
            $clickTransId = (string) ($payload['click_trans_id'] ?? '');

            try {
                $this->processor->apply(new PaymentWebhookData(
                    event: $error === 0 ? 'payment.success' : 'payment.failed',
                    transactionId: $clickTransId !== '' ? $clickTransId : 'click-'.$reference,
                    orderId: $reference,
                    amount: $amount,
                    currency: $subject->currency,
                ));
            } catch (\Throwable $e) {
                Log::warning('payment.click.webhook.rejected', ['message' => $e->getMessage()]);

                return response()->json([
                    'error' => -9,
                    'error_note' => $e->getMessage(),
                    'click_trans_id' => $payload['click_trans_id'] ?? null,
                    'merchant_trans_id' => $reference,
                ]);
            }

            return response()->json([
                'click_trans_id' => $payload['click_trans_id'] ?? null,
                'merchant_trans_id' => $reference,
                'merchant_prepare_id' => $payload['merchant_prepare_id'] ?? (crc32($reference) & 0x7FFFFFFF),
                'merchant_confirm_id' => crc32($reference.'confirm') & 0x7FFFFFFF,
                'error' => 0,
                'error_note' => 'Success',
            ]);
        }

        return response()->json([
            'error' => -3,
            'error_note' => 'Action not found',
        ]);
    }
}
