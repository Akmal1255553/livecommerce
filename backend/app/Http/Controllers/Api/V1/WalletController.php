<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\WalletServiceInterface;
use App\DTOs\Wallet\TopUpData;
use App\DTOs\Wallet\WithdrawalData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\TopUpRequest;
use App\Http\Requests\Wallet\WithdrawalRequest;
use App\Http\Resources\WalletResource;
use App\Http\Resources\WalletTransactionResource;
use App\Http\Resources\WalletWithdrawalResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(
        private readonly WalletServiceInterface $wallet,
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
            ),
        );

        return ApiResponse::created([
            'transaction' => new WalletTransactionResource($result->transaction),
            'payment_url' => $result->paymentUrl,
        ]);
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
