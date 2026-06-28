# Sprint 2.2 — Notifications Foundation

**Status:** Implemented (backend)  
**Phase:** 2 — Social Foundation  
**Depends on:** Sprint 2.1 (Follow System)

## Summary

Sprint 2.2 delivers in-app notifications, FCM device registration, notification preferences, and the `NEW_FOLLOWER` hook on `UserFollowed`. Push delivery uses a stub FCM adapter (same pattern as `StubSmsProvider`).

## Notification types

| Enum | Value |
|------|-------|
| `NEW_FOLLOWER` | User followed you |
| `NEW_COMMENT` | Comment on your content |
| `NEW_LIKE` | Like on your content |
| `LIVE_STARTED` | Seller went live |
| `ORDER_CREATED` | New order placed |
| `ORDER_PAID` | Order payment confirmed |
| `REFUND_APPROVED` | Refund approved |

Types beyond `NEW_FOLLOWER` are defined for forward compatibility; handlers ship in later sprints.

## Standardized `data` payload

All notifications store a fixed schema (no arbitrary JSON):

```json
{
  "user_id": "uuid",
  "avatar": "https://cdn.example.com/avatars/uuid.jpg",
  "username": "johndoe",
  "entity_id": "uuid",
  "entity_type": "user",
  "deep_link": "/profile/uuid"
}
```

Implemented via `App\DTOs\Notification\NotificationData`.

## Backend

### Migration

| Table | Purpose |
|-------|---------|
| `notifications` | In-app notifications with cursor-paginated feed |
| `user_devices` | FCM tokens (migration from Sprint 0; model + repo in 2.2) |

### Services & Jobs

- `NotificationService` — create, list, mark read, device registration, settings
- `NotificationData` DTO — enforced payload schema
- `StubFcmPushNotification` — logs push payloads (non-production)
- `SendPushNotificationJob` — queued push to active device tokens
- `NotifyOnFollow` listener — creates `NEW_FOLLOWER` notification on follow

### API Endpoints

All under `/api/v1` (auth required):

| Method | Path | Description |
|--------|------|-------------|
| GET | `/notifications` | List notifications (cursor: `cursor`, `limit`) |
| GET | `/notifications/unread-count` | Unread count |
| PUT | `/notifications/{id}/read` | Mark one as read |
| PUT | `/notifications/read-all` | Mark all as read |
| PUT | `/me/notification-settings` | Update preferences |
| POST | `/devices` | Register FCM token |
| DELETE | `/devices/{token}` | Deactivate device token |

### Example notification (`NEW_FOLLOWER`)

```json
{
  "type": "NEW_FOLLOWER",
  "title": "New follower",
  "body": "johndoe started following you.",
  "data": {
    "user_id": "follower-uuid",
    "avatar": null,
    "username": "johndoe",
    "entity_id": "follower-uuid",
    "entity_type": "user",
    "deep_link": "/profile/follower-uuid"
  }
}
```

### Preferences

Stored in `user_profiles.notification_settings`. Keys use enum values (e.g. `NEW_FOLLOWER`). When disabled, no in-app row and no push job.

### Tests

`tests/Feature/Notification/NotificationTest.php` — 11 tests covering devices, follow→notification, standardized payload, cursor list, read/unread, settings, authorization.

## Deferred (Sprint 2.3+)

- Real FCM HTTP integration
- Mobile notification center + FCM SDK
- Handlers for `NEW_COMMENT`, `NEW_LIKE`, order/live types
- 90-day notification cleanup job
- Blocks module

## Verification

```bash
docker compose -f docker/docker-compose.yml exec app php artisan migrate --force
docker compose -f docker/docker-compose.yml exec app ./vendor/bin/pest
```

Expected: **40 passed**.

## Acceptance Criteria

- [x] In-app notification created when user is followed
- [x] Standardized `NotificationData` payload enforced
- [x] Push job dispatched when preferences allow
- [x] Device register/unregister
- [x] Notification list with cursor pagination
- [x] Mark read / mark all read / unread count
- [x] Notification settings update
- [x] Feature tests passing
- [ ] Real FCM delivery (production)
- [ ] Mobile notification UI (Sprint 2.3+)
