<?php

declare(strict_types=1);

namespace App\DTOs;

abstract readonly class DataTransferObject
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        foreach (get_object_vars($this) as $key => $value) {
            if ($value instanceof DataTransferObject) {
                $data[$key] = $value->toArray();

                continue;
            }

            if (is_array($value)) {
                $data[$key] = array_map(
                    static fn (mixed $item): mixed => $item instanceof DataTransferObject ? $item->toArray() : $item,
                    $value,
                );

                continue;
            }

            $data[$key] = $value;
        }

        return $data;
    }
}
