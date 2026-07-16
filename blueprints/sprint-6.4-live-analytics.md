# Sprint 6.4 — Live Analytics · Blueprint

**Version:** 1  
**Sprint:** 6.4  
**Status:** Ready for deploy (`feature/sprint-6.4-live-analytics`)  
**Depends on:** Sprint 6.0–6.3 (events + metrics already recorded)

## Goal

Give sellers a dashboard of live performance: viewers, pins, add-to-cart, conversion, and per-session drill-down from existing `live_analytics_events` + `live_viewer_metrics`.

## In scope

| Area | Notes |
|------|--------|
| API | `GET /seller/live/analytics` overview |
| API | `GET /seller/live/{id}/analytics` session detail |
| Metrics | peak/unique viewers, chat count, pins, add-to-cart, cart conversion, top products |
| Mobile | Seller dashboard CTA + overview + session screens |

## Out of scope

- Charts libraries / time-series graphs (MVP = cards + lists)
- `purchase_from_live` order attribution (needs payment deep link later)
- Admin analytics

## Release

- Branch: `feature/sprint-6.4-live-analytics`  
- Render: switch branch after push  
- Test: run a live with pin + add-to-cart → Seller → Live analytics
