<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\WalletServiceInterface;
use App\DTOs\Wallet\TopUpData;
use App\DTOs\Wallet\WithdrawalData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\StorePaymentCardRequest;
use App\Http\Requests\Wallet\TopUpRequest;
use App\Http\Requests\Wallet\WithdrawalRequest;
use App\Http\Resources\UserPaymentCardResource;
use App\Http\Resources\WalletResource;
use App\Http\Resources\WalletTransactionResource;
use App\Http\Resources\WalletWithdrawalResource;
use App\Http\Responses\ApiResponse;
use App\Services\Payment\BitcoinPaymentGateway;
use App\Services\Wallet\PaymentCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(
        private readonly WalletServiceInterface $wallet,
        private readonly PaymentCardService $cards,
        private readonly BitcoinPaymentGateway $bitcoin,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success(
            new WalletResource($this->wallet->walletFor($request->user()->id)),
        );
    }

    public function transactions(Request $request): JsonResponse
    {
        $limit = min(50, max(1, (int) $request->query('limit', 20)));
        $page = $this->wallet->transactions(
            $request->user()->id,
            $request->query('cursor'),
            $limit,
        );

        return ApiResponse::cursorPaginated(
            WalletTransactionResource::collection($page->items),
            $page,
        );
    }

    public function topUp(TopUpRequest $request): JsonResponse
    {
        $result = $this->wallet->startTopUp(
            $request->user()->id,
            new TopUpData(
                amount: (int) $request->integer('amount'),
                method: $request->string('method')->toString(),
                reference: $request->input('reference'),
                paymentMethodId: $request->input('payment_method_id'),
            ),
        );

        return ApiResponse::created([
            'transaction' => new WalletTransactionResource($result->transaction),
            'payment_url' => $result->paymentUrl,
            'crypto_address' => $result->cryptoAddress,
            'crypto_amount' => $result->cryptoAmount,
            'crypto_currency' => $result->cryptoCurrency,
            'exchange_rate' => $result->exchangeRate,
            'expires_at' => $result->expiresAt,
            'qr_payload' => $result->qrPayload,
        ]);
    }

    public function bitcoinQuote(Request $request): JsonResponse
    {
        $amount = (int) $request->query('amount', 0);

        if ($amount < 1) {
            return ApiResponse::success([
                'amount' => 0,
                'currency' => 'UZS',
                'crypto_amount' => '0',
                'crypto_currency' => 'BTC',
                'exchange_rate' => 0.0,
                'min_amount' => $this->bitcoin->minAmountUzs(),
                'meets_minimum' => false,
            ]);
        }

        return ApiResponse::success($this->bitcoin->quote($amount));
    }

    public function cards(Request $request): JsonResponse
    {
        return ApiResponse::success(
            UserPaymentCardResource::collection(
                $this->cards->listFor($request->user()->id),
            ),
        );
    }

    public function storeCard(StorePaymentCardRequest $request): JsonResponse
    {
        $card = $this->cards->store(
            $request->user()->id,
            $request->string('card_number')->toString(),
            $request->string('holder_name')->toString(),
            (int) $request->integer('exp_month'),
            (int) $request->integer('exp_year'),
            $request->boolean('is_default', false),
        );

        return ApiResponse::created(new UserPaymentCardResource($card));
    }

    public function destroyCard(Request $request, string $id): JsonResponse
    {
        $this->cards->delete($request->user()->id, $id);

        return ApiResponse::success(['deleted' => true]);
    }

    /** Sandbox confirmation stands in for a provider callback until one exists. */
    public function confirmTopUp(Request $request, string $id): JsonResponse
    {
        abort_unless((bool) config('wallet.sandbox_enabled'), 404);

        $transaction = $this->wallet->completeTopUp(
            $request->user()->id,
            $id,
            $request->boolean('success', true),
        );

        return ApiResponse::success(new WalletTransactionResource($transaction));
    }

    public function withdrawals(Request $request): JsonResponse
    {
        return ApiResponse::success(
            WalletWithdrawalResource::collection(
                $this->wallet->withdrawals($request->user()->id),
            ),
        );
    }

    public function requestWithdrawal(WithdrawalRequest $request): JsonResponse
    {
        $withdrawal = $this->wallet->requestWithdrawal(
            $request->user()->id,
            new WithdrawalData(
                amount: (int) $request->integer('amount'),
                method: $request->string('method')->toString(),
                cardNumber: $request->string('card_number')->toString(),
                cardHolder: $request->string('card_holder')->toString(),
            ),
        );

        return ApiResponse::created(new WalletWithdrawalResource($withdrawal));
    }

    public function cancelWithdrawal(Request $request, string $id): JsonResponse
    {
        return ApiResponse::success(
            new WalletWithdrawalResource(
                $this->wallet->cancelWithdrawal($request->user()->id, $id),
            ),
        );
    }
}
