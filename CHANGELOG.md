# Changelog

All notable changes to the LiveCommerce monorepo are documented in this file.

Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Changed

#### Sprint 3 restructure (2026-06-28)

The monolithic **Sprint 3 — Video Platform** (2 weeks, upload + feed + likes + trending in one sprint) was split into **Sprint 3.1–3.4** after Sprint 2.3 shipped the feed read-path separately.

**Why split:**

1. **Feed already delivered** — Sprint 2.3 covers `GET /feed/for-you` and `/feed/following`; Sprint 3 no longer mixes read and write paths.
2. **Storage abstraction required** — `StorageService` (Local → S3 → MinIO → CDN) must exist before upload; no direct `Storage::` in controllers.
3. **Processing pipeline is multi-stage** — Upload → VirusScan → Metadata → Thumbnail → Transcoding → Moderation → Publish needs discrete queued jobs, not one `ProcessVideoJob`.
4. **Metrics before scale** — Engagement events (`feed_open`, `video_impression`, `watch_time`, etc.) start in 3.1, not after launch.
5. **Interactions vs ranking are separate concerns** — Likes/comments/views (3.3) ship before rule-based trending/popular/new (3.4); Sprint 9 ML replaces 3.4 internals only.

**New sub-sprints:**

| Sub-sprint | Scope |
|------------|-------|
| 3.1 | Upload Foundation — `StorageService`, MinIO, presigned URL, pipeline skeleton, metrics |
| 3.2 | Video Processing — FFmpeg thumbnail + HLS, queue errors |
| 3.3 | Video Interactions — likes, comments, views, shares, bookmarks |
| 3.4 | Recommendation Engine v1 — trending, popular, new, rule-based For You |

**Dependency updates:**

- Sprint 4 → depends on **Sprint 3.3** (published videos + interactions for product–video tagging)
- Sprint 9 → depends on **Sprint 3.4** (rule-based recommendations baseline for ML replacement)

**Docs updated:** [13_ROADMAP.md](./13_ROADMAP.md), [12_MASTER_PLAN.md](./12_MASTER_PLAN.md), [docs/17_DEPENDENCY_MATRIX.md](./docs/17_DEPENDENCY_MATRIX.md), [docs/SPRINT_3_VIDEO_PLATFORM.md](./docs/SPRINT_3_VIDEO_PLATFORM.md), [docs01_PRD.md](./docs01_PRD.md) (implementation mapping)

### Added

#### Sprint 2.3 — Feed Foundation

- `videos` table migration (minimal publishable schema)
- `VideoStatus`, `VideoVisibility` enums
- `VideoService` — For You and Following feeds with cursor pagination
- `VideoRepository::cursorPaginateFeed()` — published/public filter, following user filter
- `VideoResource`, `UserCompactResource`, `FeedController`
- API endpoints:
  - `GET /api/v1/feed/for-you` (optional auth)
  - `GET /api/v1/feed/following` (auth required)
- `CursorPaginationData` video cursor helpers (`encodeVideoCursor`, `decodeVideoCursor`, `nextVideoCursor`)
- Feature tests: `tests/Feature/Feed/FeedTest.php` (7 tests)
- Mobile: `features/feed/` — repository, providers, vertical `FeedScreen` with For You / Following tabs

#### Sprint 2.2 — Notifications Foundation

- `notifications` table migration with cursor-friendly indexes
- `NotificationType` enum: `NEW_FOLLOWER`, `NEW_COMMENT`, `NEW_LIKE`, `LIVE_STARTED`, `ORDER_CREATED`, `ORDER_PAID`, `REFUND_APPROVED`
- `NotificationData` DTO — fixed payload schema (`user_id`, `avatar`, `username`, `entity_id`, `entity_type`, `deep_link`)
- `NotificationService` — in-app notifications, cursor pagination, unread count, mark read/all
- `UserDevice` model + repository — FCM token register/unregister
- `NotifyOnFollow` listener on `UserFollowed` → `NEW_FOLLOWER` notification
- `SendPushNotificationJob` + `StubFcmPushNotification` (log-only push adapter)
- API endpoints:
  - `GET /api/v1/notifications`
  - `GET /api/v1/notifications/unread-count`
  - `PUT /api/v1/notifications/{id}/read`
  - `PUT /api/v1/notifications/read-all`
  - `PUT /api/v1/me/notification-settings`
  - `POST /api/v1/devices`
  - `DELETE /api/v1/devices/{token}`
- `NotificationResource`, `NotificationController`, `DeviceController`
- `CursorPaginationData` + `ApiResponse::cursorPaginated()`
- Feature tests: `tests/Feature/Notification/NotificationTest.php` (11 tests)

#### Sprint 2.1 — Follow System

- `follows` table migration
- `FollowService` — follow/unfollow with transactional counter updates
- `UserFollowed` event
- API endpoints:
  - `GET /api/v1/users/{id}`
  - `POST /api/v1/users/{id}/follow`
  - `DELETE /api/v1/users/{id}/follow`
  - `GET /api/v1/users/{id}/followers`
  - `GET /api/v1/users/{id}/following`
- `auth.api.optional` middleware for public profile routes
- Feature tests: `tests/Feature/Follow/FollowTest.php` (16 tests)

### Changed

- `NotificationType` values standardized to `SCREAMING_SNAKE_CASE` (was `new_follower`)
- Notification `data` column restricted to `NotificationData` schema (no arbitrary JSON)
- Roadmap restructured: Sprint 2 split into 2.1 (Follow), 2.2 (Notifications), 2.3 (Feed Foundation)

### Fixed

- Pest dev dependency compatibility for Laravel 12 (`pest ^3.8.2`, `phpunit 11.5.50`)
- UTF-8 BOM removed from service files blocking `strict_types`
- Duplicate event listener registration causing double counter increments (Sprint 2.1)

## [0.1.0] — 2026-06-27

### Added

- Sprint 0 — infrastructure, project skeleton, developer experience
- Sprint 1 — JWT authentication, OTP, profile management, mobile auth UX
- Docker Compose stack (PHP 8.4, PostgreSQL, Redis, MinIO, Mailpit)
- GitHub Actions CI for backend and mobile

[Unreleased]: https://github.com/Akmal1255553/livecommerce/compare/main...HEAD
[0.1.0]: https://github.com/Akmal1255553/livecommerce/releases/tag/v0.1.0
