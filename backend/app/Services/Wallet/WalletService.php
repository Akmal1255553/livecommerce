<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Contracts\Services\WalletServiceInterface;
use App\DTOs\Pagination\CursorPaginationData;
use App\DTOs\Wallet\TopUpData;
use App\DTOs\Wallet\TopUpResult;
use App\DTOs\Wallet\WithdrawalData;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalStatus;
use App\Exceptions\Domain\ConflictException;
use App\Exceptions\Domain\ResourceNotFoundException;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WalletWithdrawal;
use App\Services\BaseService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Single writer for wallet money.
 *
 * Every balance change runs inside a transaction with the wallet row locked, so
 * concurrent top-ups, withdrawals and refunds cannot interleave into a negative
 * or double-spent balance.
 */
class WalletService extends BaseService implements WalletServiceInterface
{
    public function walletFor(string $userId): Wallet
    {
        $wallet = Wallet::query()->where('user_id', $userId)->first();

        if ($wallet !== null) {
            return $wallet;
        }

        return Wallet::query()->create([
            'user_id' => $userId,
            'available_balance' => 0,
            'held_balance' => 0,
            'currency' => (string) config('wallet.currency', 'UZS'),
        ]);
    }

    public function transactions(string $userId, ?string $cursor, int $limit = 20): CursorPaginationData
    {
        $wallet = $this->walletFor($userId);

        $query = WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $decoded = CursorPaginationData::decodeVideoCursor($cursor);

        if ($decoded !== null) {
            $query->where(function ($builder) use ($decoded): void {
                $builder
                    ->where('created_at', '<', $decoded['created_at'])
                    ->orWhere(function ($inner) use ($decoded): void {
                        $inner
                            ->where('created_at', '=', $decoded['created_at'])
                            ->where('id', '<', $decoded['id']);
                    });
            });
        }

        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;

        if ($hasMore) {
            $items = $items->take($limit);
        }

        /** @var WalletTransaction|null $last */
        $last = $items->last();

        return new CursorPaginationData(
            items: $items->values(),
            nextCursor: $hasMore && $last !== null
                ? CursorPaginationData::encodeVideoCursor(
                    $last->id,
                    $last->created_at?->toIso8601String() ?? now()->toIso8601String(),
                )
                : null,
            hasMore: $hasMore,
            limit: $limit,
        );
    }

    /**
     * @return Collection<int, WalletWithdrawal>
     */
    public function withdrawals(string $userId, int $limit = 20): Collection
    {
        $wallet = $this->walletFor($userId);

        return WalletWithdrawal::query()
            ->where('wallet_id', $wallet->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function startTopUp(string $userId, TopUpData $data): TopUpResult
    {
        $min = (int) config('wallet.top_up.min');
        $max = (int) config('wallet.top_up.max');

        if ($data->amount < $min || $data->amount > $max) {
            throw ValidationException::withMessages([
                'amount' => ["Top-up amount must be between {$min} and {$max}."],
            ]);
        }

        $wallet = $this->walletFor($userId);
        $reference = $data->reference ?? 'topup-'.Str::uuid()->toString();

        $existing = WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('reference', $reference)
            ->first();

        // Retried request with the same reference returns the original intent.
        $transaction = $existing ?? WalletTransaction::query()->create([
            'wallet_id' => $wallet->id,
            'type' => WalletTransactionType::TopUp,
            'status' => WalletTransactionStatus::Pending,
            'amount' => $data->amount,
            'currency' => $wallet->currency,
            'method' => $data->method,
            'reference' => $reference,
            'description' => 'Wallet top-up',
        ]);

        return new TopUpResult($transaction, $this->topUpPaymentUrl($transaction));
    }

    public function completeTopUp(string $userId, string $transactionId, bool $success): WalletTransaction
    {
        $wallet = $this->walletFor($userId);

        return DB::transaction(function () use ($wallet, $transactionId, $success): WalletTransaction {
            $locked = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);

            $transaction = WalletTransaction::query()
                ->where('wallet_id', $locked->id)
                ->where('type', WalletTransactionType::TopUp)
                ->lockForUpdate()
                ->find($transactionId);

            if ($transaction === null) {
                throw new ResourceNotFoundException('Top-up not found.');
            }

            if ($transaction->status->isFinal()) {
                return $transaction;
            }

            if (! $success) {
                $transaction->status = WalletTransactionStatus::Failed;
                $transaction->completed_at = now();
                $transaction->save();

                return $transaction;
            }

            $locked->available_balance += $transaction->amount;
            $locked->save();

            $transaction->status = WalletTransactionStatus::Completed;
            $transaction->balance_after = $locked->available_balance;
            $transaction->completed_at = now();
            $transaction->save();

            $this->logger->info('wallet.topup.completed', [
                'wallet_id' => $locked->id,
                'transaction_id' => $transaction->id,
                'amount' => $transaction->amount,
            ]);

            return $transaction;
        });
    }

    public function payOrder(
        string $userId,
        string $orderId,
        int $amount,
        string $currency,
    ): WalletTransaction {
        $wallet = $this->walletFor($userId);

        return DB::transaction(function () use ($wallet, $orderId, $amount, $currency): WalletTransaction {
            $locked = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);
            $reference = 'order-'.$orderId;

            $existing = WalletTransaction::query()
                ->where('wallet_id', $locked->id)
                ->where('reference', $reference)
                ->where('type', WalletTransactionType::OrderPayment)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            if ($locked->currency !== $currency) {
                throw ValidationException::withMessages([
                    'payment_method' => ['Wallet currency does not match the order.'],
                ]);
            }

            if ($locked->available_balance < $amount) {
                throw ValidationException::withMessages([
                    'payment_method' => ['Insufficient wallet balance.'],
                ]);
            }

            $locked->available_balance -= $amount;
            $locked->save();

            return WalletTransaction::query()->create([
                'wallet_id' => $locked->id,
                'type' => WalletTransactionType::OrderPayment,
                'status' => WalletTransactionStatus::Completed,
                'amount' => -$amount,
                'balance_after' => $locked->available_balance,
                'currency' => $locked->currency,
                'method' => 'wallet',
                'reference' => $reference,
                'related_id' => $orderId,
                'description' => 'Order payment',
                'completed_at' => now(),
            ]);
        });
    }

    public function refundOrder(
        string $userId,
        string $orderId,
        int $amount,
        string $currency,
    ): WalletTransaction {
        $wallet = $this->walletFor($userId);

        return DB::transaction(function () use ($wallet, $orderId, $amount, $currency): WalletTransaction {
            $locked = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);
            $reference = 'refund-'.$orderId;

            $existing = WalletTransaction::query()
                ->where('wallet_id', $locked->id)
                ->where('reference', $reference)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $locked->available_balance += $amount;
            $locked->save();

            return WalletTransaction::query()->create([
                'wallet_id' => $locked->id,
                'type' => WalletTransactionType::Refund,
                'status' => WalletTransactionStatus::Completed,
                'amount' => $amount,
                'balance_after' => $locked->available_balance,
                'currency' => $currency,
                'method' => 'wallet',
                'reference' => $reference,
                'related_id' => $orderId,
                'description' => 'Order refund',
                'completed_at' => now(),
            ]);
        });
    }

    public function requestWithdrawal(string $userId, WithdrawalData $data): WalletWithdrawal
    {
        $min = (int) config('wallet.withdrawal.min');
        $max = (int) config('wallet.withdrawal.max');

        if ($data->amount < $min || $data->amount > $max) {
            throw ValidationException::withMessages([
                'amount' => ["Withdrawal amount must be between {$min} and {$max}."],
            ]);
        }

        $wallet = $this->walletFor($userId);
        $fee = $this->withdrawalFee($data->amount);

        return DB::transaction(function () use ($wallet, $data, $fee): WalletWithdrawal {
            $locked = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);

            $pending = WalletWithdrawal::query()
                ->where('wallet_id', $locked->id)
                ->whereIn('status', [WithdrawalStatus::Requested, WithdrawalStatus::Processing])
                ->count();

            if ($pending >= (int) config('wallet.withdrawal.max_pending', 3)) {
                throw new ConflictException('Too many withdrawals are already in progress.');
            }

            $total = $data->amount + $fee;

            if ($locked->available_balance < $total) {
                throw ValidationException::withMessages([
                    'amount' => ['Insufficient wallet balance.'],
                ]);
            }

            // Hold first: the money is neither spendable nor paid out yet.
            $locked->available_balance -= $total;
            $locked->held_balance += $total;
            $locked->save();

            $transaction = WalletTransaction::query()->create([
                'wallet_id' => $locked->id,
                'type' => WalletTransactionType::Withdrawal,
                'status' => WalletTransactionStatus::Pending,
                'amount' => -$total,
                'currency' => $locked->currency,
                'method' => $data->method,
                'reference' => 'withdrawal-'.Str::uuid()->toString(),
                'description' => 'Withdrawal to card •'.$data->cardLast4(),
                'metadata' => ['fee' => $fee],
            ]);

            $withdrawal = WalletWithdrawal::query()->create([
                'wallet_id' => $locked->id,
                'transaction_id' => $transaction->id,
                'amount' => $data->amount,
                'fee' => $fee,
                'currency' => $locked->currency,
                'method' => $data->method,
                'card_last4' => $data->cardLast4(),
                'card_holder' => $data->cardHolder,
                'status' => WithdrawalStatus::Requested,
            ]);

            $transaction->related_id = $withdrawal->id;
            $transaction->save();

            $this->logger->info('wallet.withdrawal.requested', [
                'wallet_id' => $locked->id,
                'withdrawal_id' => $withdrawal->id,
                'amount' => $data->amount,
                'fee' => $fee,
            ]);

            return $withdrawal;
        });
    }

    public function cancelWithdrawal(string $userId, string $withdrawalId): WalletWithdrawal
    {
        $wallet = $this->walletFor($userId);

        return DB::transaction(function () use ($wallet, $withdrawalId): WalletWithdrawal {
            $locked = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);

            $withdrawal = WalletWithdrawal::query()
                ->where('wallet_id', $locked->id)
                ->lockForUpdate()
                ->find($withdrawalId);

            if ($withdrawal === null) {
                throw new ResourceNotFoundException('Withdrawal not found.');
            }

            if ($withdrawal->status === WithdrawalStatus::Cancelled) {
                return $withdrawal;
            }

            if ($withdrawal->status !== WithdrawalStatus::Requested) {
                throw new ConflictException('Withdrawal can no longer be cancelled.');
            }

            $this->releaseHold($locked, $withdrawal);

            $withdrawal->status = WithdrawalStatus::Cancelled;
            $withdrawal->processed_at = now();
            $withdrawal->save();

            $this->failWithdrawalTransaction($withdrawal, WalletTransactionStatus::Cancelled);

            return $withdrawal;
        });
    }

    public function settleWithdrawal(string $withdrawalId, bool $success, ?string $reason = null): WalletWithdrawal
    {
        return DB::transaction(function () use ($withdrawalId, $success, $reason): WalletWithdrawal {
            $withdrawal = WalletWithdrawal::query()->lockForUpdate()->find($withdrawalId);

            if ($withdrawal === null) {
                throw new ResourceNotFoundException('Withdrawal not found.');
            }

            if (! $withdrawal->status->holdsFunds()) {
                return $withdrawal;
            }

            $locked = Wallet::query()->lockForUpdate()->findOrFail($withdrawal->wallet_id);
            $total = $withdrawal->amount + $withdrawal->fee;

            if ($success) {
                // Held funds leave the wallet for good.
                $locked->held_balance -= $total;
                $locked->save();

                $withdrawal->status = WithdrawalStatus::Completed;
                $withdrawal->processed_at = now();
                $withdrawal->save();

                $transaction = $withdrawal->transaction;

                if ($transaction !== null) {
                    $transaction->status = WalletTransactionStatus::Completed;
                    $transaction->balance_after = $locked->available_balance;
                    $transaction->completed_at = now();
                    $transaction->save();
                }

                $this->logger->info('wallet.withdrawal.completed', [
                    'wallet_id' => $locked->id,
                    'withdrawal_id' => $withdrawal->id,
                    'amount' => $withdrawal->amount,
                ]);

                return $withdrawal;
            }

            $this->releaseHold($locked, $withdrawal);

            $withdrawal->status = WithdrawalStatus::Rejected;
            $withdrawal->rejection_reason = $reason;
            $withdrawal->processed_at = now();
            $withdrawal->save();

            $this->failWithdrawalTransaction($withdrawal, WalletTransactionStatus::Failed);

            return $withdrawal;
        });
    }

    private function releaseHold(Wallet $wallet, WalletWithdrawal $withdrawal): void
    {
        $total = $withdrawal->amount + $withdrawal->fee;

        $wallet->held_balance -= $total;
        $wallet->available_balance += $total;
        $wallet->save();
    }

    private function failWithdrawalTransaction(
        WalletWithdrawal $withdrawal,
        WalletTransactionStatus $status,
    ): void {
        $transaction = $withdrawal->transaction;

        if ($transaction === null) {
            return;
        }

        $transaction->status = $status;
        $transaction->completed_at = now();
        $transaction->save();
    }

    private function withdrawalFee(int $amount): int
    {
        $percent = (float) config('wallet.withdrawal.fee_percent', 0);

        if ($percent <= 0) {
            return 0;
        }

        return (int) ceil($amount * $percent / 100);
    }

    private function topUpPaymentUrl(WalletTransaction $transaction): ?string
    {
        if ($transaction->status->isFinal()) {
            return null;
        }

        return url('/api/v1/wallet/topups/'.$transaction->id.'/confirm');
    }
}
