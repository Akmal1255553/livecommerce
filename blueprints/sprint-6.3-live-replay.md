# Sprint 6.3 — Live Replay · Blueprint

**Version:** 1  
**Sprint:** 6.3  
**Status:** Ready for deploy (`feature/sprint-6.3-live-replay`)  
**Depends on:** Sprint 6.0–6.2  
**ADR:** [ADR-019](../07_ADR.md#adr-019-live-as-content--livesession)

## Goal

After a live ends, expose a **replay** with product pin timeline (`offset_seconds`) so viewers can scrub and jump to products shown during the stream.

## In scope

| Area | Notes |
|------|--------|
| Provider | `StreamingProviderInterface::fetchReplayUrl()` — Fake returns placeholder VOD URL; Agora returns null (stub) |
| End session | Sets `live_sessions.replay_url` on `POST /live/{id}/end` |
| API | `GET /live/replays` — ended sessions with replay_url |
| Payload | `replay_url`, `duration_seconds`, `product_timeline[]` on LiveSessionResource |
| Mobile | Live tab **Replays**; `/live/replay/:id` scrubber + product markers |

## Out of scope

- Real Agora / Mux cloud recording fetch
- Actual video player SDK (placeholder + scrubber for Chrome MVP)
- Analytics dashboards (6.4)

## Release

- Branch: `feature/sprint-6.3-live-replay`  
- Render: switch deploy branch after push  
- Test: Go Live → pin → End → Live → Replays tab → open replay → scrub to pin time
