# Recommendation Architecture

**Status:** Active — canonical architecture for Sprint 3.4+ (blueprint approved 2026-06-28)  
**Sprint 3.4:** Rule-based engine (no AI)  
**Sprint 9:** `AiRecommendationEngine` replaces scoring internals only  
**Analytics input:** [ANALYTICS_LAYER.md](./ANALYTICS_LAYER.md)  
**Implementation blueprint:** [blueprints/recommendation-engine-v1.md](../blueprints/recommendation-engine-v1.md)

---

## 1. Design principles

1. **Algorithm-agnostic** — controllers and feed APIs depend on `RecommendationEngineInterface`, not on rule-based or ML specifics.
2. **Pipeline, not monolith SQL** — ranking is a sequence of discrete stages; each stage is testable and replaceable.
3. **Config-driven weights** — scoring coefficients live in `config/recommendation.php`, not hard-coded in services.
4. **Analytics as feedback loop** — every signal in [ANALYTICS_LAYER.md](./ANALYTICS_LAYER.md) is an input to scoring, not just telemetry.
5. **Diversity + exploration** — without these layers, feeds collapse into repetitive content; both are first-class pipeline stages in 3.4.
6. **Sprint 9 swap** — swap `RuleBasedRecommendationEngine` → `AiRecommendationEngine` via DI binding; API contracts frozen.

---

## 2. Engine abstraction

Do **not** bind the platform to a single algorithm. Use a thin orchestration façade and a swappable engine.

```
RecommendationService          ← feed-facing façade (pagination, cache, hydration)
        │
        ▼
RecommendationEngineInterface  ← algorithm contract
        │
        ├── RuleBasedRecommendationEngine   (Sprint 3.4 — MVP)
        │
        └── AiRecommendationEngine          (Sprint 9 — ML scoring)
```

### 2.1 `RecommendationEngineInterface`

Responsible for running the **ranking pipeline** (§3) for a given `FeedContext`.

```php
interface RecommendationEngineInterface
{
  public function rank(FeedContext $context, CandidateCollection $candidates): RankedCollection;
}
```

| Implementation | Sprint | Scoring |
|----------------|--------|---------|
| `RuleBasedRecommendationEngine` | 3.4 | Weighted formula from rollups + counters (config) |
| `AiRecommendationEngine` | 9 | Model inference; same pipeline stages, different `ScoringStage` |

### 2.2 `RecommendationService`

Not an algorithm. Responsibilities:

- Resolve feed strategy (`trending`, `popular`, `new`, `for_you`, `following`)
- Invoke candidate generators
- Delegate `rank()` to bound engine
- Apply Redis snapshot cache for list feeds
- Hydrate `Video` models + `enrichVideosForViewer()`
- Return `CursorPaginationData<Video>`

---

## 3. Ranking pipeline

**Never** compute final feed order in one SQL query. Use an explicit pipeline:

```
┌─────────────────────┐
│ Candidate Generator │  ← merge multiple sources (§4)
└──────────┬──────────┘
           ▼
┌─────────────────────┐
│ Filtering           │  ← published, visibility, blocked, already-watched hard excludes
└──────────┬──────────┘
           ▼
┌─────────────────────┐
│ Scoring             │  ← engine-specific (rule weights or ML)
└──────────┬──────────┘
           ▼
┌─────────────────────┐
│ Diversity           │  ← author/category caps, spacing rules (§6)
└──────────┬──────────┘
           ▼
┌─────────────────────┐
│ Exploration         │  ← inject fresh/random slots (§7)
└──────────┬──────────┘
           ▼
┌─────────────────────┐
│ Final Ranking       │  ← stable sort, cursor snapshot, persist to Redis
└─────────────────────┘
```

Each stage implements `RankingPipelineStageInterface`:

```php
interface RankingPipelineStageInterface
{
  public function handle(RankingContext $context, RankedCollection $items): RankedCollection;
}
```

`RuleBasedRecommendationEngine` composes stages in order. Sprint 9 may replace `ScoringStage` only, or plug a parallel `AiScoringStage` behind the same interface.

### 3.1 Stage responsibilities

| Stage | Input | Output | Notes |
|-------|-------|--------|-------|
| **Candidate Generator** | `FeedContext`, user | `CandidateCollection` (deduped video IDs + source tags) | Multi-source merge |
| **Filtering** | Candidates | Smaller set | Hard excludes; no scoring yet |
| **Scoring** | Filtered candidates | Scored items (`score`, `signals`) | Config weights (3.4) or model (9) |
| **Diversity** | Scored list | Reordered list | Cap author/category streaks |
| **Exploration** | Diverse list | List with exploration slots | 90/10 blend (configurable) |
| **Final Ranking** | List | `RankedCollection` + snapshot metadata | Tie-break: `published_at`, `id` |

---

## 4. Candidate sources

Candidate generation is **multi-source**. Sprint 3.4 implements core sources; others are registered as **stubs** returning empty collections until their sprint lands.

| Source | ID | Sprint | Description |
|--------|-----|--------|-------------|
| Following | `following` | 2.3 ✅ | Videos from followed creators |
| Trending | `trending` | 3.4 | High 24h velocity from rollups |
| Popular | `popular` | 3.4 | All-time engagement leaders |
| New | `new` | 3.4 | Latest `published_at` |
| Category | `category` | 4+ | Videos tagged to user's interested categories — **stub** |
| Seller | `seller` | 5+ | Videos from followed stores / live sellers — **stub** |
| Previously Watched | `previously_watched` | 3.4 | Negative source — IDs to deprioritize/exclude |
| Bookmarks | `bookmarks` | 3.3 ✅ | Saved videos — boost signal, not primary feed |
| Random Exploration | `exploration` | 3.4 | Fresh pool for 10% exploration slots |

### 4.1 `CandidateSourceInterface`

```php
interface CandidateSourceInterface
{
  public function sourceId(): string;

  /** @return CandidateCollection */
  public function generate(FeedContext $context): CandidateCollection;
}
```

Registered in `RecommendationServiceProvider`. Stubs return `CandidateCollection::empty()` and log at `debug` once per request max.

### 4.2 Feed → source mapping

| Feed endpoint | Primary sources | Pipeline |
|---------------|-----------------|----------|
| `/feed/following` | `following` only | Chronological — **bypasses engine** (Sprint 2.3 preserved) |
| `/feed/trending` | `trending` | Full pipeline, trending score |
| `/feed/popular` | `popular` | Full pipeline, popular score |
| `/feed/new` | `new` | Chronological — **bypasses engine** |
| `/feed/for-you` | `trending`, `popular`, `new`, `bookmarks`, `exploration` | Full pipeline + personalization |

---

## 5. Rule-based scoring (Sprint 3.4)

**No AI in 3.4.** `RuleBasedRecommendationEngine` uses a transparent weighted formula.

### 5.1 Default formula

```
Score =
    Watch Completion  × W_completion   (default 40%)
  + Watch Time        × W_watch_time   (default 20%)
  + Like              × W_like         (default 15%)
  + Comment           × W_comment      (default 10%)
  + Share             × W_share        (default 10%)
  + Freshness         × W_freshness    (default  5%)
```

Each component is **normalized to [0, 1]** per candidate batch before applying weights.

| Component | Signal source | Normalization |
|-----------|---------------|---------------|
| Watch Completion | `video_progress_100` rate or rollup `completions / views` | min-max in batch |
| Watch Time | Sum `watch_time.payload.seconds` from rollups | log-scale min-max |
| Like | `like_count` or 24h rollup | min-max |
| Comment | `comment_count` or 24h rollup | min-max |
| Share | `share_count` or 24h rollup | min-max |
| Freshness | `hours_since_publish` | `1 / (1 + hours/24)` — already [0,1] |

Negative signals applied in **Filtering** or as score penalties (configurable):

| Signal | Effect |
|--------|--------|
| `skip` (watched < 3s) | Hard exclude from For You (MVP) |
| `video_progress_100` by user | Hard exclude re-show (30 days) |

### 5.2 Config-driven weights

All coefficients in `config/recommendation.php`:

```php
return [
    'engine' => env('RECOMMENDATION_ENGINE', 'rule'), // rule | ai (Sprint 9)

    'scoring' => [
        'weights' => [
            'completion' => 0.40,
            'watch_time' => 0.20,
            'like'       => 0.15,
            'comment'    => 0.10,
            'share'      => 0.10,
            'freshness'  => 0.05,
        ],
        // Per-feed overrides (optional)
        'feeds' => [
            'trending' => [ /* ... */ ],
            'for_you'  => [ /* ... */ ],
        ],
    ],

    'exploration' => [
        'exploit_ratio' => 0.90,  // best score
        'explore_ratio' => 0.10,  // random fresh
    ],

    'diversity' => [
        'max_consecutive_same_author'    => 2,
        'max_consecutive_same_category'  => 3, // when categories exist
        'min_gap_same_video_hours'       => 72,
    ],
];
```

**Rule:** no weight literals in service classes — only `config('recommendation.scoring.weights.*')`.

---

## 6. Diversity layer

Without diversity, feeds degenerate:

```
Cat → Cat → Cat → Cat → Cat → Cat → Cat
```

`DiversityStage` runs **after** scoring, **before** exploration.

### 6.1 Rules (MVP)

| Rule | Default | Action |
|------|---------|--------|
| Max consecutive same author | 2 | Pull next-best different author |
| Max consecutive same category | 3 | Stub until Sprint 4 categories on videos — stage no-op |
| Recently shown video | 72h gap | Deprioritize if same user saw `video_impression` in session history (Redis set per user) |

### 6.2 Algorithm (greedy re-rank)

1. Sort by score DESC
2. Iterate: pick highest-scored item that does not violate consecutive author/category caps
3. If stuck, relax caps by 1 (logged once) — avoids empty slots

Diversity is **deterministic** given same input — aids testing.

---

## 7. Exploration layer

`ExplorationStage` injects **new creator content** so the feed does not only reinforce winners.

### 7.1 Default split (configurable)

| Pool | Ratio | Source |
|------|-------|--------|
| **Exploit** (best score) | 90% | Post-diversity ranked list |
| **Explore** (random fresh) | 10% | `exploration` candidate source — videos < 7 days old, < 1000 views, random shuffle |

### 7.2 Placement

For a page of `limit=20`:

- Positions 3, 8, 15 (configurable slots) → exploration candidates if available
- Remaining slots → exploit pool in score order

Helps new authors surface without destroying overall feed quality.

---

## 8. Feedback loop

Analytics collected in Sprint 3.3 becomes **recommendation input**:

```
Video Viewed (view)
        ↓
Watch Time (watch_time)
        ↓
Completion (video_progress_100)
        ↓
Like → Comment → Share → Save
        ↓
Recommendation Score (ScoringStage)
        ↓
Feed order → video_impression (next session)
```

### 8.1 Data paths

| Layer | Table / store | Used by |
|-------|---------------|---------|
| Raw events | `engagement_events` | Rollup job, per-user exclusions |
| Hourly aggregates | `video_engagement_rollups` | Trending / scoring windows |
| Denormalized counters | `videos.*_count` | Popular feed, fast scoring |
| Per-user state | Redis `rec:user:{id}:seen` | Diversity + exclude re-shows |

`AggregateEngagementRollupsJob` bridges `engagement_events` → rollups (see blueprint §5).

---

## 9. Caching

| Key | TTL | Content |
|-----|-----|---------|
| `feed:trending:snapshot` | 5 min | `{ version, ids[] }` |
| `feed:popular:snapshot` | 15 min | `{ version, ids[] }` |
| `feed:for_you:{user_id}:snapshot` | 5 min | Personalized ranked IDs |

Cache stores **IDs only**. Hydration + `is_liked` / `is_bookmarked` enrichment happens in `RecommendationService`.

Ranked feeds use **offset cursor** tied to `snapshot` version — see blueprint §3.3.

---

## 10. Sprint 9 migration path

| Layer | Sprint 3.4 | Sprint 9 |
|-------|------------|----------|
| API endpoints | Frozen | Unchanged |
| `RecommendationService` | Facade + cache | Unchanged |
| `RecommendationEngineInterface` | `RuleBasedRecommendationEngine` | Bind `AiRecommendationEngine` |
| `ScoringStage` | Config weights | Model inference |
| Candidate sources | + stubs | + collaborative signals |
| Diversity / Exploration | Rule-based | Tunable via model policy |
| `config/recommendation.php` | Rule weights | + `ai` section (model URI, features) |

**ADR required** before enabling `ai` engine in production.

---

## 11. Module layout

```
app/
├── Contracts/Recommendation/
│   ├── RecommendationEngineInterface.php
│   ├── CandidateSourceInterface.php
│   ├── RankingPipelineStageInterface.php
│   └── RecommendationServiceInterface.php
├── Services/Recommendation/
│   ├── RecommendationService.php
│   ├── Engines/
│   │   ├── RuleBasedRecommendationEngine.php
│   │   └── AiRecommendationEngine.php          (Sprint 9 stub in 3.4)
│   ├── Pipeline/
│   │   ├── FilteringStage.php
│   │   ├── ScoringStage.php
│   │   ├── DiversityStage.php
│   │   ├── ExplorationStage.php
│   │   └── FinalRankingStage.php
│   ├── Sources/
│   │   ├── TrendingCandidateSource.php
│   │   ├── PopularCandidateSource.php
│   │   ├── NewCandidateSource.php
│   │   ├── FollowingCandidateSource.php
│   │   ├── BookmarksCandidateSource.php
│   │   ├── ExplorationCandidateSource.php
│   │   ├── CategoryCandidateSource.php         (stub)
│   │   └── SellerCandidateSource.php           (stub)
│   └── DTOs/
│       ├── FeedContext.php
│       ├── CandidateCollection.php
│       └── RankedCollection.php
config/
└── recommendation.php
```

---

## 12. References

| Document | Role |
|----------|------|
| [ANALYTICS_LAYER.md](./ANALYTICS_LAYER.md) | Event types + funnel |
| [blueprints/recommendation-engine-v1.md](../blueprints/recommendation-engine-v1.md) | Sprint 3.4 implementation spec |
| [SPRINT_3.4_BLUEPRINT.md](./SPRINT_3.4_BLUEPRINT.md) | Sprint summary + API + tests |
| [21_CODE_QUALITY_REPORT.md](./21_CODE_QUALITY_REPORT.md) | CI / PHPStan gate |
