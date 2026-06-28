# Video Processing · Blueprint

**Sprint:** 3.2  
**Status:** Approved (2026-06-28)  
**Phase:** 3 — Video Platform  
**Depends on:** Sprint 3.1 — Video Upload Foundation ([release v0.3.1](https://github.com/Akmal1255553/livecommerce/releases/tag/v0.3.1-video-upload-foundation))  
**Overview:** [docs/SPRINT_3_VIDEO_PLATFORM.md](../docs/SPRINT_3_VIDEO_PLATFORM.md)

> **Gate rule:** No Sprint 3.2 code may be merged until this blueprint is marked **Approved** — satisfied 2026-06-28.

---

## 1. Goal

Replace pipeline **stubs** with real media processing: metadata extraction (ffprobe), thumbnail generation, HLS transcoding (FFmpeg), error handling, and **publish** videos to feeds. Enable HLS playback in the mobile feed.

**In scope:** FFmpeg jobs, real `PublishVideoStep`, failed/retry semantics, HLS URLs via `StorageService`, mobile `video_player`.

**Out of scope:** likes/comments (3.3), trending ranking (3.4), CDN edge caching (Sprint 15), real virus scanner (Sprint 15).

---

## 2. API

### 2.1 Endpoints (changes from 3.1)

| Method | Path | Auth | Change |
|--------|------|------|--------|
| GET | `/videos/{id}` | Optional | Published videos return `video_url` (HLS), `thumbnail_url`, `duration` |
| GET | `/feed/for-you` | Optional | Includes newly **published** videos (no contract change) |
| GET | `/feed/following` | Required | Same |

**No new public endpoints.** Processing is queue-driven (internal).

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

**Public URLs:** `StorageService::publicUrl($path)` — MinIO in dev; CDN domain in production (`AWS_URL` / dedicated config).

### 2.3 Processing visibility

| status | Visible in feed | GET `/videos/{id}` |
|--------|-----------------|---------------------|
| uploading | No | Owner only |
| processing | No | Owner only |
| published | Yes | Public per visibility |
| failed | No | Owner; `failure_code` in response meta |
| rejected | No | Owner |

---

## 3. Database schema

### 3.1 Alter `videos`

| Column | Type | Description |
|--------|------|-------------|
| codec | VARCHAR(50) | NULLABLE — e.g. h264 |
| bitrate | INTEGER | NULLABLE — kbps |
| failure_code | VARCHAR(50) | NULLABLE — transcode_error, duration_exceeded, … |
| failure_message | TEXT | NULLABLE | Internal detail |

Set `published_at` on successful publish (column exists from 3.1).

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

| Event | Trigger | Payload | Listeners |
|-------|---------|---------|-----------|
| `VideoUploadConfirmed` | (3.1) | — | Pipeline entry (unchanged) |
| `VideoMetadataExtracted` | Metadata step success | `videoId`, `duration`, `width`, `height` | — |
| `VideoTranscoded` | Transcode step success | `videoId`, `manifestPath` | — |
| `VideoPublished` | Publish step success | `videoId`, `userId` | Optional follower notify stub |
| `VideoProcessingFailed` | Step final failure | `videoId`, `step`, `code` | Log + in-app notify stub |

**3.2 change:** `PublishVideoStep` sets `status=published`, fills `video_url`, `thumbnail_url`, `published_at`.

---

## 5. Queues

**Queue:** `video-processing` (same as 3.1).

### 5.1 Processing steps (real implementation)

| Step | Input | Output | Timeout |
|------|-------|--------|---------|
| VirusScan | raw object key | pass / fail | 60s |
| ExtractMetadata | raw file (ffprobe) | duration, width, height, codec | 120s |
| GenerateThumbnail | raw @ 1s frame (ffmpeg) | `thumb.jpg` | 120s |
| TranscodeVideo | raw file (ffmpeg) | HLS manifest + segments | 600s |
| ModerateContent | metadata | auto-approve MVP | 30s |
| PublishVideo | paths | `published` status | 30s |

### 5.2 VirusScan (3.2)

Stub++: log file hash, always pass. Real ClamAV/vendor in Sprint 15.

### 5.3 TranscodeVideo

- Download raw to temp via `StorageService::downloadToTemp`
- FFmpeg → HLS 720p/480p
- Upload outputs via `StorageService::put`
- On failure: `videos.status=failed`, `failure_code=transcode_error`

### 5.4 Retry policy

| Setting | Value |
|---------|-------|
| Max attempts | 3 per pipeline job |
| Backoff | 30s, 120s, 600s |
| Failure audit | `video_processing_steps.error_message` + `videos.failure_*` |
| Manual retry | Admin endpoint — Sprint 14 |

### 5.5 Duration guard

If `duration > 60` after metadata → `failure_code=duration_exceeded`.

---

## 6. Services & infrastructure

| Component | Responsibility |
|-----------|----------------|
| `FfmpegTranscoderInterface` | Wrap ffprobe/ffmpeg CLI |
| `StorageService` | Add `publicUrl()`, `downloadToTemp()` |
| `VideoProcessingPipelineRunner` | Replace stub steps with real implementations |
| Docker `app` image | Must include `ffmpeg` and `ffprobe` |

**Rule:** Jobs use `StorageService` only — no `Storage::` facade.

---

## 7. Test cases

File: `tests/Feature/Video/VideoProcessingTest.php`

| # | Test | Assert |
|---|------|--------|
| 1 | Confirm upload → pipeline publishes video with HLS URL | status `published`, video_url set |
| 2 | Published video in GET `/feed/for-you` | video id in response |
| 3 | Thumbnail URL populated | thumbnail_url not null |
| 4 | Duration and dimensions from ffprobe | duration > 0, width/height set |
| 5 | All 6 processing steps completed (not skipped) | step rows |
| 6 | Transcode failure → status failed | failure_code set |
| 7 | Duration > 60s → duration_exceeded | status `failed` |
| 8 | VideoPublished event dispatched | Event::fake |
| 9 | Non-owner cannot see other's failed video | 404 |
| 10 | Owner can GET failed video with failure_code | 200 |

File: `tests/Unit/Services/FfmpegTranscoderTest.php`

**Fixture:** `tests/fixtures/sample.mp4` (< 1 MB, ~3s) committed for CI.

---

## 8. Acceptance criteria

- [x] Blueprint reviewed and marked **Approved**
- [ ] FFmpeg available in Docker app container
- [ ] Pipeline produces HLS + thumbnail; video `status=published`
- [ ] Published videos appear in For You / Following feeds
- [ ] Failed transcodes set `failed` with auditable step errors
- [ ] Mobile feed plays HLS; auto-play/pause on scroll
- [ ] Videos > 60s rejected at processing
- [ ] All test cases in §7 passing
- [ ] No `Storage::` in jobs — only `StorageService`

---

## 9. Mobile

- Replace thumbnail-only feed card with `video_player` (HLS)
- Auto-play active page; pause off-screen
- Uploader sees `processing` / `failed` state on profile

---

## 10. Approval

| Role | Name | Date | Status |
|------|------|------|--------|
| CTO / Founder | — | 2026-06-28 | Approved |
| Backend lead | — | 2026-06-28 | Approved |

**Approved** unlocks branch `feature/sprint-3.2-processing`.
