# Sprint 6.5 — AI Live Assistant (rule-based MVP) · Blueprint

**Version:** 1  
**Sprint:** 6.5  
**Status:** Ready for deploy (`feature/sprint-6.5-ai-live-assistant`)  
**Depends on:** Sprint 6.0–6.4  
**ADR:** [ADR-012](../07_ADR.md#adr-012-ai-adapter-integration-strategy) (rule-based now; OpenAI in Sprint 9+)

## Goal

Help the **live host** with actionable tips during a stream: pin products, draft FAQ replies, engagement prompts — without blocking on real LLM keys.

## In scope

| Area | Notes |
|------|--------|
| Service | `LiveAssistantService` — rule engine over chat + products + metrics |
| API | `GET /live/{id}/assistant/suggestions` (seller/host only) |
| Types | `pin_product`, `reply_faq`, `engage`, `highlight` |
| Mobile | Host ✨ button → bottom sheet → Pin / draft reply into chat |

## Out of scope

- OpenAI / streaming LLM (Sprint 9+ via `AiProviderInterface`)
- Auto-posting assistant messages without host confirm
- Buyer-facing shopping agent

## Release

- Branch: `feature/sprint-6.5-ai-live-assistant`  
- Render: switch branch  
- Test: Go Live with products → ask “how much shipping?” from another account → host opens Assistant → Use / Pin
