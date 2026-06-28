# Sprint 3.4 — Recommendation Engine v1 · Blueprint

**Status:** Blueprint (implementation blocked until approved)  
**Phase:** 3 — Video Platform  
**Depends on:** Sprint 3.3 (approved + merged)  
**Part of:** [Sprint 3 overview](./SPRINT_3_VIDEO_PLATFORM.md)

> **Gate rule:** No Sprint 3.4 code may be merged until this blueprint is reviewed and marked **Approved**.

---

## 1. Goal

Ship **rule-based recommendation v1** (no AI): Trending, Popular, New feeds, and enhanced For You ranking using engagement signals from 3.1/3.3. Redis-cached hot lists. Sprint 9 ML replaces scoring internals without API contract changes.

**In scope:** `RecommendationService`, new feed endpoints, For You rule upgrade, caching, mobile feed tabs.

**Out of scope:** ML models (Sprint 9), collaborative filtering, A/B testing framework (Sprint 13), personalized product recommendations.

---

## 2. API

### 2.1 Endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/feed/trending` | Optional | Trending videos (cursor) |
| GET | `/feed/popular` | Optional | All-time popular (cursor) |
| GET | `/feed/new` | Optional | Latest published (cursor) |
| GET | `/feed/for-you` | Optional | **Enhanced** — rule-based ranking (replaces chronological default) |
| GET | `/feed/following` | Required | Unchanged (Sprint 2.3) |

**Query params (all feed endpoints):** `cursor`, `limit` (default 20, max 50) — per [API Specification](./04_API_SPECIFICATION.md) §4.1.

### 2.2 Response shape

Unchanged cursor envelope; `data` = array of `VideoResource`.

```json
{
  "success": true,
  "data": [ { "VideoResource" } ],
  "meta": {
    "next_cursor": "...",
    "prev_cursor": null,
    "has_more": true,
    "limit": 20,
    "strategy": "trending"
  }
}
```

New optional meta field: `strategy` (`trending`, `popular`, `new`, `for_you`, `following`).

### 2.3 Ranking definitions (MVP)

| Feed | Score formula | Window |
|------|---------------|--------|
| **Trending** | `(views_24h × 1) + (likes_24h × 3) + (comments_24h × 5) + (shares_24h × 4)` × recency decay | 24 hours |
| **Popular** | `(view_count × 1) + (like_count × 3) + (comment_count × 5) + (share_count × 4)` | All time |
| **New** | `published_at DESC`, `id DESC` | — |
| **For You** | Blend: 40% trending score + 30% popular (normalized) + 30% new; exclude videos user watched >90% (completion events) | 7 days signals |

**Recency decay (trending):** `score × (1 / (1 + hours_since_publish / 24))`.

**Anonymous For You:** trending + new mix (no completion exclusion).

### 2.4 Cache

| Key | TTL | Content |
|-----|-----|---------|
| `feed:trending:page:{hash}` | 5 min | Pre-ranked video IDs |
| `feed:popular:page:{hash}` | 15 min | Pre-ranked video IDs |
| `feed:for_you:{user_id}:page:{hash}` | 5 min | Personalized ranked IDs |

Cache miss → compute from DB + Redis rollups → store IDs only (hydrate videos in service).

---

## 3. Database schema

### 3.1 New table: `video_engagement_rollups`

Hourly aggregates for trending (populated by scheduled job from `engagement_events` + counters).

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGSERIAL | PK |
| video_id | UUID | FK → videos, NOT NULL |
| bucket_hour | TIMESTAMP | NOT NULL — truncated to hour |
| views | INTEGER | DEFAULT 0 |
| likes | INTEGER | DEFAULT 0 |
| comments | INTEGER | DEFAULT 0 |
| shares | INTEGER | DEFAULT 0 |
| watch_seconds | BIGINT | DEFAULT 0 |

**Unique:** `(video_id, bucket_hour)` · **Index:** `(bucket_hour DESC)`.

### 3.2 Existing tables (read-only)

- `videos` — counters, `published_at`, status filter
- `engagement_events` — completion exclusion for For You
- `video_likes`, `comments`, `video_shares` — optional cross-check

### 3.3 No change to feed cursor format

Reuse `CursorPaginationData::encodeVideoCursor` from Sprint 2.3.

---

## 4. Events

| Event | Trigger | Payload | Listeners |
|-------|---------|---------|-----------|
| `FeedRequested` | Optional telemetry | `strategy`, `userId?`, `cursor` | Metrics (feed_open already in 3.1) |
| `TrendingCacheRefreshed` | Scheduler | `videoCount` | — |

**No new domain events required for MVP.** Ranking is pull-based on feed request + cache.

---

## 5. Queues & scheduler

| Job | Schedule | Description |
|-----|----------|-------------|
| `AggregateEngagementRollupsJob` | Every 15 min | Sum engagement_events → video_engagement_rollups |
| `RefreshTrendingCacheJob` | Every 5 min | Precompute top 500 trending video IDs → Redis |
| `RefreshPopularCacheJob` | Every 15 min | Precompute top 500 popular video IDs → Redis |

**Queue:** `default`.

---

## 6. Services

| Contract | Responsibility |
|----------|----------------|
| `RecommendationServiceInterface` | score(), rank(), getCandidates() |
| `TrendingFeedStrategy` | 24h window scoring |
| `PopularFeedStrategy` | all-time counters |
| `NewFeedStrategy` | published_at order |
| `ForYouFeedStrategy` | blended rules + completion exclusion |
| `VideoService` | delegate feed methods to strategies; hydrate VideoResource |

**Sprint 9 swap:** replace strategy internals; keep interface + endpoint contracts.

### 6.1 Architecture

```
FeedController
    → VideoService / FeedService
        → RecommendationService (strategy per endpoint)
            → VideoRepository (hydrate by IDs)
            → Redis (cache)
            → video_engagement_rollups (trending)
```

---

## 7. Test cases

File: `tests/Feature/Feed/RecommendationFeedTest.php`

| # | Test | Assert |
|---|------|--------|
| 1 | GET /feed/trending returns published videos only | 200, all status published |
| 2 | Trending order — higher 24h engagement ranks first | seed 3 videos, assert order |
| 3 | GET /feed/popular ranks by total engagement | order by popular score |
| 4 | GET /feed/new returns newest published first | published_at DESC |
| 5 | Cursor pagination works on trending | page 1 + page 2, no duplicates |
| 6 | meta.strategy = trending | meta field |
| 7 | For You excludes completed videos for auth user | completion event seeded |
| 8 | Anonymous For You returns 200 | no auth required |
| 9 | Following feed unchanged from 2.3 | regression test |
| 10 | Cache hit reduces DB queries | optional query count assertion |
| 11 | Empty trending returns empty data + has_more false | edge case |
| 12 | Draft/processing videos never appear | excluded |

File: `tests/Unit/Services/RecommendationServiceTest.php` — score formula unit tests with fixed inputs.

File: `tests/Feature/Feed/FeedTest.php` — update For You test if ranking changes order (document expected behaviour).

---

## 8. Acceptance criteria

- [ ] Blueprint reviewed and marked **Approved**
- [ ] `video_engagement_rollups` migration + aggregation job running
- [ ] GET `/feed/trending`, `/feed/popular`, `/feed/new` live with cursor pagination
- [ ] GET `/feed/for-you` uses rule-based ranking (not pure chronological)
- [ ] Redis cache for trending/popular with documented TTLs
- [ ] Following feed regression — no behaviour change
- [ ] Sprint 9 can replace `RecommendationService` without breaking API contracts
- [ ] Mobile: Trending / Popular / New tabs or filter chips
- [ ] All test cases in §7 passing

---

## 9. Mobile

- Extend FeedScreen tabs: For You | Following | Trending | New (Popular optional 5th or merged)
- Each tab calls respective endpoint with shared cursor pagination provider
- Preserve vertical PageView + infinite scroll from Sprint 2.3

---

## 10. Approval

| Role | Name | Date | Status |
|------|------|------|--------|
| CTO / Founder | | | Pending |
| Backend lead | | | Pending |

**Approved** unlocks `feature/sprint-3.4-recommendations`.

---

## 11. API spec follow-up

Add `/feed/popular` and `/feed/new` to [04_API_SPECIFICATION.md](./04_API_SPECIFICATION.md) §7.4 when implementing (currently only trending listed).
