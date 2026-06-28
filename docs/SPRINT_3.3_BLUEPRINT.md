# Sprint 3.3 — Video Interactions · Blueprint

**Status:** Approved (2026-06-28)  
**Phase:** 3 — Video Platform  
**Depends on:** Sprint 3.2 (approved + merged)  
**Part of:** [Sprint 3 overview](./SPRINT_3_VIDEO_PLATFORM.md)

> **Gate rule:** No Sprint 3.3 code may be merged until this blueprint is reviewed and marked **Approved**.

---

## 1. Goal

Add **social interactions** on published videos: likes, comments (1-level replies), bookmarks, shares/reposts, view counting, and playback engagement metrics. Update `VideoResource` with real viewer-specific flags.

**In scope:** interaction APIs, counter denormalization, Redis-debounced views, watch-time metrics, mobile UX (like, comments, share, bookmark).

**Out of scope:** comment likes, multi-level comment threads, rule-based feed ranking (3.4), product tags (Sprint 4), push notifications for every like (batch/defer acceptable).

---

## 2. API

All paths `/api/v1`.

### 2.1 Endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/videos/{id}/like` | Required | Like video |
| DELETE | `/videos/{id}/like` | Required | Unlike video |
| POST | `/videos/{id}/view` | Optional | Record view (debounced) |
| GET | `/videos/{id}/comments` | Optional | List comments (offset) |
| POST | `/videos/{id}/comments` | Required | Add comment or reply |
| DELETE | `/videos/{id}/comments/{commentId}` | Required | Soft-delete own comment |
| POST | `/videos/{id}/bookmark` | Required | Bookmark video |
| DELETE | `/videos/{id}/bookmark` | Required | Remove bookmark |
| GET | `/bookmarks` | Required | Bookmarked videos (cursor) |
| POST | `/videos/{id}/share` | Required | Record share / repost |
| PUT | `/videos/{id}` | Required | Update title, description, visibility (owner) |
| DELETE | `/videos/{id}` | Required | Soft-delete (owner) |
| POST | `/metrics/events` | Optional | Extended event types (§2.5) |

**Feed endpoints unchanged** — but `VideoResource` gains real `is_liked`, `is_bookmarked` when auth present.

### 2.2 POST `/videos/{id}/like`

**Response: 201 Created**
```json
{ "success": true, "data": { "liked": true, "like_count": 42 } }
```

**Rules:** idempotent — second like returns 200 with same payload; only published videos.

### 2.3 POST `/videos/{id}/view`

**Request:** `{ "session_id": "uuid" }`

**Response: 202 Accepted** — view counted at most once per `session_id` + `video_id` per 24h (Redis key `view:{video_id}:{session_id}`).

Flush: `FlushVideoViewsJob` every 60s increments `videos.view_count` from Redis counters.

### 2.4 Comments

**GET `/videos/{id}/comments?page=1&per_page=20`**

Returns top-level comments with nested `replies` (max 1 level).

**POST `/videos/{id}/comments`**
```json
{ "body": "Great video!", "parent_id": null }
```
| Field | Rules |
|-------|-------|
| body | Required, 1–1000 chars |
| parent_id | Optional; must be top-level comment on same video |

**Response: 201** — `CommentResource`

### 2.5 POST `/videos/{id}/share`

**Request:**
```json
{ "channel": "link", "session_id": "uuid" }
```

`channel`: `link`, `copy`, `external` (MVP).

**Response: 201** — increments `share_count`; records `share` engagement event.

### 2.6 Analytics Layer (extended metrics funnel)

Canonical spec: [`docs/ANALYTICS_LAYER.md`](./ANALYTICS_LAYER.md).

All engagement signals flow through `POST /metrics/events` (batch, max 20) and interaction APIs with required `session_id` for funnel correlation.

**Recommendation funnel (Sprint 3.4 + Sprint 9 inputs):**

```
feed_open → video_impression → video_start → 25% → 50% → 75% → 100% → watch_time → like → comment → share → save → follow_after_watch
```

| UI action | Analytics `type` | API |
|-----------|------------------|-----|
| ❤️ Like | `like` | `POST /videos/{id}/like` |
| 💬 Comment | `comment` | `POST /videos/{id}/comments` |
| 👁 View | `view` | `POST /videos/{id}/view` |
| ⏱ Watch Time | `watch_time` | `POST /metrics/events` |
| 📌 Save | `save` | `POST /videos/{id}/bookmark` |
| ↗ Share | `share` | `POST /videos/{id}/share` |

**Additional `type` values (metrics batch):**

| type | payload |
|------|---------|
| `video_start` | `{ "position": 0 }` |
| `video_progress_25` | `{ "percent": 25, "position": N }` |
| `video_progress_50` | `{ "percent": 50, "position": N }` |
| `video_progress_75` | `{ "percent": 75, "position": N }` |
| `video_progress_100` | `{ "percent": 100, "position": N }` |
| `watch_time` | `{ "seconds": 5, "position": 12 }` |
| `follow_after_watch` | `{ "creator_id": "uuid" }` |
| `skip` | `{ "watched_seconds": 2 }` |

Same batch endpoint as 3.1; `MetricsService` is the only write path to `engagement_events`.

### 2.7 GET `/bookmarks`

Cursor pagination — same meta shape as feed (`next_cursor`, `has_more`, `limit`).

---

## 3. Database schema

### 3.1 New tables

#### `video_likes`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGSERIAL | PK |
| user_id | UUID | FK → users, NOT NULL |
| video_id | UUID | FK → videos, NOT NULL |
| created_at | TIMESTAMP | NOT NULL |

**Unique:** `(user_id, video_id)` · **Index:** `(video_id)`.

#### `comments`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGSERIAL | PK |
| user_id | UUID | FK → users, NOT NULL |
| video_id | UUID | FK → videos, NOT NULL |
| parent_id | BIGINT | FK → comments, NULLABLE |
| body | TEXT | NOT NULL |
| like_count | INTEGER | DEFAULT 0 |
| created_at | TIMESTAMP | NOT NULL |
| updated_at | TIMESTAMP | NOT NULL |
| deleted_at | TIMESTAMP | NULLABLE |

**Indexes:** `(video_id, created_at DESC)`, `(parent_id)`.

**Constraint:** `parent_id` must reference top-level comment (enforce in service).

#### `bookmarks`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGSERIAL | PK |
| user_id | UUID | FK → users, NOT NULL |
| video_id | UUID | FK → videos, NOT NULL |
| created_at | TIMESTAMP | NOT NULL |

**Unique:** `(user_id, video_id)`.

#### `video_shares`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGSERIAL | PK |
| user_id | UUID | FK → users, NOT NULL |
| video_id | UUID | FK → videos, NOT NULL |
| channel | VARCHAR(20) | NOT NULL |
| created_at | TIMESTAMP | NOT NULL |

**Index:** `(video_id, created_at DESC)`.

### 3.2 Redis keys

| Key pattern | Purpose | TTL |
|-------------|---------|-----|
| `view:{video_id}:{session_id}` | View dedup | 86400s |
| `views:pending:{video_id}` | Counter buffer | — |
| `like:lock:{video_id}:{user_id}` | Prevent race on concurrent likes | 5s |

### 3.3 `engagement_events`

Reuse from 3.1 — add event types: `watch_time`, `completion`, `skip`, `like`, `comment`, `share`.

---

## 4. Events

| Event | Trigger | Payload | Listeners |
|-------|---------|---------|-----------|
| `VideoLiked` | Like created | `videoId`, `userId`, `ownerId` | `NotifyOnVideoLiked` (stub → NEW_LIKE in Sprint 2.2 pattern) |
| `VideoUnliked` | Unlike | `videoId`, `userId` | Decrement counter (sync in service) |
| `CommentCreated` | Comment POST | `commentId`, `videoId`, `userId`, `ownerId` | `NotifyOnComment` (stub) |
| `VideoShared` | Share POST | `videoId`, `userId`, `channel` | Metrics rollup |
| `VideoBookmarked` | Bookmark POST | `videoId`, `userId` | — |
| `VideoViewRecorded` | View debounce pass | `videoId`, `sessionId` | Increment Redis buffer |

**Counter updates:** transactional `lockForUpdate` on `videos` row when flushing like/comment/share counts (same pattern as FollowService).

---

## 5. Queues

| Job | Queue | Schedule | Description |
|-----|-------|----------|-------------|
| `FlushVideoViewsJob` | default | Every 60s (scheduler) | Redis pending → `videos.view_count` |
| `RecordEngagementMetricsJob` | default | On dispatch (optional) | Async bulk insert if POST /metrics/events volume high |

**MVP:** synchronous insert in `MetricsService` acceptable; job for >100 events/s path.

---

## 6. Services

| Contract | Responsibility |
|----------|----------------|
| `VideoInteractionServiceInterface` | like, unlike, view, share, bookmark |
| `CommentServiceInterface` | list, create, delete |
| `MetricsServiceInterface` | extended event types |
| `VideoService` | enrich feed with `is_liked`, `is_bookmarked` for authenticated viewer |

**VideoResource:** when `$request->user()` present, query interaction state (batch `whereIn` for feed pages — avoid N+1).

---

## 7. Test cases

File: `tests/Feature/Video/VideoInteractionTest.php`

| # | Test | Assert |
|---|------|--------|
| 1 | User can like published video | 201, like_count +1 |
| 2 | Duplicate like is idempotent | 200, count unchanged |
| 3 | User can unlike | 200, like_count -1 |
| 4 | Unlike when not liked | 404 |
| 5 | Like on non-published video | 404 or 422 |
| 6 | View recorded once per session | 202; second call same session ignored |
| 7 | FlushVideoViewsJob increments view_count | DB count matches |
| 8 | User can post top-level comment | 201, comment_count +1 |
| 9 | User can reply to top-level comment | 201, parent_id set |
| 10 | Reply to reply rejected | 422 |
| 11 | User can delete own comment | soft deleted |
| 12 | User cannot delete others comment | 403 |
| 13 | Bookmark and unbookmark | 201/200 |
| 14 | GET /bookmarks returns cursor page | 200, meta.has_more |
| 15 | Share increments share_count | 201 |
| 16 | VideoResource includes is_liked=true for liker | feed + detail |
| 17 | VideoResource includes is_bookmarked | feed + detail |
| 18 | watch_time metric persisted | engagement_events row |
| 19 | completion metric persisted | engagement_events row |
| 20 | Owner can PUT metadata | 200 |
| 21 | Owner can soft DELETE video | 204; excluded from feed |

File: `tests/Feature/Video/VideoCommentTest.php` — pagination, empty body, max length.

---

## 8. Acceptance criteria

- [ ] Blueprint reviewed and marked **Approved**
- [ ] Migrations: video_likes, comments, bookmarks, video_shares
- [ ] Like/unlike idempotent with accurate like_count
- [ ] Comments with 1-level replies; pagination works
- [ ] Bookmarks cursor list works
- [ ] Share recorded; share_count updated
- [ ] View debounce + flush job accurate
- [ ] Metrics: watch_time, completion, skip recorded
- [ ] VideoResource reflects viewer like/bookmark state in feeds
- [ ] Mobile: double-tap like, comments sheet, share sheet, bookmark
- [ ] All test cases in §7 passing

---

## 9. Mobile

- Double-tap like + heart animation
- Comments bottom sheet (list, add, reply)
- Share via system share sheet → POST `/videos/{id}/share`
- Bookmark toggle on sidebar
- Playback heartbeat → `/metrics/events` every 5s

---

## 10. Approval

| Role | Name | Date | Status |
|------|------|------|--------|
| CTO / Founder | | 2026-06-28 | Approved |
| Backend lead | | 2026-06-28 | Approved |

**Approved** unlocks `feature/sprint-3.3-interactions`.
