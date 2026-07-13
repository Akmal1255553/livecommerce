# Sprint 6.0 — Live as Content (backend foundation) · Blueprint

**Version:** 1  
**Sprint:** 6.0  
**Status:** Ready for deploy (`feature/sprint-6.0-live-commerce`)  
**Phase:** Live Commerce  
**Depends on:** Sprint 5 seller platform  
**Plan:** Cursor plan `sprint_6.0_live_api`  
**ADR:** [ADR-019](../07_ADR.md#adr-019-live-as-content--livesession)

---

## 1. Goal

Ship LiveSession domain + streaming provider abstraction + mixed discover/for-you ContentItems so mobile 6.1 can go live and watch without changing discovery architecture later.

## 2. In scope

| Area | Notes |
|------|--------|
| Schema | `live_sessions`, products pin timeline, typed chat, viewer metrics/presence, analytics events |
| Provider | `StreamingProviderInterface` → Fake (default) / Agora (env) |
| Services | `LiveSessionService`, `ViewerMetricsService`, `LiveAnalyticsService` |
| HTTP | `/live/*` session control; `GET /discover` + mixed `/feed/for-you` |
| Feed | `LiveCandidateSource` + live boost in scoring |

## 3. Out of scope

- Flutter / Agora SDK → 6.1  
- In-stream checkout attribution UX → 6.2  
- Replay player → 6.3  
- Analytics dashboards → 6.4  
- AI Live Assistant → 6.5  

## 4. Release

- Branch: `feature/sprint-6.0-live-commerce`  
- Tag: `v0.6.0-live-commerce`  
- Deploy: push → Render auto-deploy on connected branch  
