<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\PaymentWebhookProcessorInterface;
use App\DTOs\Payment\PaymentWebhookData;
use App\Http\Controllers\Controller;
use App\Services\Payment\BitcoinPaymentGateway;
use App\Services\Payment\PaymentSubjectLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class BitcoinWebhookController extends Controller
{
    public function __construct(
        private readonly BitcoinPaymentGateway $bitcoin,
        private readonly PaymentWebhookProcessorInterface $processor,
        private readonly PaymentSubjectLookup $subjects,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $signature = $request->header('x-nowpayments-sig')
            ?? $request->header('X-Signature');

        try {
            if (! $this->bitcoin->verifyWebhookSignature($rawBody, $signature)) {
                throw new AccessDeniedHttpException('Invalid bitcoin webhook signature.');
            }

            /** @var array<string, mixed> $payload */
            $payload = json_decode($rawBody, true);
            if (! is_array($payload)) {
                $payload = $request->all();
            }

            $this->processor->apply($this->mapPayload($payload));
        } catch (\Throwable $e) {
            Log::warning('payment.bitcoin.webhook.rejected', [
                'message' => $e->getMessage(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mapPayload(array $payload): PaymentWebhookData
    {
        $status = strtolower((string) ($payload['payment_status'] ?? $payload['status'] ?? ''));
        $success = in_array($status, ['finished', 'confirmed', 'sending', 'payment.success', 'success'], true);

        $orderId = (string) ($payload['order_id'] ?? $payload['orderId'] ?? '');
        $transactionId = (string) ($payload['payment_id'] ?? $payload['transaction_id'] ?? '');

        // Invoice fiat may be USD while our ledger is UZS — prefer the subject we created.
        $subject = $orderId !== '' ? $this->subjects->find($orderId) : null;
        $amount = $subject?->amount
            ?? (int) round((float) ($payload['price_amount'] ?? $payload['amount'] ?? 0));
        $currency = $subject?->currency
            ?? strtoupper((string) ($payload['price_currency'] ?? $payload['currency'] ?? 'UZS'));

        return new PaymentWebhookData(
            event: $success ? 'payment.success' : 'payment.failed',
            transactionId: $transactionId,
            orderId: $orderId,
            amount: $amount,
            currency: $currency,
        );
    }
}
