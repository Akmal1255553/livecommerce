<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Contracts\Services\OrderNumberGeneratorInterface;
use App\Models\Order;
use Illuminate\Support\Str;

final class ShortCodeOrderNumberGenerator implements OrderNumberGeneratorInterface
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public function generate(): string
    {
        $prefix = (string) config('commerce.order_number_prefix', 'LC');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = $this->randomCode(8);
            $number = sprintf('%s-%s', $prefix, $code);

            if (! Order::query()->where('order_number', $number)->exists()) {
                return $number;
            }
        }

        return sprintf('%s-%s', $prefix, $this->randomCode(8).Str::upper(Str::random(2)));
    }

    private function randomCode(int $length): string
    {
        $alphabet = self::ALPHABET;
        $max = strlen($alphabet) - 1;
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $result .= $alphabet[random_int(0, $max)];
        }

        return $result;
    }
}
