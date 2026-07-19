<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\PaymentWebhookProcessorInterface;
use App\DTOs\Payment\PaymentWebhookData;
use App\Http\Controllers\Controller;
use App\Services\Payment\UzumPaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Uzum HMAC webhook — expects normalized JSON {event, transaction_id, order_id, amount, currency}.
 */
class UzumWebhookController extends Controller
{
    public function __construct(
        private readonly UzumPaymentGateway $uzum,
        private readonly PaymentWebhookProcessorInterface $processor,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $raw = $request->getContent();
        $signature = $request->header('X-Signature') ?? $request->header('X-Uzum-Signature');

        if (! $this->uzum->verifyWebhookSignature($raw, $signature)) {
            return response()->json(['ok' => false, 'error' => 'invalid_signature'], 403);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            $payload = $request->all();
        }

        try {
            $this->processor->apply(PaymentWebhookData::fromPayload($payload));
        } catch (\Throwable $e) {
            Log::warning('payment.uzum.webhook.rejected', ['message' => $e->getMessage()]);
        }

        return response()->json(['ok' => true]);
    }
}
