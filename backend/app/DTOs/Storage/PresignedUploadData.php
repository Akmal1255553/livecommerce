<?php

declare(strict_types=1);

namespace App\DTOs\Storage;

use App\DTOs\DataTransferObject;

readonly class PresignedUploadData extends DataTransferObject
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public string $url,
        public string $method,
        public array $headers,
        public string $expiresAt,
        public string $storagePath,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'upload_url' => $this->url,
            'upload_method' => $this->method,
            'upload_headers' => $this->headers,
            'expires_at' => $this->expiresAt,
            'storage_path' => $this->storagePath,
        ];
    }
}
