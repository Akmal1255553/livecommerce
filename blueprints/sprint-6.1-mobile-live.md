# Sprint 6.1 — Mobile Live · Blueprint

**Version:** 1  
**Sprint:** 6.1  
**Status:** In progress  
**Depends on:** Sprint 6.0 LiveSession API  
**ADR:** [ADR-019](../07_ADR.md#adr-019-live-as-content--livesession)

## Goal

Ship Flutter live discovery + go-live + room UI on top of existing `/live/*` APIs. Chrome uses a LIVE video placeholder (no Agora yet).

## In scope

| Route | Notes |
|-------|--------|
| `/live` | Active sessions list |
| `/live/go` | Seller start (title + optional products) |
| `/live/:id` | Room: chat poll 3s, pin (host), viewer metrics join/leave, pinned product chips → `/products/:id` |
| Feed AppBar | Live now shortcut |
| Seller dashboard | Go Live CTA |

## Out of scope

- Agora RTC SDK (native later)
- In-stream checkout attribution (6.2)
- Replay / analytics dashboards

## Release

- Branch: `feature/sprint-6.1-mobile-live`  
- API: Render on Sprint 6.0 branch is enough  
