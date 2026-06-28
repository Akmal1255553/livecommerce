# Sprint 2.1 — Follow System

**Status:** Implemented (backend)  
**Phase:** 2 — Social Foundation  
**Depends on:** Sprint 1  
**Part of:** Sprint 2 — Social Foundation

## Summary

Sprint 2.1 delivers the user follow graph: follow/unfollow, public profiles, paginated follower/following lists, and denormalized counter updates on `user_profiles`. Notifications, blocks, and mobile UI are deferred to later Sprint 2 slices.

## Backend

### Migration

| Table | Purpose |
|-------|---------|
| `follows` | Follower/following relationships with unique pair constraint and self-follow check (PostgreSQL) |

### Services

- `FollowService` — follow, unfollow, public profile, paginated lists
- `UpdateFollowCounters` listener — increments counters on `UserFollowed` event

### Events

| Event | Payload | Listener |
|-------|---------|----------|
| `UserFollowed` | `followerId`, `followingId` | `UpdateFollowCounters` |

Unfollow decrements counters synchronously in `FollowService` (no event).

### API Endpoints

All under `/api/v1`:

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/users/{id}` | Optional | Public user profile (`is_following` when authenticated) |
| POST | `/users/{id}/follow` | Yes | Follow user |
| DELETE | `/users/{id}/follow` | Yes | Unfollow user |
| GET | `/users/{id}/followers` | Optional | Paginated followers (`page`, `per_page`) |
| GET | `/users/{id}/following` | Optional | Paginated following (`page`, `per_page`) |

### Middleware

- `auth.api.optional` — sets authenticated user when Bearer token is valid; does not require auth

### Business Rules

- Cannot follow yourself (403 via `UserPolicy`)
- Duplicate follow returns 409 Conflict
- Unfollow when not following returns 404
- Counter updates: `follower_count` on target, `following_count` on follower

### Tests

`tests/Feature/Follow/FollowTest.php` — 13 tests covering follow/unfollow, counters, public profile, pagination, auth, and error cases.

## Deferred (Sprint 2.2+)

- Block/unblock users
- Push/in-app notifications on new follower
- Mobile follow button and follower/following screens
- User search

## Verification

```bash
docker compose -f docker/docker-compose.yml exec app php artisan migrate --force
docker compose -f docker/docker-compose.yml exec app ./vendor/bin/pest
```

Expected: **26 passed** (120 assertions).

## Acceptance Criteria

- [x] Users can follow and unfollow other users
- [x] Follower/following counts update correctly
- [x] Public profile with optional `is_following`
- [x] Followers and following lists with offset pagination
- [x] Feature tests passing
- [ ] Push notifications for new follower (Sprint 2.2 — stub only)
- [ ] Mobile follow UI (Sprint 2.3)
- [ ] Block users (Sprint 2.2)
