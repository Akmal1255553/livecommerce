# Analytics Layer

**Status:** Active from Sprint 3.3  
**Consumers:** Sprint 3.4 (rule-based recommendations), Sprint 9 (ML ranking)  
**Write path:** `MetricsService` only — no direct `engagement_events` inserts outside services

---

## Interaction surface (Sprint 3.3)

| UX | API | Analytics event |
|----|-----|-----------------|
| ❤️ Like | `POST /videos/{id}/like` | `like` |
| 💬 Comment | `POST /videos/{id}/comments` | `comment` |
| 👁 View | `POST /videos/{id}/view` | `view` |
| ⏱ Watch time | `POST /metrics/events` | `watch_time` (+ progress milestones) |
| 📌 Save | `POST /videos/{id}/bookmark` | `save` |
| ↗ Share | `POST /videos/{id}/share` | `share` |

---

## Engagement funnel

All events append to `engagement_events` (immutable log). Pass the same `session_id` across a watch session so 3.4 can reconstruct user journeys.

```
feed_open
    ↓
video_impression
    ↓
video_start
    ↓
video_progress_25
    ↓
video_progress_50
    ↓
video_progress_75
    ↓
video_progress_100
    ↓
watch_time          (heartbeat every ~5s while playing)
    ↓
like | comment | share | save | follow_after_watch
```

**Branch events (optional):**

- `skip` — user left before 25% (negative signal for ranking)

---

## Ingestion

### Client batch — `POST /api/v1/metrics/events`

Discovery + playback events. Auth optional (anonymous allowed with `session_id`).

```json
{
  "events": [
    { "type": "feed_open", "session_id": "uuid", "payload": { "tab": "for_you" } },
    { "type": "video_impression", "session_id": "uuid", "video_id": "uuid", "payload": { "position": 0 } },
    { "type": "video_start", "session_id": "uuid", "video_id": "uuid" },
    { "type": "video_progress_25", "session_id": "uuid", "video_id": "uuid", "payload": { "percent": 25, "position": 11 } },
    { "type": "watch_time", "session_id": "uuid", "video_id": "uuid", "payload": { "seconds": 5, "position": 12 } },
    { "type": "follow_after_watch", "session_id": "uuid", "video_id": "uuid", "payload": { "creator_id": "uuid" } }
  ]
}
```

Max **20 events** per request (unchanged from 3.1).

### Server-side — interaction APIs

When the user performs an action, the corresponding service records the analytics event via `MetricsService::record()` with the client-supplied `session_id` (required on interaction endpoints for correlation).

| Event | Source |
|-------|--------|
| `view` | `VideoInteractionService::recordView()` |
| `like` | `VideoInteractionService::like()` |
| `comment` | `CommentService::create()` |
| `share` | `VideoInteractionService::share()` |
| `save` | `VideoInteractionService::bookmark()` |

---

## Event reference

| Type | `video_id` | Payload |
|------|------------|---------|
| `feed_open` | — | `{ "tab": "for_you" \| "following" }` |
| `video_impression` | required | `{ "position": int, "visible_ms": int }` |
| `video_start` | required | `{ "position": 0 }` optional |
| `video_progress_25` | required | `{ "percent": 25, "position": int }` |
| `video_progress_50` | required | `{ "percent": 50, "position": int }` |
| `video_progress_75` | required | `{ "percent": 75, "position": int }` |
| `video_progress_100` | required | `{ "percent": 100, "position": int }` |
| `watch_time` | required | `{ "seconds": int, "position": int }` |
| `skip` | required | `{ "watched_seconds": int }` |
| `view` | required | `{}` |
| `like` | required | `{}` |
| `comment` | required | `{ "comment_id": int }` |
| `share` | required | `{ "channel": "link" \| "copy" \| "external" }` |
| `save` | required | `{}` |
| `follow_after_watch` | required | `{ "creator_id": "uuid" }` |

---

## Sprint 3.4 usage

`AggregateEngagementRollupsJob` (blueprint 3.4) will read `engagement_events` grouped by `video_id` + `event_type` + time window. Funnel weights (initial):

| Signal | Weight hint |
|--------|-------------|
| `video_progress_100` | High completion |
| `watch_time` | Duration × recency |
| `like`, `comment`, `share`, `save` | Social proof |
| `follow_after_watch` | Strong creator affinity |
| `skip` | Negative |

Exact scoring lives in Sprint 3.4 blueprint — this document defines **what to collect**.

---

## Rules

1. **Single write path** — `MetricsService::record()` / `recordEvents()`
2. **Same session_id** per feed tab session (mobile generates once per tab open)
3. **Progress milestones** — fire once per session per video (client responsibility; server accepts all)
4. **No PII in payload** — user identity is `user_id` column when authenticated
