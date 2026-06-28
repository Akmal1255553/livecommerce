# Sprint 2.3 — Feed Foundation

**Status:** Implemented  
**Phase:** 2 — Social Foundation  
**Depends on:** Sprint 2.1 (Follow System), Sprint 2.2 (Notifications)

## Summary

Sprint 2.3 delivers the read-path video feed: published videos with cursor pagination, For You and Following endpoints, `VideoResource` per API spec, and a mobile vertical feed with tab switching.

Upload, likes, comments, and rule-based recommendations ship in Sprint 3.1–3.4; feed read-path is Sprint 2.3.

## Backend

### Migration

| Table | Purpose |
|-------|---------|
| `videos` | Minimal publishable video schema (status, visibility, counters, soft deletes) |

### Services

- `VideoService` — `feedForYou()` and `feedFollowing()` with cursor pagination
- `VideoRepository::cursorPaginateFeed()` — published + public filter, optional user filter for following feed

### API Endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/feed/for-you` | Optional | All published public videos (cursor) |
| GET | `/feed/following` | Required | Videos from followed users only (cursor) |

Query: `cursor` (opaque), `limit` (default 20, max 50).

### Response shape

```json
{
  "success": true,
  "data": [ { VideoResource } ],
  "meta": {
    "next_cursor": "eyJpZCI6...",
    "prev_cursor": null,
    "has_more": true,
    "limit": 20
  }
}
```

### Resources

- `VideoResource` — full video payload per API spec (`is_liked` / `is_bookmarked` default false until Sprint 3.3)
- `UserCompactResource` — compact creator payload on each video

## Mobile

- `features/feed/` — repository, Riverpod providers, `FeedScreen`
- Vertical `PageView` with For You / Following tabs
- Cursor infinite scroll via `loadMore()`
- Home route (`/home`) renders `FeedScreen`

## Tests

`backend/tests/Feature/Feed/FeedTest.php`:

- For-you feed pagination and cursor meta
- Draft/private video exclusion
- Following feed auth requirement
- Following feed filters by follow graph
- Empty following feed
- Compact user payload in `VideoResource`

## Verification

```bash
docker compose -f docker/docker-compose.yml exec app php artisan migrate --force
docker compose -f docker/docker-compose.yml exec app ./vendor/bin/pest
```
