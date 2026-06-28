<?php

declare(strict_types=1);

namespace App\DTOs\Notification;

use App\DTOs\DataTransferObject;
use App\Models\User;
use InvalidArgumentException;

readonly class NotificationData extends DataTransferObject
{
    public function __construct(
        public string $user_id,
        public ?string $avatar,
        public string $username,
        public string $entity_id,
        public string $entity_type,
        public string $deep_link,
    ) {}

    public static function fromUser(
        User $user,
        string $entity_type,
        string $entity_id,
        string $deep_link,
    ): self {
        $profile = $user->profile;

        return new self(
            user_id: $user->id,
            avatar: $user->avatar_url,
            username: $profile !== null ? $profile->display_name : $user->username,
            entity_id: $entity_id,
            entity_type: $entity_type,
            deep_link: $deep_link,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        foreach (['user_id', 'username', 'entity_id', 'entity_type', 'deep_link'] as $key) {
            if (! array_key_exists($key, $data) || ! is_string($data[$key]) || $data[$key] === '') {
                throw new InvalidArgumentException("Notification data missing or invalid key: {$key}");
            }
        }

        $avatar = $data['avatar'] ?? null;

        if ($avatar !== null && ! is_string($avatar)) {
            throw new InvalidArgumentException('Notification data avatar must be a string or null.');
        }

        return new self(
            user_id: $data['user_id'],
            avatar: $avatar,
            username: $data['username'],
            entity_id: $data['entity_id'],
            entity_type: $data['entity_type'],
            deep_link: $data['deep_link'],
        );
    }
}
