<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Contracts\Services\OrderNumberGeneratorInterface;
use Illuminate\Support\Facades\DB;

final class DateSequenceOrderNumberGenerator implements OrderNumberGeneratorInterface
{
    public function generate(): string
    {
        $prefix = (string) config('commerce.order_number_prefix', 'LC');
        $date = now()->format('Ymd');

        return DB::transaction(function () use ($prefix, $date): string {
            $row = DB::table('order_number_sequences')
                ->where('date', $date)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                DB::table('order_number_sequences')->insert([
                    'date' => $date,
                    'last_sequence' => 1,
                ]);

                $sequence = 1;
            } else {
                $sequence = (int) $row->last_sequence + 1;
                DB::table('order_number_sequences')
                    ->where('date', $date)
                    ->update(['last_sequence' => $sequence]);
            }

            return sprintf('%s-%s-%06d', $prefix, $date, $sequence);
        });
    }
}
