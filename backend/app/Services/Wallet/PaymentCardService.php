<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Logging\StructuredLogger;
use App\Models\UserPaymentCard;
use App\Services\BaseService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentCardService extends BaseService
{
    public function __construct(StructuredLogger $logger)
    {
        parent::__construct($logger);
    }

    /**
     * @return Collection<int, UserPaymentCard>
     */
    public function listFor(string $userId): Collection
    {
        return UserPaymentCard::query()
            ->where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();
    }

    public function store(
        string $userId,
        string $cardNumber,
        string $holderName,
        int $expMonth,
        int $expYear,
        bool $isDefault = false,
    ): UserPaymentCard {
        $digits = preg_replace('/\D+/', '', $cardNumber) ?? '';

        if (strlen($digits) < 12 || strlen($digits) > 19) {
            throw ValidationException::withMessages([
                'card_number' => ['Card number must be 12–19 digits.'],
            ]);
        }

        $last4 = substr($digits, -4);
        $brand = $this->detectBrand($digits);

        return DB::transaction(function () use ($userId, $last4, $brand, $holderName, $expMonth, $expYear, $isDefault): UserPaymentCard {
            $makeDefault = $isDefault || ! UserPaymentCard::query()->where('user_id', $userId)->exists();

            if ($makeDefault) {
                UserPaymentCard::query()
                    ->where('user_id', $userId)
                    ->update(['is_default' => false]);
            }

            $card = UserPaymentCard::query()->create([
                'user_id' => $userId,
                'last4' => $last4,
                'brand' => $brand,
                'holder_name' => $holderName,
                'exp_month' => $expMonth,
                'exp_year' => $expYear,
                'provider_token' => null,
                'is_default' => $makeDefault,
            ]);

            $this->logger->info('wallet.card.stored', [
                'user_id' => $userId,
                'card_id' => $card->id,
                'brand' => $brand,
                'last4' => $last4,
            ]);

            return $card;
        });
    }

    public function delete(string $userId, string $cardId): void
    {
        $card = UserPaymentCard::query()
            ->where('user_id', $userId)
            ->whereKey($cardId)
            ->first();

        if ($card === null) {
            throw ValidationException::withMessages([
                'card' => ['Payment card not found.'],
            ]);
        }

        $wasDefault = $card->is_default;
        $card->delete();

        if ($wasDefault) {
            $next = UserPaymentCard::query()
                ->where('user_id', $userId)
                ->orderByDesc('created_at')
                ->first();

            if ($next !== null) {
                $next->is_default = true;
                $next->save();
            }
        }
    }

    public function findOwned(string $userId, string $cardId): ?UserPaymentCard
    {
        return UserPaymentCard::query()
            ->where('user_id', $userId)
            ->whereKey($cardId)
            ->first();
    }

    private function detectBrand(string $digits): string
    {
        return match (true) {
            str_starts_with($digits, '4') => 'visa',
            (int) substr($digits, 0, 2) >= 51 && (int) substr($digits, 0, 2) <= 55 => 'mastercard',
            str_starts_with($digits, '8600') => 'uzcard',
            str_starts_with($digits, '9860') => 'humo',
            default => 'unknown',
        };
    }
}
