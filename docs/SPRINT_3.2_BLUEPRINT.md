# Sprint 3.2 — Video Processing · Blueprint

**Status:** Blueprint (implementation blocked until approved)  
**Phase:** 3 — Video Platform  
**Depends on:** Sprint 3.1 (approved + merged)  
**Part of:** [Sprint 3 overview](./SPRINT_3_VIDEO_PLATFORM.md)

> **Gate rule:** No Sprint 3.2 code may be merged until this blueprint is reviewed and marked **Approved**.

---

## 1. Goal

Replace pipeline **stubs** with real media processing: metadata extraction (ffprobe), thumbnail generation, HLS transcoding (FFmpeg), error handling, and **publish** videos to feeds. Enable HLS playback in the mobile feed.

**In scope:** FFmpeg jobs, `PublishVideoJob`, failed/retry semantics, HLS URLs via `StorageService`, mobile video player.

**Out of scope:** likes/comments (3.3), trending ranking (3.4), CDN edge caching (Sprint 15), real virus scanner (Sprint 15).

---

## 2. API

### 2.1 Endpoints (changes from 3.1)

| Method | Path | Auth | Change |
|--------|------|------|--------|
| GET | `/videos/{id}` | Optional | Published videos return `video_url` (HLS), `thumbnail_url`, `duration` |
| GET | `/feed/for-you` | Optional | Includes newly **published** videos (no API contract change) |
| GET | `/feed/following` | Required | Same |

**No new public endpoints.** Processing is internal (queue-driven).

### 2.2 VideoResource (published)

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

**CDN URL:** `StorageService::publicUrl($path)` — MinIO direct in dev; CDN domain in prod config.

### 2.3 Processing visibility

| status | Visible in feed | GET `/videos/{id}` |
|--------|-----------------|---------------------|
| uploading | No | Owner only |
| processing | No | Owner only; include `processing_progress` optional meta |
| published | Yes | Public per visibility |
| failed | No | Owner; include `failure_reason` code |
| rejected | No | Owner |

---

## 3. Database schema

### 3.1 Alter `videos`

| Column | Type | Description |
|--------|------|-------------|
| codec | VARCHAR(50) | NULLABLE — e.g. h264 |
| bitrate | INTEGER | NULLABLE — kbps |
| failure_code | VARCHAR(50) | NULLABLE — transcode_error, virus_detected, … |
| failure_message | TEXT | NULLABLE | Internal detail |

Set `published_at` on successful publish.

### 3.2 Alter `media_uploads`

| Column | Type | Description |
|--------|------|-------------|
| processed_at | TIMESTAMP | NULLABLE |

### 3.3 `video_processing_steps`

No schema change — reuse from 3.1. Steps transition from `skipped` → `completed` with real timestamps.

### 3.4 Storage outputs (via StorageService)

| Path | Description |
|------|-------------|
| `videos/{id}/raw.mp4` | Input (from 3.1) |
| `videos/{id}/thumb.jpg` | 720px JPEG thumbnail |
| `videos/{id}/hls/master.m3u8` | HLS master playlist |
| `videos/{id}/hls/720p/segment_%04d.ts` | 720p segments |
| `videos/{id}/hls/480p/segment_%04d.ts` | 480p segments |

**MVP transcode profile:** max duration 60s; 720p + 480p HLS; AAC audio.

---

## 4. Events

| Event | Trigger | Payload | Listeners / Jobs |
|-------|---------|---------|------------------|
| `VideoUploadConfirmed` | (3.1) | — | Pipeline (unchanged entry) |
| `VideoMetadataExtracted` | ExtractMetadataJob success | `videoId`, `duration`, `width`, `height` | — |
| `VideoTranscoded` | TranscodeVideoJob success | `videoId`, `manifestPath` | — |
| `VideoPublished` | PublishVideoJob success | `videoId`, `userId` | Notify followers (optional stub → Sprint 2.2 pattern) |
| `VideoProcessingFailed` | Any step final failure | `videoId`, `step`, `code` | Log + notify uploader (in-app stub) |

**Replace 3.1 stub:** `PublishVideoJob` sets `status=published`, fills `video_url`, `thumbnail_url`, `published_at`.

---

## 5. Queues

**Queue:** `video-processing` (same as 3.1).

### 5.1 Step jobs (real implementation)

| Job | Input | Output | Timeout |
|-----|-------|--------|---------|
| `VirusScanJob` | raw object key | pass / fail | 60s |
| `ExtractMetadataJob` | raw file | duration, width, height, codec → `videos` | 120s |
| `GenerateThumbnailJob` | raw file @ 1s frame | `thumb.jpg` uploaded | 120s |
| `TranscodeVideoJob` | raw file | HLS manifest + segments | 600s |
| `ModerateContentJob` | metadata | auto-approve MVP | 30s |
| `PublishVideoJob` | paths | `published` status | 30s |

### 5.2 VirusScanJob (3.2)

**Stub++:** log file hash, always pass. Store `scanned_at` in step payload. Real scanner in Sprint 15.

### 5.3 TranscodeVideoJob

- Shell out to `ffmpeg` / `ffprobe` (Docker image includes ffmpeg)
- Write to temp dir → upload segments via `StorageService::put`
- On failure: `videos.status=failed`, `failure_code=transcode_error`

### 5.4 Retry & DLQ

| Setting | Value |
|---------|-------|
| Max attempts | 3 per step |
| Backoff | 30s, 120s, 600s |
| DLQ | `failed` video + `video_processing_steps.error_message` |
| Manual retry | Admin endpoint deferred to Sprint 14 |

### 5.5 Duration guard

If `duration > 60` after metadata extract → fail with `failure_code=duration_exceeded` (MVP limit).

---

## 6. Services

| Service | New / changed |
|---------|-----------------|
| `VideoProcessingService` | Orchestration helpers, step status |
| `FfmpegTranscoderInterface` | `FfmpegTranscoder` — wrap CLI |
| `StorageService` | `put`, `publicUrl`, `downloadToTemp` |
| `VideoUploadService` | No change to upload API |

**Infrastructure:** Docker `app` image must include `ffmpeg` and `ffprobe`. Document in `SPRINT_3.2` verification section.

---

## 7. Test cases

File: `tests/Feature/Video/VideoProcessingTest.php`

| # | Test | Assert |
|---|------|--------|
| 1 | Confirm upload → pipeline publishes video with HLS URL | status `published`, video_url set |
| 2 | Published video appears in GET `/feed/for-you` | video id in response |
| 3 | Thumbnail URL populated after processing | thumbnail_url not null |
| 4 | Duration and dimensions set from metadata | duration > 0, width/height set |
| 5 | All processing steps marked completed | 6 rows in video_processing_steps |
| 6 | Transcode failure sets status failed | status `failed`, failure_code set |
| 7 | Duration > 60s fails with duration_exceeded | status `failed` |
| 8 | VideoPublished event dispatched on success | Event fake |
| 9 | Non-owner cannot see failed video details of other user | 404 |
| 10 | Owner can GET failed video with failure_code | 200 |

File: `tests/Unit/Services/FfmpegTranscoderTest.php` — mock FFmpeg; verify command assembly.

**Test media:** commit small fixture `tests/fixtures/sample.mp4` (< 1 MB, 3s) for CI.

---

## 8. Acceptance criteria

- [ ] Blueprint reviewed and marked **Approved**
- [ ] FFmpeg available in Docker app container
- [ ] Pipeline produces HLS + thumbnail; video `status=published`
- [ ] Published videos appear in For You / Following feeds (Sprint 2.3)
- [ ] Failed transcodes set `failed` with auditable step errors
- [ ] Mobile feed plays HLS (video_player); auto-play/pause on scroll
- [ ] Videos > 60s rejected at processing
- [ ] All test cases in §7 passing
- [ ] No `Storage::` in jobs — only `StorageService`

---

## 9. Mobile

- Replace thumbnail-only feed card with `video_player` HLS
- Auto-play active page; pause off-screen
- Show processing state on uploader's profile for `processing`/`failed`

---

## 10. Approval

| Role | Name | Date | Status |
|------|------|------|--------|
| CTO / Founder | | | Pending |
| Backend lead | | | Pending |

**Approved** unlocks `feature/sprint-3.2-processing`.
