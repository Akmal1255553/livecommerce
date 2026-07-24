<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Pagination\CursorPaginationData;
use App\DTOs\Wallet\TopUpData;
use App\DTOs\Wallet\TopUpResult;
use App\DTOs\Wallet\WithdrawalData;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WalletWithdrawal;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

interface WalletServiceInterface
{
    public function walletFor(string $userId): Wallet;

    /**
     * @return CursorPaginationData<WalletTransaction>
     */
    public function transactions(string $userId, ?string $cursor, int $limit = 20): CursorPaginationData;

    /**
     * @return Collection<int, WalletWithdrawal>
     */
    public function withdrawals(string $userId, int $limit = 20): Collection;

    public function startTopUp(string $userId, TopUpData $data): TopUpResult;

    /**
     * Debits the wallet for an order.
     *
     * Keyed by order, so a retried checkout charges the balance once.
     *
     * @throws ValidationException when the balance is short
     */
    public function payOrder(
        string $userId,
        string $orderId,
        int $amount,
        string $currency,
    ): WalletTransaction;

    /** Returns order money to the wallet after a cancellation or refund. */
    public function refundOrder(
        string $userId,
        string $orderId,
        int $amount,
        string $currency,
    ): WalletTransaction;

    /** Settles a pending top-up; safe to call twice with the same outcome. */
    public function completeTopUp(string $userId, string $transactionId, bool $success): WalletTransaction;

    public function requestWithdrawal(string $userId, WithdrawalData $data): WalletWithdrawal;

    public function cancelWithdrawal(string $userId, string $withdrawalId): WalletWithdrawal;

    /** Provider callback: money left our account, or the payout bounced. */
    public function settleWithdrawal(string $withdrawalId, bool $success, ?string $reason = null): WalletWithdrawal;
}
