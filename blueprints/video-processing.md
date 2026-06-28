# Video Processing · Blueprint

**Version:** 2  
**Sprint:** 3.2  
**Status:** Approved (2026-06-28)  
**Phase:** 3 — Video Platform  
**Depends on:** Sprint 3.1 — Video Upload Foundation ([release v0.3.1](https://github.com/Akmal1255553/livecommerce/releases/tag/v0.3.1-video-upload-foundation))  
**Overview:** [docs/SPRINT_3_VIDEO_PLATFORM.md](../docs/SPRINT_3_VIDEO_PLATFORM.md)

> **Gate rule:** No Sprint 3.2 code may be merged until this blueprint is marked **Approved** — satisfied 2026-06-28 (v2 architecture adjustments approved same date).

---

## 1. Goal

Replace pipeline **stubs** with a **state-driven, DAG-orchestrated** processing pipeline: metadata extraction (ffprobe), validation, thumbnail generation, HLS transcoding (FFmpeg), moderation stub, and **publish** to feeds. Enable HLS playback in the mobile feed.

**Architectural pillars (v2):**

1. **Video State Machine** — coarse lifecycle of the `Video` entity, separate from internal processing steps.
2. **DAG Orchestrator** — parallel stages for independent steps; sequential gates where dependencies exist.
3. **Step lifecycle** — each processing step has its own status (`pending` → `running` → `completed` / `failed` / `skipped`, with `retrying`).
4. **MediaAsset** — all derivative files stored and referenced independently of `Video` columns.
5. **Idempotency** — every step safe to re-run; partial pipeline retry without full re-upload.

**In scope (3.2 MVP):** FFmpeg/ffprobe, `ValidateVideoStep`, DAG orchestrator, `media_assets` table, HLS **720p + 480p**, real `PublishVideoStep`, failed/retry semantics, HLS via `StorageService`, mobile `video_player`.

**Phased (3.2b):** HLS **360p** and **1080p** renditions — add only when profiling shows clear need; orchestrator and `MediaAsset` types must accept them without schema redesign.

**Out of scope:** likes/comments (3.3), trending ranking (3.4), CDN edge caching (Sprint 15), real virus scanner (Sprint 15), AI moderation (Sprint 9).

---

## 2. Video State Machine

The **Video state machine** governs user-visible lifecycle. It sits **above** the DAG pipeline and must not be conflated with `video_processing_steps.status`.

### 2.1 States

| State | Meaning | Entered when |
|-------|---------|--------------|
| `uploading` | Presigned URL issued; client PUT in progress | `POST /videos` (3.1) |
| `uploaded` | Object confirmed in storage; not yet dequeued | `POST /videos/{id}/confirm-upload` success |
| `queued` | `ProcessVideoPipelineJob` dispatched; waiting for worker | Job pushed to `video-processing` queue |
| `processing` | DAG orchestrator actively running steps | Worker starts pipeline job |
| `published` | All steps complete; visible in feeds | `PublishVideoStep` success |
| `failed` | Unrecoverable pipeline error | Any step exhausts retries or hard-fail |
| `rejected` | Moderation rejected | `ModerateContentStep` reject (future AI) |
| `hidden` | Owner/admin hide | Manual (unchanged from 3.1) |

### 2.2 Transitions

```
uploading ──confirm-upload──► uploaded ──enqueue──► queued ──worker pick──► processing
                                                                                  │
                                    ┌─────────────────────────────────────────────┤
                                    ▼                                             ▼
                              published                                      failed
                                    │
                              (moderation reject)
                                    ▼
                               rejected
```

**Rules:**

- Only `VideoStateMachine` (service) may transition `videos.status`. Steps emit events; the state machine applies transitions.
- `uploading` → `uploaded`: set on confirm-upload (replaces 3.1 behaviour of jumping directly to `processing`).
- `uploaded` → `queued`: listener after `VideoUploadConfirmed` dispatches job and transitions.
- `queued` → `processing`: first line of `ProcessVideoPipelineJob` / orchestrator entry.
- `processing` → `published` | `failed` | `rejected`: terminal transitions from publish / fail / moderation steps.
- API `VideoResource` exposes `status` only — never individual step names to public clients.

### 2.3 Migration from 3.1

| 3.1 behaviour | 3.2 behaviour |
|---------------|---------------|
| confirm-upload → `processing` | confirm-upload → `uploaded` → `queued` → `processing` |
| `video_url` / `thumbnail_url` on `videos` | Source of truth in `media_assets`; denormalized URLs copied to `videos` on publish for API compat |
| Linear `VideoProcessingPipelineRunner` | `VideoProcessingPipelineOrchestrator` (DAG) |

Add enum cases: `VideoStatus::Uploaded`, `VideoStatus::Queued`.

---

## 3. DAG Pipeline

Each confirmed upload enters the **`video-processing`** queue. The **orchestrator** runs a directed acyclic graph of steps — not a single linear `foreach`.

### 3.1 Flow diagram

```
VideoUploadConfirmed
        │
        ▼
┌───────────────────┐
│  video-processing │  queue
│       queue       │
└─────────┬─────────┘
          │  Video status: queued → processing
          ▼
┌─────────────────────────────────────────────────────────┐
│ Stage 1 — parallel (Bus::batch)                         │
│   VirusScanStep │ ExtractMetadataStep │ ValidateVideoStep │
└─────────────────────────┬───────────────────────────────┘
                          │ gate: all Stage 1 completed
                          ▼
              GenerateThumbnailStep
                          │
                          ▼
┌─────────────────────────────────────────────────────────┐
│ Stage 3 — parallel (Bus::batch) — MVP                   │
│        TranscodeHls720Step │ TranscodeHls480Step        │
└─────────────────────────┬───────────────────────────────┘
                          │ gate: all renditions completed
                          ▼
              ModerateContentStep  (stub: auto-approve)
                          │
              ┌───────────┴───────────┐
              ▼                       ▼
      PublishVideoStep            rejected
              │
              ▼
         published
```

**Stage 3.2b (phased):** add `TranscodeHls360Step`, `TranscodeHls1080Step` to Stage 3 batch; update HLS master `MediaAsset` — no orchestrator rewrite.

### 3.2 Step registry

| Step | Stage | Depends on | Output `MediaAsset` type(s) |
|------|-------|------------|----------------------------|
| `VirusScanStep` | 1 | raw upload | — (pass/fail only) |
| `ExtractMetadataStep` | 1 | raw upload | — (writes `videos.duration`, `width`, `height`, `codec`) |
| `ValidateVideoStep` | 1 | metadata | — (pass/fail; sets `failure_code` on fail) |
| `GenerateThumbnailStep` | 2 | Stage 1 | `thumbnail` |
| `TranscodeHls720Step` | 3 | Stage 2 | `hls_720p`, contributes to `hls_master` |
| `TranscodeHls480Step` | 3 | Stage 2 | `hls_480p`, contributes to `hls_master` |
| `ModerateContentStep` | 4 | Stage 3 | — |
| `PublishVideoStep` | 5 | Stage 4 | finalizes `hls_master`; denormalizes URLs to `videos` |

Add enum cases: `VideoProcessingStepName::Validate`, `TranscodeHls720`, `TranscodeHls480` (replace monolithic `Transcode`).

### 3.3 Step lifecycle

Each row in `video_processing_steps` tracks **step-local** status — independent of `videos.status`.

| Status | Meaning |
|--------|---------|
| `pending` | Registered for video; not yet started |
| `running` | Step `execute()` in progress |
| `completed` | Success; outputs persisted |
| `failed` | Error; may transition to `retrying` or fail video |
| `retrying` | Re-queued after failure; `attempt` incremented |
| `skipped` | Not required (e.g. phased rendition disabled) |

**Rules:**

- Orchestrator pre-seeds all steps as `pending` when pipeline starts (or on first stage entry).
- `AbstractProcessingStep` transitions: `pending`/`retrying` → `running` → `completed` | `failed`.
- On retry: failed step → `retrying`; downstream steps reset to `pending` if outputs invalidated.
- Completed/skipped steps are **never re-run** unless explicitly reset via `RetryVideoProcessingStepJob`.

Add enum case: `VideoProcessingStepStatus::Retrying`.

---

## 4. MediaAsset abstraction

Processed files must **not** be owned implicitly by `Video` columns. `MediaAsset` is the canonical record for every stored object.

### 4.1 Model

**Table:** `media_assets`

| Column | Type | Description |
|--------|------|-------------|
| id | UUID | PK |
| video_id | UUID | FK → `videos` |
| type | VARCHAR(30) | Asset type enum |
| storage_path | VARCHAR(500) | Key in object storage |
| mime_type | VARCHAR(100) | e.g. `image/jpeg`, `application/vnd.apple.mpegurl` |
| byte_size | BIGINT | NULLABLE |
| width | INTEGER | NULLABLE |
| height | INTEGER | NULLABLE |
| checksum | VARCHAR(64) | NULLABLE — SHA-256 |
| metadata | JSONB | NULLABLE — rendition bitrate, segment count, etc. |
| created_at | TIMESTAMP | |

**Indexes:** `(video_id, type)` unique where type is singular per video (thumbnail, hls_master); `(video_id)` for listing.

### 4.2 Asset types

| Type | MVP | Description |
|------|-----|-------------|
| `raw` | 3.1 | Source upload (`videos/{id}/raw.mp4`) |
| `thumbnail` | 3.2 | JPEG preview |
| `hls_master` | 3.2 | Master playlist (`master.m3u8`) |
| `hls_720p` | 3.2 | 720p variant playlist + segments prefix |
| `hls_480p` | 3.2 | 480p variant playlist + segments prefix |
| `hls_360p` | 3.2b | Low-bandwidth rendition |
| `hls_1080p` | 3.2b | High-quality rendition |

### 4.3 MediaAssetService

| Method | Responsibility |
|--------|----------------|
| `register(Video, type, path, meta)` | Upsert asset row; idempotent on `(video_id, type)` |
| `getPrimary(Video, type)` | Single asset by type |
| `listForVideo(Video)` | All assets for admin/debug |
| `publicUrl(MediaAsset)` | Delegate to `StorageService::publicUrl($path)` |
| `resolvePlaybackUrl(Video)` | `hls_master` public URL for API |

**Publish flow:** `PublishVideoStep` reads `MediaAsset` rows → sets `videos.video_url` and `videos.thumbnail_url` (denormalized cache for feed performance). Re-transcode updates assets first; publish refreshes denormalized columns.

**Rule:** Processing steps write **only** via `MediaAssetService` + `StorageService` — never set `videos.video_url` directly except in `PublishVideoStep`.

---

## 5. Idempotency requirements

Every processing step **must** be safe to run more than once without duplicate side effects.

### 5.1 Global rules

| Rule | Requirement |
|------|-------------|
| Skip if done | If step status is `completed` or `skipped`, `run()` returns immediately |
| Stable storage keys | Same `(video_id, type)` → same object key; overwrite on re-run |
| Asset upsert | `MediaAssetService::register()` upserts — no duplicate rows |
| Temp cleanup | `StorageService::downloadToTemp()` files deleted in `finally` |
| Stage gates | Next stage starts only when all upstream steps `completed` or `skipped` |
| Job uniqueness | `ProcessVideoPipelineJob` keyed by `videoId`; no concurrent pipelines for same video |

### 5.2 Per-step idempotency

| Step | Idempotency strategy |
|------|---------------------|
| `VirusScanStep` | Hash logged once; pass/fail idempotent |
| `ExtractMetadataStep` | Re-read ffprobe; overwrite `videos` metadata columns |
| `ValidateVideoStep` | Re-evaluate rules; same result on same input |
| `GenerateThumbnailStep` | Overwrite `thumb.jpg`; upsert `thumbnail` MediaAsset |
| `TranscodeHls720Step` | Overwrite segment dir; upsert `hls_720p` asset |
| `TranscodeHls480Step` | Overwrite segment dir; upsert `hls_480p` asset |
| `ModerateContentStep` | Stub always approves; idempotent decision |
| `PublishVideoStep` | Rebuild master.m3u8 from existing assets; idempotent publish |

### 5.3 Partial retry

`RetryVideoProcessingStepJob(videoId, stepName)`:

1. Reset target step → `pending` (clear `error_message`).
2. Reset downstream steps → `pending` if their inputs depend on target output.
3. Re-enter orchestrator at failed stage — **do not** re-upload raw file.

Manual retry via admin endpoint — Sprint 14.

---

## 6. API

### 6.1 Endpoints (changes from 3.1)

| Method | Path | Auth | Change |
|--------|------|------|--------|
| GET | `/videos/{id}` | Optional | Published: `video_url`, `thumbnail_url`, `duration`; owner sees `uploaded`/`queued`/`processing`/`failed` |
| GET | `/feed/for-you` | Optional | Includes **published** videos only |
| GET | `/feed/following` | Required | Same |

**No new public endpoints.** Processing is queue-driven (internal).

### 6.2 VideoResource (published)

```json
{
  "id": "uuid",
  "status": "published",
  "video_url": "https://cdn.example.com/videos/{id}/hls/master.m3u8",
  "thumbnail_url": "https://cdn.example.com/videos/{id}/thumb.jpg",
  "duration": 45,
  "width": 1080,
  "height": 1920
}
```

URLs resolved from `MediaAsset` at publish time; served from denormalized `videos` columns.

### 6.3 Visibility

| status | Visible in feed | GET `/videos/{id}` |
|--------|-----------------|---------------------|
| uploading | No | Owner only |
| uploaded | No | Owner only |
| queued | No | Owner only |
| processing | No | Owner only |
| published | Yes | Public per visibility |
| failed | No | Owner; `failure_code` in meta |
| rejected | No | Owner |

---

## 7. Database schema

### 7.1 Alter `videos`

| Column | Type | Description |
|--------|------|-------------|
| codec | VARCHAR(50) | NULLABLE — from ffprobe |
| bitrate | INTEGER | NULLABLE — kbps |
| failure_code | VARCHAR(50) | NULLABLE — `transcode_error`, `duration_exceeded`, `validation_failed`, … |
| failure_message | TEXT | NULLABLE | Internal detail |

**Status enum:** add `uploaded`, `queued` (see §2).

`video_url`, `thumbnail_url` remain — denormalized publish cache (see §4.3).

### 7.2 Create `media_assets`

See §4.1.

### 7.3 Alter `media_uploads`

| Column | Type | Description |
|--------|------|-------------|
| processed_at | TIMESTAMP | NULLABLE — set when pipeline completes |

### 7.4 `video_processing_steps`

| Change | Description |
|--------|-------------|
| Pre-seed rows | Orchestrator creates all step rows as `pending` at pipeline start |
| Status enum | Add `retrying` |
| Step names | Add `validate`, `transcode_hls_720`, `transcode_hls_480` |

### 7.5 Storage paths (via StorageService)

| Path | MediaAsset type |
|------|-----------------|
| `videos/{id}/raw.mp4` | `raw` (3.1) |
| `videos/{id}/thumb.jpg` | `thumbnail` |
| `videos/{id}/hls/master.m3u8` | `hls_master` |
| `videos/{id}/hls/720p/` | `hls_720p` |
| `videos/{id}/hls/480p/` | `hls_480p` |
| `videos/{id}/hls/360p/` | `hls_360p` (3.2b) |
| `videos/{id}/hls/1080p/` | `hls_1080p` (3.2b) |

**MVP transcode profile:** max duration 60 s; **720p + 480p** HLS; AAC audio.

---

## 8. Events

| Event | Trigger | Payload | Listeners |
|-------|---------|---------|-----------|
| `VideoUploadConfirmed` | confirm-upload (3.1) | `videoId`, `userId` | Enqueue pipeline; `uploaded` → `queued` |
| `VideoProcessingStarted` | worker picks job | `videoId` | `queued` → `processing` |
| `VideoMetadataExtracted` | ExtractMetadata success | `videoId`, `duration`, `width`, `height` | — |
| `VideoValidated` | ValidateVideo success | `videoId` | — |
| `VideoTranscoded` | Stage 3 batch success | `videoId`, `manifestPath` | — |
| `VideoPublished` | PublishVideo success | `videoId`, `userId` | Optional follower notify stub |
| `VideoProcessingFailed` | Step final failure | `videoId`, `step`, `code` | State machine → `failed`; log + notify stub |

---

## 9. Queues & retry

**Queue:** `video-processing` (same as 3.1).

### 9.1 Timeouts

| Step | Timeout |
|------|---------|
| VirusScan | 60 s |
| ExtractMetadata | 120 s |
| ValidateVideo | 30 s |
| GenerateThumbnail | 120 s |
| TranscodeHls720 | 600 s |
| TranscodeHls480 | 600 s |
| ModerateContent | 30 s |
| PublishVideo | 30 s |

### 9.2 VirusScan (3.2)

Stub++: log file hash via `StorageService`, always pass. Real ClamAV/vendor in Sprint 15.

### 9.3 ValidateVideo

| Rule | Action |
|------|--------|
| `duration > 60` | `failure_code=duration_exceeded` |
| Unsupported codec | `failure_code=unsupported_codec` |
| Corrupt / unreadable | `failure_code=validation_failed` |

Runs in parallel with metadata extraction; validation reads metadata columns written by `ExtractMetadataStep` — orchestrator must ensure metadata step completes before validate reads (same batch: validate job runs after metadata job in batch, or validate re-invokes ffprobe if metadata not yet written).

**Recommended:** Stage 1 batch runs VirusScan + ExtractMetadata in parallel; **ValidateVideo runs in gate callback** after metadata is persisted (sequential within stage, parallel with virus scan).

### 9.4 Transcode steps

- Download raw via `StorageService::downloadToTemp()`
- FFmpeg via `FfmpegTranscoderInterface` → HLS segments
- Upload via `StorageService::put()`
- Register `MediaAsset` per rendition
- `PublishVideoStep` assembles `master.m3u8` from completed rendition assets

### 9.5 Retry policy

| Setting | Value |
|---------|-------|
| Max attempts | 3 per step job |
| Backoff | 30 s, 120 s, 600 s |
| Failure audit | `video_processing_steps.error_message` + `videos.failure_*` |
| Step retry state | `failed` → `retrying` on re-dispatch |
| Manual retry | `RetryVideoProcessingStepJob` — Sprint 14 admin UI |

---

## 10. Services & infrastructure

| Component | Responsibility |
|-----------|----------------|
| `VideoStateMachine` | Sole authority for `videos.status` transitions |
| `VideoProcessingPipelineOrchestrator` | DAG stages, batch gates, step dispatch |
| `MediaAssetService` | Register/query derivative files |
| `FfmpegTranscoderInterface` | ffprobe + HLS transcode (swappable implementation) |
| `StorageService` | `publicUrl()`, `downloadToTemp()`, `put()` — no `Storage::` facade |
| `VideoProcessingPipelineRunner` | **Deprecated** — replace with orchestrator in 3.2 |

**Docker `app` image:** must include `ffmpeg` and `ffprobe`.

**CDN:** production CDN domain via `StorageService::publicUrl()` config (`AWS_URL` / dedicated CDN config) — pipeline unchanged when CDN is attached (Sprint 15 edge cache is separate).

**AI moderation (Sprint 9):** replace `ModerateContentStep` internals; orchestrator and state machine unchanged.

---

## 11. Test cases

File: `tests/Feature/Video/VideoProcessingTest.php`

| # | Test | Assert |
|---|------|--------|
| 1 | confirm-upload → status `uploaded`, then `queued`, then `processing` | state transitions |
| 2 | Full pipeline → `published` with HLS URL | `video_url` set |
| 3 | Published video in GET `/feed/for-you` | video id present |
| 4 | Thumbnail MediaAsset + URL populated | `media_assets` row + `thumbnail_url` |
| 5 | Duration/dimensions from ffprobe | `duration > 0`, width/height set |
| 6 | All MVP steps `completed` (not skipped) | step rows |
| 7 | Stage 1 parallel completion before thumbnail | thumbnail starts after metadata + validate |
| 8 | Transcode failure → `failed` | `failure_code=transcode_error` |
| 9 | Duration > 60 s → `duration_exceeded` | status `failed` |
| 10 | VideoPublished event dispatched | `Event::fake` |
| 11 | Idempotent re-run skips completed steps | no duplicate MediaAsset rows |
| 12 | Retry single step re-runs downstream only | partial retry |
| 13 | Non-owner cannot see other's failed video | 404 |
| 14 | Owner can GET failed video with `failure_code` | 200 |

File: `tests/Unit/Services/FfmpegTranscoderTest.php`

File: `tests/Unit/Services/MediaAssetServiceTest.php`

**Fixture:** `tests/fixtures/sample.mp4` (< 1 MB, ~3 s) committed for CI.

---

## 12. Acceptance criteria

- [x] Blueprint v2 reviewed and marked **Approved**
- [ ] `VideoStateMachine` enforces transitions; no direct `status` writes in steps
- [ ] `media_assets` table + `MediaAssetService` in use
- [ ] DAG orchestrator with Stage 1 parallel + ValidateVideo gate
- [ ] FFmpeg available in Docker app container
- [ ] HLS 720p + 480p + thumbnail; video `status=published`
- [ ] Published videos in For You / Following feeds
- [ ] Failed transcodes → `failed` with auditable step errors
- [ ] Every step idempotent per §5
- [ ] Mobile feed plays HLS; auto-play/pause on scroll
- [ ] Videos > 60 s rejected at validation
- [ ] All test cases in §11 passing
- [ ] No `Storage::` in jobs — only `StorageService`

**3.2b (phased, not blocking 3.2 MVP):**

- [ ] 360p / 1080p renditions + updated master playlist
- [ ] Profiling note documenting bandwidth/quality decision

---

## 13. Mobile

- Replace thumbnail-only feed card with `video_player` (HLS)
- Auto-play active page; pause off-screen
- Uploader sees `uploaded` / `queued` / `processing` / `failed` on profile

---

## 14. Approval

| Role | Name | Date | Status |
|------|------|------|--------|
| CTO / Founder | — | 2026-06-28 | Approved (v1) |
| Backend lead | — | 2026-06-28 | Approved (v1) |
| CTO / Founder | — | 2026-06-28 | Approved (v2 — state machine, MediaAsset, DAG) |
| Backend lead | — | 2026-06-28 | Approved (v2) |

**Approved (v2)** unlocks implementation on branch `feature/sprint-3.2-processing`.

---

## Changelog (blueprint)

| Version | Date | Changes |
|---------|------|---------|
| 1 | 2026-06-28 | Initial approved spec — linear pipeline, 720p/480p |
| 2 | 2026-06-28 | State machine (`uploaded`/`queued`); DAG orchestrator; `ValidateVideoStep`; `MediaAsset`; step lifecycle + `retrying`; idempotency §5; split transcode steps; 360p/1080p deferred to 3.2b |
