<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaymentWebhookJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        /** @var array<string, mixed> $payload */
        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            $payload = $request->all();
        }
        $signature = $request->header('X-Signature');

        try {
            ProcessPaymentWebhookJob::dispatchSync($payload, $rawBody, $signature);
        } catch (\Throwable $e) {
            Log::warning('payment.webhook.rejected', [
                'message' => $e->getMessage(),
            ]);
        }

        // Always 200 so gateways do not infinite-retry signature/business rejects.
        return response()->json(['ok' => true]);
    }
}
