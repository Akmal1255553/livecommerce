<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A Payme Merchant API error. Payme always answers HTTP 200, so failures travel
 * inside the JSON-RPC envelope with the code the protocol assigns to each case.
 */
final class PaymeRpcException extends RuntimeException
{
    /**
     * @param  array{ru: string, uz: string, en: string}  $localizedMessage
     */
    public function __construct(
        private readonly int $rpcCode,
        private readonly array $localizedMessage,
        private readonly mixed $data = null,
    ) {
        parent::__construct($localizedMessage['en']);
    }

    public static function accountNotFound(): self
    {
        return new self(-31050, [
            'ru' => 'Заказ не найден',
            'uz' => 'Buyurtma topilmadi',
            'en' => 'Order not found',
        ], 'order_id');
    }

    public static function accountNotPayable(): self
    {
        return new self(-31051, [
            'ru' => 'Заказ не ожидает оплаты',
            'uz' => "To'lov kutilmayapti",
            'en' => 'Order is not awaiting payment',
        ], 'order_id');
    }

    public static function wrongAmount(): self
    {
        return new self(-31001, [
            'ru' => 'Неверная сумма',
            'uz' => 'Noto\'g\'ri summa',
            'en' => 'Wrong amount',
        ], 'amount');
    }

    public static function transactionNotFound(): self
    {
        return new self(-31003, [
            'ru' => 'Транзакция не найдена',
            'uz' => 'Tranzaksiya topilmadi',
            'en' => 'Transaction not found',
        ]);
    }

    public static function unableToPerform(?string $detail = null): self
    {
        $suffix = $detail === null ? '' : ': '.$detail;

        return new self(-31008, [
            'ru' => 'Невозможно выполнить операцию'.$suffix,
            'uz' => 'Amalni bajarib bo\'lmaydi'.$suffix,
            'en' => 'Unable to perform operation'.$suffix,
        ]);
    }

    public static function unableToCancel(): self
    {
        return new self(-31007, [
            'ru' => 'Невозможно отменить операцию',
            'uz' => 'Amalni bekor qilib bo\'lmaydi',
            'en' => 'Unable to cancel operation',
        ]);
    }

    public static function methodNotFound(string $method): self
    {
        return new self(-32601, [
            'ru' => 'Метод не найден',
            'uz' => 'Metod topilmadi',
            'en' => 'Method not found',
        ], $method);
    }

    public static function insufficientPrivilege(): self
    {
        return new self(-32504, [
            'ru' => 'Недостаточно привилегий',
            'uz' => 'Huquqlar yetarli emas',
            'en' => 'Insufficient privilege',
        ]);
    }

    /**
     * @return array{code: int, message: array{ru: string, uz: string, en: string}, data?: mixed}
     */
    public function toRpcError(): array
    {
        $error = [
            'code' => $this->rpcCode,
            'message' => $this->localizedMessage,
        ];

        if ($this->data !== null) {
            $error['data'] = $this->data;
        }

        return $error;
    }

    public function rpcCode(): int
    {
        return $this->rpcCode;
    }
}
