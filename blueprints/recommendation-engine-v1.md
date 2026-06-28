# Recommendation Engine v1 · Blueprint

**Version:** 1  
**Sprint:** 3.4  
**Status:** Approved (2026-06-28)  
**Phase:** 3 — Video Platform  
**Depends on:** Sprint 3.3 — [v0.3.3-video-interactions](https://github.com/Akmal1255553/livecommerce/releases/tag/v0.3.3-video-interactions)  
**Architecture:** [docs/22_RECOMMENDATION_ARCHITECTURE.md](../docs/22_RECOMMENDATION_ARCHITECTURE.md)  
**Analytics:** [docs/ANALYTICS_LAYER.md](../docs/ANALYTICS_LAYER.md)  
**Overview:** [docs/SPRINT_3_VIDEO_PLATFORM.md](../docs/SPRINT_3_VIDEO_PLATFORM.md)

> **Gate rule:** Satisfied 2026-06-28 — implementation may proceed on `feature/sprint-3.4-recommendations`.

---

## 1. Goal

Ship **rule-based recommendation v1** using the **ranking pipeline** architecture (Candidate → Filter → Score → Diversity → Exploration → Final Rank). No AI in this sprint.

**Architectural pillars:**

1. **`RecommendationEngineInterface`** — algorithm-agnostic; Sprint 9 swaps `AiRecommendationEngine` via DI.
2. **Pipeline stages** — no monolithic SQL ranking; each stage unit-tested.
3. **Multi-source candidates** — 9 sources registered; Category/Seller as stubs.
4. **Config-driven weights** — `config/recommendation.php`; zero hard-coded coefficients.
5. **Diversity + exploration** — author caps + 90/10 exploit/explore split.
6. **Analytics feedback loop** — `engagement_events` → rollups → scores.

**In scope:** Engine + pipeline, rollups, feed endpoints, Redis snapshots, tests.  
**Out of scope:** ML (Sprint 9), A/B framework (Sprint 13), product recommendations (Sprint 4+).

---

## 2. Engine & service contracts

### 2.1 Interfaces (`app/Contracts/Recommendation/`)

| Interface | Responsibility |
|-----------|----------------|
| `RecommendationServiceInterface` | Feed-facing: `feedTrending()`, `feedPopular()`, `feedNew()`, `feedForYou()` |
| `RecommendationEngineInterface` | `rank(FeedContext, CandidateCollection): RankedCollection` |
| `CandidateSourceInterface` | `generate(FeedContext): CandidateCollection` |
| `RankingPipelineStageInterface` | `handle(RankingContext, RankedCollection): RankedCollection` |

### 2.2 Implementations (Sprint 3.4)

| Class | Role |
|-------|------|
| `RecommendationService` | Cache, hydration, cursor, delegates to engine |
| `RuleBasedRecommendationEngine` | Composes pipeline stages |
| `AiRecommendationEngine` | **Stub** — throws `LogicException` if bound; Sprint 9 |

### 2.3 DI binding (`AppServiceProvider` or `RecommendationServiceProvider`)

```php
// config('recommendation.engine') === 'rule'
RecommendationEngineInterface → RuleBasedRecommendationEngine

// Sprint 9: 'ai' → AiRecommendationEngine
```

---

## 3. Ranking pipeline

```
CandidateGeneratorService
    → FilteringStage
    → ScoringStage (uses config weights)
    → DiversityStage
    → ExplorationStage
    → FinalRankingStage
```

Implemented inside `RuleBasedRecommendationEngine::rank()`.

### 3.1 FilteringStage

| Filter | Source |
|--------|--------|
| `status = published` | `videos` |
| `visibility = public` | `videos` |
| Not soft-deleted | `videos` |
| User `video_progress_100` last 30d | `engagement_events` |
| User `skip` with `watched_seconds < 3` last 7d | `engagement_events` |
| Blocked creators (future) | stub — pass-through |

### 3.2 ScoringStage

Loads signals from `video_engagement_rollups` (24h / 7d window) + `videos` counters.

**Formula** (weights from `config/recommendation.php`):

```
Score =
    norm(completion) × W_completion   // 0.40 default
  + norm(watch_time) × W_watch_time   // 0.20
  + norm(likes)      × W_like         // 0.15
  + norm(comments)   × W_comment      // 0.10
  + norm(shares)     × W_share        // 0.10
  + freshness        × W_freshness    // 0.05
```

`freshness = 1 / (1 + hours_since_publish / 24)`

Per-feed overrides: `config('recommendation.scoring.feeds.for_you')`.

### 3.3 DiversityStage

| Rule | Config key | Default |
|------|------------|---------|
| Max consecutive same author | `diversity.max_consecutive_same_author` | 2 |
| Max consecutive same category | `diversity.max_consecutive_same_category` | 3 (no-op until categories on videos) |
| Min gap same video | `diversity.min_gap_same_video_hours` | 72 |

### 3.4 ExplorationStage

| Config | Default |
|--------|---------|
| `exploration.exploit_ratio` | 0.90 |
| `exploration.explore_ratio` | 0.10 |
| `exploration.slot_positions` | `[3, 8, 15]` |

Explore pool: `ExplorationCandidateSource` — published < 7 days, `view_count < 1000`, random order.

### 3.5 FinalRankingStage

Stable sort: `score DESC`, `published_at DESC`, `id DESC`. Attach `snapshot` version hash for cursor.

---

## 4. Candidate sources

| Source class | `sourceId()` | Sprint 3.4 | Notes |
|--------------|--------------|:------------:|-------|
| `FollowingCandidateSource` | `following` | Used by `/feed/following` only | Chronological — outside engine |
| `TrendingCandidateSource` | `trending` | ✅ | Top 500 from 24h rollups |
| `PopularCandidateSource` | `popular` | ✅ | Top 500 by all-time score |
| `NewCandidateSource` | `new` | ✅ | Latest published |
| `BookmarksCandidateSource` | `bookmarks` | ✅ | Boost in For You merge |
| `ExplorationCandidateSource` | `exploration` | ✅ | Random fresh pool |
| `PreviouslyWatchedCandidateSource` | `previously_watched` | ✅ | Negative list for filter stage |
| `CategoryCandidateSource` | `category` | **Stub** | Returns empty |
| `SellerCandidateSource` | `seller` | **Stub** | Returns empty |

`CandidateGeneratorService` merges sources per feed strategy, dedupes by `video_id`, tags each candidate with `sources: string[]`.

---

## 5. API

### 5.1 Endpoints

| Method | Path | Auth | Engine |
|--------|------|------|--------|
| GET | `/feed/trending` | Optional | Pipeline + trending sources |
| GET | `/feed/popular` | Optional | Pipeline + popular sources |
| GET | `/feed/new` | Optional | Chronological — no engine |
| GET | `/feed/for-you` | Optional | Full pipeline + multi-source |
| GET | `/feed/following` | Required | **Unchanged** — no engine |

### 5.2 Response meta

```json
{
  "meta": {
    "next_cursor": "...",
    "has_more": true,
    "limit": 20,
    "strategy": "for_you",
    "snapshot": "a1b2c3d4",
    "engine": "rule"
  }
}
```

### 5.3 Ranked-feed cursor

```json
{ "snapshot": "sha1_of_list", "offset": 20 }
```

Encoded via `CursorPaginationData::encodeRankedCursor()` / `decodeRankedCursor()` (new helpers).

**New feed** keeps existing `encodeVideoCursor` (chronological).

---

## 6. Database

### 6.1 `video_engagement_rollups`

| Column | Type | Notes |
|--------|------|-------|
| id | BIGSERIAL | PK |
| video_id | UUID | FK → videos |
| bucket_hour | TIMESTAMP | UTC hour |
| views | INTEGER | from `view` |
| likes | INTEGER | from `like` |
| comments | INTEGER | from `comment` |
| shares | INTEGER | from `share` |
| saves | INTEGER | from `save` |
| completions | INTEGER | from `video_progress_100` |
| skips | INTEGER | from `skip` |
| follow_after_watch | INTEGER | from `follow_after_watch` |
| watch_seconds | BIGINT | sum `watch_time.payload.seconds` |

**Unique:** `(video_id, bucket_hour)`

### 6.2 Rollup job mapping

See [22_RECOMMENDATION_ARCHITECTURE.md](../docs/22_RECOMMENDATION_ARCHITECTURE.md) §8.

---

## 7. Configuration

**New file:** `config/recommendation.php`

```php
return [
    'engine' => env('RECOMMENDATION_ENGINE', 'rule'),

    'scoring' => [
        'weights' => [
            'completion' => (float) env('REC_WEIGHT_COMPLETION', 0.40),
            'watch_time' => (float) env('REC_WEIGHT_WATCH_TIME', 0.20),
            'like'       => (float) env('REC_WEIGHT_LIKE', 0.15),
            'comment'    => (float) env('REC_WEIGHT_COMMENT', 0.10),
            'share'      => (float) env('REC_WEIGHT_SHARE', 0.10),
            'freshness'  => (float) env('REC_WEIGHT_FRESHNESS', 0.05),
        ],
    ],

    'exploration' => [
        'exploit_ratio'    => 0.90,
        'explore_ratio'    => 0.10,
        'slot_positions'   => [3, 8, 15],
        'max_views'        => 1000,
        'max_age_days'     => 7,
    ],

    'diversity' => [
        'max_consecutive_same_author'   => 2,
        'max_consecutive_same_category' => 3,
        'min_gap_same_video_hours'      => 72,
    ],

    'cache' => [
        'trending_ttl'  => 300,
        'popular_ttl'   => 900,
        'for_you_ttl'   => 300,
        'snapshot_size' => 500,
    ],

    'rollup' => [
        'aggregation_interval_minutes' => 15,
        'trending_window_hours'        => 24,
        'for_you_window_days'          => 7,
    ],
];
```

---

## 8. Jobs & scheduler

| Job | Schedule | Description |
|-----|----------|-------------|
| `AggregateEngagementRollupsJob` | Every 15 min | `engagement_events` → rollups |
| `RefreshTrendingCacheJob` | Every 5 min | Trending snapshot → Redis |
| `RefreshPopularCacheJob` | Every 15 min | Popular snapshot → Redis |

Register in `bootstrap/app.php`.

---

## 9. Events

No new domain events for MVP. `feed_open` analytics already captures tab usage.

---

## 10. Test cases

### 10.1 Unit — `tests/Unit/Recommendation/`

| File | Tests |
|------|-------|
| `ScoringStageTest` | Config weights applied; normalization |
| `DiversityStageTest` | Author cap enforced |
| `ExplorationStageTest` | 90/10 slot injection |
| `RuleBasedRecommendationEngineTest` | Pipeline order |

### 10.2 Feature — `tests/Feature/Feed/RecommendationFeedTest.php`

| # | Test |
|---|------|
| 1 | Trending — published only |
| 2 | Trending order by 24h score |
| 3 | Popular order |
| 4 | New — chronological |
| 5 | Ranked cursor pagination |
| 6 | `meta.strategy` + `meta.engine` |
| 7 | For You excludes `video_progress_100` |
| 8 | For You excludes hard skip |
| 9 | Anonymous For You — 200 |
| 10 | Following regression |
| 11 | Diversity — max 2 same author in row |
| 12 | Exploration slot contains low-view video |
| 13 | Config weight change affects order |
| 14 | Category/Seller stubs return empty — no error |

### 10.3 Job — `tests/Feature/Jobs/AggregateEngagementRollupsJobTest.php`

| # | Test |
|---|------|
| 1 | Maps `like` + `watch_time` to rollup |
| 2 | Idempotent re-run |

**Target:** 120+ tests total.

---

## 11. Acceptance criteria

- [x] Blueprint + architecture docs **Approved** (2026-06-28)
- [ ] `RecommendationEngineInterface` + `RuleBasedRecommendationEngine`
- [ ] `AiRecommendationEngine` stub registered
- [ ] Full pipeline (5 stages) + 9 candidate sources (2 stubs)
- [ ] `config/recommendation.php` — no hard-coded weights in services
- [ ] `video_engagement_rollups` + aggregation job
- [ ] GET `/feed/trending`, `/feed/popular`, `/feed/new`, enhanced `/feed/for-you`
- [ ] Diversity + exploration active on For You
- [ ] Redis snapshots + ranked cursor
- [ ] Following feed unchanged
- [ ] PHPStan 0 / Pint pass / all tests pass
- [ ] [04_API_SPECIFICATION.md](../docs/04_API_SPECIFICATION.md) updated

---

## 12. Mobile

- Tabs: For You | Following | Trending | New
- `feed_open` with `payload.tab` per tab
- Stable `session_id` per tab session

---

## 13. Implementation branch & order

```
feature/sprint-3.4-recommendations  ← from develop after approval
```

1. `config/recommendation.php` + contracts + DTOs
2. Migration `video_engagement_rollups` + repository
3. `AggregateEngagementRollupsJob`
4. Pipeline stages + `RuleBasedRecommendationEngine`
5. Candidate sources (incl. stubs)
6. `RecommendationService` + cache jobs
7. `FeedController` + routes
8. Tests + docs

---

## 14. Approval

| Role | Name | Date | Status |
|------|------|------|--------|
| CTO / Founder | Akmal | 2026-06-28 | Approved |
| Backend lead | — | 2026-06-28 | Approved |

**Approved** — branch `feature/sprint-3.4-recommendations` unlocked.

---

## 15. References

| Doc | Role |
|-----|------|
| [22_RECOMMENDATION_ARCHITECTURE.md](../docs/22_RECOMMENDATION_ARCHITECTURE.md) | Canonical architecture |
| [ANALYTICS_LAYER.md](../docs/ANALYTICS_LAYER.md) | Feedback loop inputs |
| [SPRINT_3.4_BLUEPRINT.md](../docs/SPRINT_3.4_BLUEPRINT.md) | Sprint index (links here) |
| [21_CODE_QUALITY_REPORT.md](../docs/21_CODE_QUALITY_REPORT.md) | CI gate |
