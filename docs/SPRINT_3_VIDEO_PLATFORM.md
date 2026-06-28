# Sprint 3 — Video Platform

**Status:** Planned (split into 3.1–3.4)  
**Phase:** 3 — Video Platform  
**Depends on:** Sprint 2.3 (Feed Foundation — complete)

## Summary

Sprint 3 delivers the full video platform: upload, processing pipeline, social interactions, and rule-based recommendations. The feed **read path** (For You / Following, cursor pagination) shipped in **Sprint 2.3**; Sprint 3 adds write path, media processing, engagement, and ranking.

Roadmap detail: [13_ROADMAP.md](../13_ROADMAP.md#sprint-3--video-platform)

| Sub-sprint | Focus | Blueprint | Status |
|------------|-------|-----------|--------|
| **3.1** | Video Upload Foundation | [SPRINT_3.1_BLUEPRINT.md](./SPRINT_3.1_BLUEPRINT.md) | Blueprint — pending approval |
| **3.2** | Video Processing | [SPRINT_3.2_BLUEPRINT.md](./SPRINT_3.2_BLUEPRINT.md) | Blueprint — pending approval |
| **3.3** | Video Interactions | [SPRINT_3.3_BLUEPRINT.md](./SPRINT_3.3_BLUEPRINT.md) | Blueprint — pending approval |
| **3.4** | Recommendation Engine v1 | [SPRINT_3.4_BLUEPRINT.md](./SPRINT_3.4_BLUEPRINT.md) | Blueprint — pending approval |

> **Implementation gate:** No Sprint 3 code is written until the sub-sprint blueprint is reviewed and marked **Approved** in §10 of the blueprint document.

---

## Storage abstraction

**Rule:** Controllers and jobs never call `Storage::` directly. All object I/O goes through `StorageService`.

```
StorageService (App\Contracts\Services\StorageServiceInterface)
    │
    ├── LocalStorageDriver        — PHPUnit, local dev without MinIO
    ├── S3StorageDriver           — AWS S3, Cloudflare R2 (production)
    │       └── MinIO             — S3-compatible (docker-compose)
    └── CdnStorageDriver (future) — signed CDN URLs, cache purge
```

`MediaService` owns upload path conventions and presigned URL generation; it delegates bytes to `StorageService`.

### Config mapping

| Environment | Driver | Endpoint |
|-------------|--------|----------|
| Docker dev | S3 → MinIO | `http://minio:9000` |
| CI tests | Local | `storage/app` |
| Production | S3 → R2/S3 | Provider endpoint + CDN domain |

---

## Video processing pipeline

Each confirmed upload enters a **queued pipeline**. Early sub-sprints may ship stubs; interfaces and job order are fixed from 3.1.

```
POST /videos/{id}/confirm-upload
        │
        ▼
┌─────────────────┐
│ VirusScanJob    │  stub → ClamAV / vendor (Sprint 15)
└────────┬────────┘
         ▼
┌─────────────────┐
│ ExtractMetadata │  duration, width, height, codec (ffprobe)
└────────┬────────┘
         ▼
┌─────────────────┐
│ GenerateThumb   │  stub → FFmpeg frame (Sprint 3.2)
└────────┬────────┘
         ▼
┌─────────────────┐
│ TranscodeVideo  │  stub → HLS 720p/480p (Sprint 3.2)
└────────┬────────┘
         ▼
┌─────────────────┐
│ ModerateContent │  stub → rules; ML in Sprint 9
└────────┬────────┘
         ▼
┌─────────────────┐
│ PublishVideo    │  status=published, video_url=HLS manifest
└─────────────────┘
```

### Status transitions

| Status | Meaning |
|--------|---------|
| `uploading` | Presigned URL issued, awaiting client PUT |
| `processing` | Pipeline running |
| `published` | Visible in feeds |
| `failed` | Pipeline error (retriable log) |
| `rejected` | Moderation rejected |

---

## Engagement metrics

Start collecting **before** user scale. `MetricsService` is the only entry point; no direct inserts from controllers.

| Event | When | Sprint |
|-------|------|--------|
| `feed_open` | User opens feed tab | 3.1 |
| `video_impression` | Video visible ≥ threshold in viewport | 3.1 |
| `watch_time` | Playback heartbeat (e.g. every 5 s) | 3.3 |
| `completion` | Watched ≥ 90% of duration | 3.3 |
| `skip` | Swiped away before 3 s | 3.3 |
| `like` | Like toggled | 3.3 |
| `comment` | Comment created | 3.3 |
| `share` | Share action | 3.3 |

Storage: append-only `video_events` (or partitioned table); aggregate to Redis/rollups for trending (3.4).

---

## Sub-sprint scope

### 3.1 — Video Upload Foundation

- `StorageService` + drivers (Local, S3/MinIO)
- `POST /videos`, `POST /videos/{id}/confirm-upload`, `POST /media/presigned-url`
- `media_uploads` migration
- Pipeline skeleton (stub jobs)
- `MetricsService` + `feed_open`, `video_impression`

### 3.2 — Video Processing

- FFmpeg thumbnail + HLS transcode
- Real metadata extraction
- Queue retries, `failed` status, error log
- HLS playback in mobile feed

### 3.3 — Video Interactions

- Likes, comments, bookmarks, shares (reposts), views
- `VideoResource` — real `is_liked`, `is_bookmarked`
- Watch-time / completion / skip metrics
- Mobile: double-tap like, comments sheet, share

### 3.4 — Recommendation Engine v1 (no AI)

- Rule-based `RecommendationService`
- Feeds: **Trending**, **Popular**, **New**, enhanced **For You**
- **Following** unchanged (Sprint 2.3)
- Redis cache for hot lists
- Sprint 9 replaces ranking implementation, not API contracts

---

## Architecture rules

1. No business logic in controllers — `VideoUploadService`, `VideoInteractionService`, `RecommendationService`
2. No `Storage::` in controllers — `StorageService` only
3. Pipeline stages are discrete queued jobs (testable, retryable)
4. Metrics via `MetricsService` — event name enum, typed payload DTO
5. Module contracts in `app/Contracts/Services/`

---

## Dependency chain

```
Sprint 2.3 (feed read)
    ↓
Sprint 3.1 (upload + storage + metrics foundation)
    ↓
Sprint 3.2 (FFmpeg + publish)
    ↓
Sprint 3.3 (interactions + watch metrics)
    ↓
Sprint 3.4 (trending / popular / new / for-you rules)
    ↓
Sprint 4 (commerce — product-video tagging)
```

Sprint 9 (AI) replaces `RecommendationService` internals and enhances `ModerateContentJob`; Sprint 15 hardens FFmpeg and virus scan for production scale.
