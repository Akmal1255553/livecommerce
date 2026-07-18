# Sprint 8 — Messaging · Blueprint

**Version:** 1  
**Sprint:** 8  
**Status:** Implemented (`feature/sprint-8-messaging`)  
**Depends on:** Sprint 4 (orders), Sprint 5 (seller), Sprint 2 notifications stub  
**Module:** [08_MODULES.md §13](../08_MODULES.md)

## Goal

Buyer ↔ seller direct messaging with optional order link, text + image, unread tracking, block enforcement, and stub FCM via existing `NotificationService`.

## In scope

| Area | Notes |
|------|--------|
| Schema | `blocks`, `conversations`, `conversation_participants`, `messages` |
| API | `GET/POST /conversations`, `GET/POST /conversations/{id}/messages`, `PUT /conversations/{id}/read`, `GET /conversations/unread-count`, `POST/DELETE /users/{id}/block` |
| Service | `MessagingService` + thin `BlockService` |
| Push | `NotificationType::NEW_MESSAGE` → in-app + `SendPushNotificationJob` (stub FCM) |
| Media | `purpose=message_image` on presigned upload |
| Mobile | Conversation list, chat screen, entry from product/order, unread badge |
| Tests | Create conversation, send/list messages, block prevents send, mark read |

## Out of scope

- WebSocket / realtime transport (REST poll OK for MVP)
- Real FCM credentials
- Group chats
- Message edit/delete

## Realtime note

MVP uses REST (same pattern as live chat poll). Client may poll messages with `after_id` / cursor.

## Release

1. Branch → `feature/sprint-8-messaging`
2. Migrate DB
3. Mobile: Messages tab / entry points from product + order detail
