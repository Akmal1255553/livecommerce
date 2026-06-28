# Sprint 3.1 — Video Upload Foundation · Blueprint

**Status:** Approved (2026-06-28)  
**Implementation:** Sprint 3.1 backend complete  
**Phase:** 3 — Video Platform  
**Depends on:** Sprint 2.3 (Feed Foundation — complete)  
**Part of:** [Sprint 3 overview](./SPRINT_3_VIDEO_PLATFORM.md)

> **Gate rule:** No Sprint 3.1 code may be merged until this blueprint is reviewed and marked **Approved**.

---

## 1. Goal

Deliver the **video write path foundation**: storage abstraction (`StorageService`), presigned upload to MinIO/S3, upload session tracking, pipeline orchestration skeleton, and early engagement metrics — without FFmpeg transcoding or social interactions.

**In scope:** upload initiation, direct-to-object-storage PUT, confirm-upload, queued pipeline stubs, `feed_open` / `video_impression` metrics.

**Out of scope (later sub-sprints):** HLS transcoding (3.2), likes/comments (3.3), trending feeds (3.4), product tagging on videos (Sprint 4).

---

## 2. API

All paths under `/api/v1`. JSON envelope per [API Specification](./04_API_SPECIFICATION.md).

### 2.1 Endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/videos` | Required | Create video record + presigned upload URL |
| POST | `/videos/{id}/confirm-upload` | Required | Confirm client finished PUT; enqueue pipeline |
| GET | `/videos/{id}` | Optional | Video detail (owner sees `uploading`/`processing`) |
| POST | `/media/presigned-url` | Required | Generic presigned URL (avatar, etc.; existing contract) |
| POST | `/metrics/events` | Optional | Batch engagement events (see §2.4) |

**Not in 3.1:** `PUT /videos/{id}`, `DELETE /videos/{id}`, like/comment/bookmark, feed changes.

### 2.2 POST `/videos`

**Request:**
```json
{
  "title": "My new video",
  "description": "Optional description",
  "visibility": "public",
  "mime_type": "video/mp4",
  "file_size": 52428800
}
```

| Field | Rules |
|-------|-------|
| `title` | Optional, max 255 |
| `description` | Optional, max 2000 |
| `visibility` | `public`, `followers`, `private` — default `public` |
| `mime_type` | Required; allow `video/mp4`, `video/quicktime`, `video/webm` |
| `file_size` | Required; max 104_857_600 (100 MB MVP); max duration enforced post-upload in 3.2 |

**Response: 201 Created**
```json
{
  "success": true,
  "data": {
    "video": { "VideoResource": "status=uploading" },
    "upload_url": "https://minio:9000/bucket/videos/{uuid}/raw.mp4?...",
    "upload_method": "PUT",
    "upload_headers": { "Content-Type": "video/mp4" },
    "expires_at": "2026-06-28T12:15:00Z"
  }
}
```

**Errors:** 422 validation, 403 if user suspended, 429 upload rate limit (10/hour/user MVP).

### 2.3 POST `/videos/{id}/confirm-upload`

**Request:**
```json
{
  "checksum": "sha256:abc123..."
}
```

**Response: 202 Accepted**
```json
{
  "success": true,
  "data": {
    "video": { "VideoResource": "status=processing" },
    "message": "Upload confirmed. Processing started."
  }
}
```

**Business rules:**
- Caller must own the video
- Video must be `status=uploading`
- `media_uploads.status` must transition `pending` → `uploaded`
- Object must exist at `storage_path` (HEAD via `StorageService`)
- Dispatches `VideoUploadConfirmed` → pipeline job chain

**Errors:** 404 not found, 403 not owner, 409 wrong status, 422 object missing in storage.

### 2.4 POST `/metrics/events`

Fire-and-forget batch; returns 202.

**Request:**
```json
{
  "events": [
    {
      "type": "feed_open",
      "session_id": "uuid",
      "payload": { "tab": "for_you" }
    },
    {
      "type": "video_impression",
      "session_id": "uuid",
      "video_id": "uuid",
      "payload": { "position": 0, "visible_ms": 500 }
    }
  ]
}
```

| Field | Rules |
|-------|-------|
| `events` | 1–20 items per request |
| `type` | `feed_open`, `video_impression` only in 3.1 |
| `session_id` | Required; client-generated UUID per app session |
| `video_id` | Required for `video_impression` |

**Response: 202 Accepted** — `{ "success": true, "data": { "accepted": 2 } }`

---

## 3. Database schema

### 3.1 New tables

#### `media_uploads`

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | UUID | PK | |
| user_id | UUID | FK → users, NOT NULL | |
| entity_type | VARCHAR(50) | NOT NULL | `video` |
| entity_id | UUID | NOT NULL | videos.id |
| file_name | VARCHAR(255) | NOT NULL | |
| mime_type | VARCHAR(100) | NOT NULL | |
| file_size | BIGINT | NOT NULL | Expected bytes |
| storage_path | VARCHAR(500) | NOT NULL | S3 key |
| checksum | VARCHAR(128) | NULLABLE | sha256 after confirm |
| status | VARCHAR(20) | NOT NULL, DEFAULT `pending` | pending, uploaded, processing, completed, failed |
| created_at | TIMESTAMP | NOT NULL | |
| updated_at | TIMESTAMP | NOT NULL | |

**Indexes:** `(status, created_at)`, `(entity_type, entity_id)`.

#### `video_processing_steps`

Pipeline audit log (one row per step per run).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGSERIAL | PK | |
| video_id | UUID | FK → videos, NOT NULL | |
| step | VARCHAR(50) | NOT NULL | virus_scan, metadata, thumbnail, transcode, moderation, publish |
| status | VARCHAR(20) | NOT NULL | pending, running, completed, failed, skipped |
| attempt | SMALLINT | NOT NULL, DEFAULT 1 | |
| error_message | TEXT | NULLABLE | |
| started_at | TIMESTAMP | NULLABLE | |
| completed_at | TIMESTAMP | NULLABLE | |
| created_at | TIMESTAMP | NOT NULL | |

**Indexes:** `(video_id, step)`, `(status, created_at)`.

#### `engagement_events`

Append-only metrics store.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGSERIAL | PK | |
| event_type | VARCHAR(50) | NOT NULL | feed_open, video_impression, … |
| user_id | UUID | FK → users, NULLABLE | Null for anonymous |
| video_id | UUID | FK → videos, NULLABLE | |
| session_id | UUID | NOT NULL | Client session |
| payload | JSONB | NOT NULL, DEFAULT `{}` | Typed per event |
| created_at | TIMESTAMP | NOT NULL | |

**Indexes:** `(event_type, created_at DESC)`, `(video_id, event_type, created_at DESC)`, `(user_id, created_at DESC)`.

### 3.2 Alter `videos` (migration)

Add columns if not present:

| Column | Type | Description |
|--------|------|-------------|
| processing_started_at | TIMESTAMP | NULLABLE |
| processing_completed_at | TIMESTAMP | NULLABLE |
| published_at | TIMESTAMP | NULLABLE |

Existing columns from Sprint 2.3 remain unchanged.

### 3.3 Storage path convention

```
videos/{video_id}/raw.{ext}           — original upload
videos/{video_id}/thumb.jpg           — 3.2
videos/{video_id}/hls/master.m3u8     — 3.2
```

---

## 4. Events

| Event | Trigger | Payload | Listeners / Jobs |
|-------|---------|---------|------------------|
| `VideoCreated` | POST `/videos` success | `videoId`, `userId` | — (audit log optional) |
| `VideoUploadConfirmed` | POST confirm-upload success | `videoId`, `userId`, `mediaUploadId` | `DispatchVideoProcessingPipeline` |

**Naming:** past tense, matches [Engineering Rules](./06_ENGINEERING_RULES.md).

**Not in 3.1:** `VideoProcessed`, `VideoPublished` (3.2), `VideoLiked` (3.3).

---

## 5. Queues

**Queue name:** `video-processing` (Redis).

### 5.1 Orchestrator

| Job | Description |
|-----|-------------|
| `ProcessVideoPipelineJob` | Loads video; runs step chain in order; updates `video_processing_steps` |

### 5.2 Step jobs (3.1 = stubs)

| Job | 3.1 behaviour | Real impl |
|-----|---------------|-----------|
| `VirusScanJob` | Log + mark completed | ClamAV / vendor (Sprint 15) |
| `ExtractMetadataJob` | Set placeholder duration=0 | ffprobe (3.2) |
| `GenerateThumbnailJob` | Skip (status=skipped) | FFmpeg (3.2) |
| `TranscodeVideoJob` | Skip (status=skipped) | FFmpeg HLS (3.2) |
| `ModerateContentJob` | Auto-pass | Rules / ML (9/14) |
| `PublishVideoJob` | **Do not publish** — leave `status=processing` | Sets `published` (3.2) |

**Retry policy:** 3 attempts, exponential backoff (30s, 120s, 600s). On final failure: `videos.status=failed`, step row `failed`.

**Idempotency:** Each step checks latest `video_processing_steps` — skip if already `completed` for same `attempt`.

---

## 6. Services & architecture

| Contract | Implementation | Responsibility |
|----------|----------------|----------------|
| `StorageServiceInterface` | `StorageService` | Presigned URL, exists, delete — **no `Storage::` in controllers** |
| `StorageDriverInterface` | `LocalStorageDriver`, `S3StorageDriver` | Driver swap via config |
| `MediaServiceInterface` | `MediaService` | Path conventions, delegates to StorageService |
| `VideoUploadServiceInterface` | `VideoUploadService` | create, confirm, ownership checks |
| `MetricsServiceInterface` | `MetricsService` | validate + persist engagement_events |

**Controllers:** `VideoController`, `MediaController`, `MetricsController` — thin; call services only.

---

## 7. Test cases

File: `tests/Feature/Video/VideoUploadTest.php`

| # | Test | Assert |
|---|------|--------|
| 1 | Authenticated user can create video upload session | 201, video `uploading`, presigned URL returned |
| 2 | Unauthenticated POST `/videos` | 401 |
| 3 | POST `/videos` validates mime_type and file_size | 422 |
| 4 | POST `/videos` rejects file_size over limit | 422 |
| 5 | Owner can confirm upload when object exists | 202, status `processing`, pipeline dispatched |
| 6 | Confirm upload fails if not owner | 403 |
| 7 | Confirm upload fails if status not `uploading` | 409 |
| 8 | Confirm upload fails if object missing in storage | 422 |
| 9 | Pipeline stub jobs create `video_processing_steps` rows | DB rows for each step |
| 10 | After pipeline stubs video stays `processing` (not published) | status unchanged |
| 11 | StorageService used — no direct Storage facade in VideoController | architecture smoke (optional static or integration) |
| 12 | POST `/metrics/events` accepts feed_open | 202, row in engagement_events |
| 13 | POST `/metrics/events` accepts video_impression with video_id | 202, row persisted |
| 14 | Metrics rejects unknown event type in 3.1 | 422 |
| 15 | GET `/videos/{id}` returns uploading/processing video for owner | 200 |

File: `tests/Unit/Services/StorageServiceTest.php` — presigned URL generation, local driver round-trip.

**Target:** 15+ feature tests, 80%+ service coverage for new services.

---

## 8. Acceptance criteria

- [ ] Blueprint reviewed and marked **Approved**
- [x] `StorageServiceInterface` + Local + S3/MinIO drivers; zero `Storage::` in controllers
- [x] `media_uploads`, `video_processing_steps`, `engagement_events` migrations applied
- [x] POST `/videos` returns presigned URL; client uploads direct to storage
- [x] POST `/videos/{id}/confirm-upload` enqueues pipeline; video → `processing`
- [x] Pipeline stub jobs run in order with audit rows; video **not** published until 3.2
- [x] POST `/metrics/events` records `feed_open` and `video_impression`
- [x] All test cases in §7 passing in Docker
- [ ] Mobile upload flow (deferred to 3.2 with HLS playback)

---

## 9. Mobile (reference)

- Select/record video → metadata form → POST `/videos` → PUT to presigned URL → POST confirm
- Poll GET `/videos/{id}` for status or listen for push (push content in 3.2)
- Emit `feed_open` / `video_impression` via `/metrics/events` from existing FeedScreen

---

## 10. Approval

| Role | Name | Date | Status |
|------|------|------|--------|
| CTO / Founder | — | 2026-06-28 | Approved |
| Backend lead | — | 2026-06-28 | Approved |

**Approved** unlocks implementation branch `feature/sprint-3.1-upload`.
