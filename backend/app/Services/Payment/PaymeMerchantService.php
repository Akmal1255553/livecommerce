<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Services\PaymentWebhookProcessorInterface;
use App\DTOs\Payment\PaymentSubject;
use App\DTOs\Payment\PaymentWebhookData;
use App\Enums\PaymeTransactionState;
use App\Exceptions\PaymeRpcException;
use App\Models\PaymeTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Payme Merchant API (Paycom JSON-RPC protocol).
 *
 * Payme never charges on a single call: it asks whether an account can be billed, creates a
 * transaction, and only then performs it — with cancellation and reconciliation as separate
 * methods. Money is moved once, in PerformTransaction, by handing the callback to the shared
 * webhook processor so orders and wallet top-ups settle through the same path as other providers.
 *
 * Amounts on the wire are tiyin (1 UZS = 100 tiyin); times are epoch milliseconds.
 */
final class PaymeMerchantService
{
    /** Payme abandons a transaction that was created but never performed within 12 hours. */
    private const TIMEOUT_MS = 12 * 60 * 60 * 1000;

    /** Reason code Payme expects when the merchant cancels a timed-out transaction. */
    private const REASON_TIMEOUT = 4;

    public function __construct(
        private readonly PaymentSubjectLookup $subjects,
        private readonly PaymentWebhookProcessorInterface $processor,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws PaymeRpcException
     */
    public function handle(string $method, array $params): array
    {
        return match ($method) {
            'CheckPerformTransaction' => $this->checkPerformTransaction($params),
            'CreateTransaction' => $this->createTransaction($params),
            'PerformTransaction' => $this->performTransaction($params),
            'CancelTransaction' => $this->cancelTransaction($params),
            'CheckTransaction' => $this->checkTransaction($params),
            'GetStatement' => $this->getStatement($params),
            default => throw PaymeRpcException::methodNotFound($method),
        };
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function checkPerformTransaction(array $params): array
    {
        $this->assertChargeable($this->reference($params), $this->amount($params));

        return ['allow' => true];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function createTransaction(array $params): array
    {
        $paymeId = $this->paymeId($params);
        $reference = $this->reference($params);
        $amount = $this->amount($params);
        $paymeTime = (int) ($params['time'] ?? 0);

        return DB::transaction(function () use ($paymeId, $reference, $amount, $paymeTime): array {
            $existing = PaymeTransaction::query()
                ->where('payme_transaction_id', $paymeId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->state !== PaymeTransactionState::Created) {
                    throw PaymeRpcException::unableToPerform();
                }

                if ($this->hasTimedOut($existing)) {
                    $this->markCancelled($existing, PaymeTransactionState::CancelledBeforePerform, self::REASON_TIMEOUT);

                    throw PaymeRpcException::unableToPerform();
                }

                return $this->createdPayload($existing);
            }

            $this->assertChargeable($reference, $amount);

            $activeExists = PaymeTransaction::query()
                ->where('reference', $reference)
                ->where('state', PaymeTransactionState::Created->value)
                ->exists();

            if ($activeExists) {
                throw PaymeRpcException::unableToPerform('another transaction is already pending');
            }

            $transaction = PaymeTransaction::query()->create([
                'payme_transaction_id' => $paymeId,
                'reference' => $reference,
                'amount' => $amount,
                'state' => PaymeTransactionState::Created,
                'payme_time' => $paymeTime,
                'create_time' => $this->nowMs(),
            ]);

            return $this->createdPayload($transaction);
        });
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function performTransaction(array $params): array
    {
        $paymeId = $this->paymeId($params);

        return DB::transaction(function () use ($paymeId): array {
            $transaction = $this->lockTransaction($paymeId);

            if ($transaction->state === PaymeTransactionState::Performed) {
                return $this->performedPayload($transaction);
            }

            if ($transaction->state !== PaymeTransactionState::Created) {
                throw PaymeRpcException::unableToPerform();
            }

            if ($this->hasTimedOut($transaction)) {
                $this->markCancelled($transaction, PaymeTransactionState::CancelledBeforePerform, self::REASON_TIMEOUT);

                throw PaymeRpcException::unableToPerform();
            }

            $subject = $this->subjects->find($transaction->reference);

            if ($subject === null) {
                throw PaymeRpcException::accountNotFound();
            }

            try {
                $this->processor->apply(new PaymentWebhookData(
                    event: 'payment.success',
                    transactionId: $transaction->payme_transaction_id,
                    orderId: $transaction->reference,
                    amount: intdiv($transaction->amount, 100),
                    currency: $subject->currency,
                ));
            } catch (Throwable $e) {
                Log::warning('payment.payme.perform.rejected', [
                    'payme_transaction_id' => $transaction->payme_transaction_id,
                    'reference' => $transaction->reference,
                    'message' => $e->getMessage(),
                ]);

                throw PaymeRpcException::unableToPerform();
            }

            $transaction->state = PaymeTransactionState::Performed;
            $transaction->perform_time = $this->nowMs();
            $transaction->save();

            return $this->performedPayload($transaction);
        });
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function cancelTransaction(array $params): array
    {
        $paymeId = $this->paymeId($params);
        $reason = isset($params['reason']) ? (int) $params['reason'] : null;

        return DB::transaction(function () use ($paymeId, $reason): array {
            $transaction = $this->lockTransaction($paymeId);

            if ($transaction->state->isCancelled()) {
                return $this->cancelledPayload($transaction);
            }

            // Reversing settled money needs a payout back to the card, which no provider
            // contract covers yet. Payme accepts -31007 for goods that cannot be returned.
            if ($transaction->state === PaymeTransactionState::Performed) {
                Log::warning('payment.payme.cancel.refused', [
                    'payme_transaction_id' => $transaction->payme_transaction_id,
                    'reference' => $transaction->reference,
                ]);

                throw PaymeRpcException::unableToCancel();
            }

            $this->releaseSubject($transaction);
            $this->markCancelled($transaction, PaymeTransactionState::CancelledBeforePerform, $reason);

            return $this->cancelledPayload($transaction);
        });
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function checkTransaction(array $params): array
    {
        $transaction = PaymeTransaction::query()
            ->where('payme_transaction_id', $this->paymeId($params))
            ->first();

        if ($transaction === null) {
            throw PaymeRpcException::transactionNotFound();
        }

        return [
            'create_time' => $transaction->create_time,
            'perform_time' => $transaction->perform_time ?? 0,
            'cancel_time' => $transaction->cancel_time ?? 0,
            'transaction' => (string) $transaction->id,
            'state' => $transaction->state->value,
            'reason' => $transaction->reason,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function getStatement(array $params): array
    {
        $from = (int) ($params['from'] ?? 0);
        $to = (int) ($params['to'] ?? 0);

        $transactions = PaymeTransaction::query()
            ->whereBetween('create_time', [$from, $to])
            ->orderBy('create_time')
            ->get()
            ->map(fn (PaymeTransaction $transaction): array => [
                'id' => $transaction->payme_transaction_id,
                'time' => $transaction->payme_time,
                'amount' => $transaction->amount,
                'account' => ['order_id' => $transaction->reference],
                'create_time' => $transaction->create_time,
                'perform_time' => $transaction->perform_time ?? 0,
                'cancel_time' => $transaction->cancel_time ?? 0,
                'transaction' => (string) $transaction->id,
                'state' => $transaction->state->value,
                'reason' => $transaction->reason,
            ])
            ->all();

        return ['transactions' => $transactions];
    }

    /**
     * @throws PaymeRpcException
     */
    private function assertChargeable(string $reference, int $amountTiyin): PaymentSubject
    {
        $subject = $this->subjects->find($reference);

        if ($subject === null) {
            throw PaymeRpcException::accountNotFound();
        }

        if (! $subject->chargeable) {
            throw PaymeRpcException::accountNotPayable();
        }

        if ($amountTiyin !== $subject->amount * 100) {
            throw PaymeRpcException::wrongAmount();
        }

        return $subject;
    }

    /**
     * Cancelling before the money moved should release whatever the reference was holding —
     * an unpaid order goes back to cancelled, a pending top-up to failed.
     */
    private function releaseSubject(PaymeTransaction $transaction): void
    {
        try {
            $this->processor->apply(new PaymentWebhookData(
                event: 'payment.failed',
                transactionId: $transaction->payme_transaction_id,
                orderId: $transaction->reference,
                amount: intdiv($transaction->amount, 100),
                currency: 'UZS',
            ));
        } catch (Throwable $e) {
            // Payme must always be able to cancel; a subject that already moved on is not a reason to refuse.
            Log::warning('payment.payme.cancel.release_skipped', [
                'payme_transaction_id' => $transaction->payme_transaction_id,
                'reference' => $transaction->reference,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function markCancelled(PaymeTransaction $transaction, PaymeTransactionState $state, ?int $reason): void
    {
        $transaction->state = $state;
        $transaction->reason = $reason;
        $transaction->cancel_time = $this->nowMs();
        $transaction->save();
    }

    private function lockTransaction(string $paymeId): PaymeTransaction
    {
        $transaction = PaymeTransaction::query()
            ->where('payme_transaction_id', $paymeId)
            ->lockForUpdate()
            ->first();

        if ($transaction === null) {
            throw PaymeRpcException::transactionNotFound();
        }

        return $transaction;
    }

    private function hasTimedOut(PaymeTransaction $transaction): bool
    {
        return $this->nowMs() - $transaction->create_time > self::TIMEOUT_MS;
    }

    /**
     * @return array<string, mixed>
     */
    private function createdPayload(PaymeTransaction $transaction): array
    {
        return [
            'create_time' => $transaction->create_time,
            'transaction' => (string) $transaction->id,
            'state' => $transaction->state->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function performedPayload(PaymeTransaction $transaction): array
    {
        return [
            'transaction' => (string) $transaction->id,
            'perform_time' => $transaction->perform_time ?? 0,
            'state' => $transaction->state->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cancelledPayload(PaymeTransaction $transaction): array
    {
        return [
            'transaction' => (string) $transaction->id,
            'cancel_time' => $transaction->cancel_time ?? 0,
            'state' => $transaction->state->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function paymeId(array $params): string
    {
        $id = (string) ($params['id'] ?? '');

        if ($id === '') {
            throw PaymeRpcException::transactionNotFound();
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function reference(array $params): string
    {
        $account = is_array($params['account'] ?? null) ? $params['account'] : [];
        $reference = (string) ($account['order_id'] ?? '');

        if ($reference === '') {
            throw PaymeRpcException::accountNotFound();
        }

        return $reference;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function amount(array $params): int
    {
        return (int) round((float) ($params['amount'] ?? 0));
    }

    private function nowMs(): int
    {
        return (int) now()->getPreciseTimestamp(3);
    }
}
