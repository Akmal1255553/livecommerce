# Master Plan

Version: 1.0  
Project: LiveCommerce Platform  
Status: Architecture Phase  
Document Owner: Founder & CTO  
Last Updated: 2026-06-27

---

## Purpose

This document is the **single source of truth** for the entire LiveCommerce project. It connects business goals, architecture, modules, roadmap, AI strategy, and development process into one unified vision.

Every engineering decision must align with this plan. When documents conflict, this Master Plan takes precedence, followed by the PRD.

---

## Document Map

| # | Document | Purpose |
|---|----------|---------|
| — | [PRD](./docs01_PRD.md) | Business requirements, MVP scope, personas |
| — | [Project Context](./PROJECT_CONTEXT.md) | Tech stack, philosophy, coding standards overview |
| 07 | [ADR](./07_ADR.md) | Architecture decision records |
| 08 | [Modules](./08_MODULES.md) | Business module definitions and boundaries |
| 09 | [Event Flow](./09_EVENT_FLOW.md) | Domain events and async processing |
| 10 | [State Machines](./10_STATE_MACHINES.md) | Entity lifecycle rules |
| 11 | [Design System](./11_DESIGN_SYSTEM.md) | Visual language and UI standards |
| 12 | **Master Plan** (this doc) | Unified project vision |
| 13 | [Roadmap](./13_ROADMAP.md) | Sprint plan and timeline |
| 02 | [System Architecture](./docs/02_SYSTEM_ARCHITECTURE.md) | High-level system design |
| 03 | [Database Design](./docs/03_DATABASE_DESIGN.md) | Schema, ER diagram, indexes |
| 04 | [API Specification](./docs/04_API_SPECIFICATION.md) | REST endpoints and contracts |
| 05 | [Project Structure](./docs/05_PROJECT_STRUCTURE.md) | Folder structure and conventions |
| 06 | [Engineering Rules](./docs/06_ENGINEERING_RULES.md) | Coding standards, git, CI/CD |
| 14 | [Validation Report](./docs/14_VALIDATION_REPORT.md) | Cross-document audit |
| 15 | [Repository Tree](./docs/15_REPOSITORY_TREE.md) | Final repo structure |
| 16 | [Technology Audit](./docs/16_TECHNOLOGY_AUDIT.md) | Stack compatibility |
| 17 | [Dependency Matrix](./docs/17_DEPENDENCY_MATRIX.md) | Module dependencies |
| 18 | [Architecture Freeze Report](./docs/18_ARCHITECTURE_FREEZE_REPORT.md) | Freeze approval v1.0 |

---

## Project Vision

Build the world's most engaging **AI-powered Live Commerce platform** where entertainment and shopping become one seamless experience.

Users discover products naturally while watching short-form videos and live streams — never feeling like they are "shopping."

---

## Mission

Combine the engagement of TikTok, the commerce of TikTok Shop, and the store management of Shopify into a single mobile-first platform designed for Central Asia, starting with Uzbekistan.

---

## Business Goals

| Goal | Metric | Target (Year 1) |
|------|--------|-----------------|
| User acquisition | DAU | 50,000 |
| Engagement | Avg session duration | 25+ minutes |
| Revenue | GMV | $5M equivalent (UZS) |
| Retention | D7 retention | 30% |
| Commerce | Cart conversion rate | 8% |
| Sellers | Active sellers | 500 |
| Content | Videos uploaded/day | 1,000 |

---

## Technical Goals

| Goal | Target |
|------|--------|
| API response time (p95) | < 200ms reads, < 500ms writes |
| App cold start | < 2 seconds |
| Feed load time | < 500ms (cached) |
| Scroll performance | 60 FPS |
| Uptime | 99.9% |
| Test coverage (services) | 80%+ |
| Zero-downtime deploys | Blue-green or rolling |

---

## Target Market

| Phase | Market | Languages | Currency |
|-------|--------|-----------|----------|
| Launch (v1.0) | Uzbekistan | Uzbek, Russian | UZS |
| v1.5 | Kazakhstan, Kyrgyzstan | + Kazakh | KZT, KGS |
| v2.0 | Turkey, Azerbaijan | + Turkish | TRY, AZN |
| v3.0 | Middle East, Europe | + Arabic, English | Multi |

**Primary audience:** 18–40 years old, mobile-native, interested in fashion, electronics, beauty, and daily shopping.

---

## Competitive Advantages

1. **Video-first commerce** — Every product is discovered through entertainment, not search.
2. **Live shopping native** — Live streams with pinned products built into the core, not bolted on.
3. **AI-powered** — Recommendations, moderation, seller tools, and content enhancement from Phase 2.
4. **Central Asia focus** — Local payment gateways, languages, and seller onboarding for Uzbekistan.
5. **Mobile-only MVP** — Faster iteration, better UX than responsive web competitors.
6. **Clean architecture** — Built to scale to millions without rewrite.

---

## Product Philosophy

1. The platform must **never feel like a marketplace**. It must feel like TikTok.
2. Shopping happens **naturally** during content consumption.
3. Every action requires **minimum taps** (max 3 from video to cart).
4. **Entertainment comes before selling.**
5. **Videos are primary.** Products are secondary.
6. Every feature must answer: *"Does this make discovering, trusting, or buying products easier without reducing entertainment value?"*

---

## Core Principles

| # | Principle | Implementation |
|---|-----------|----------------|
| 1 | Mobile First | Flutter app is the only MVP client |
| 2 | Video First | Feed is the home screen; all flows start from content |
| 3 | AI First | AI adapter layer from day one; features added incrementally |
| 4 | Performance First | 60 FPS scroll, < 2s startup, CDN for all media |
| 5 | Simplicity | Max 3 taps for common actions; minimal UI chrome |
| 6 | Clean Architecture | Strict layer separation on mobile and backend |
| 7 | Event-Driven | Side effects via domain events and queues |
| 8 | Provider Abstraction | Streaming, payments, AI, SMS all behind interfaces |

---

## Architecture Overview

```
┌─────────────────────────────────────────────────┐
│                  Mobile App                      │
│         Flutter + Riverpod + Clean Arch          │
└──────────────────────┬──────────────────────────┘
                       │ HTTPS REST (JWT)
┌──────────────────────▼──────────────────────────┐
│              Load Balancer + CDN                 │
└──────────────────────┬──────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────┐
│           Laravel 12 Modular Monolith            │
│   Controllers → Services → Repositories → DB     │
│   Events → Listeners → Jobs → Queues             │
└──────┬──────────┬──────────┬────────────────────┘
       │          │          │
  PostgreSQL    Redis     S3 + CDN
       │
  External: Agora, Payment Gateway, FCM, SMS, AI
```

**Key decisions:** See [ADR Index](./07_ADR.md)

---

## Modules Overview

18 business modules with strict boundaries:

| Module | Sprint | Priority |
|--------|--------|----------|
| Authentication | 1 | P0 |
| Users / Profiles | 1 | P0 |
| Followers | 2 | P0 |
| Videos | 3 | P0 |
| Feed | 3 | P0 |
| Recommendations | 3 (rules), 9 (ML) | P0/P1 |
| Products | 4 | P0 |
| Categories | 4 | P0 |
| Orders | 4 | P0 |
| Payments | 7 | P0 |
| Seller | 5 | P0 |
| Live Streaming | 6 | P0 |
| Notifications | 2 | P0 |
| Search | 2–4 | P0 |
| Messaging | 8 | P1 |
| Analytics | 12 | P1 |
| Admin | 14 | P0 (core) |
| AI | 9–11 | P1 |

Full details: [Module Definitions](./08_MODULES.md)

---

## Roadmap Summary

| Phase | Sprints | Timeline | Deliverable |
|-------|---------|----------|-------------|
| Engineering Foundation | 0 | Week 1–2 | Docs, repo scaffold, CI |
| Core Platform | 1 | Week 3–4 | Auth, profiles, settings |
| Social & Engagement | 2 | Week 5–6 | Follow, notifications, search |
| Video Platform | 3 | Week 7–8 | Upload, feed, likes, comments |
| Commerce | 4–5, 7 | Week 9–14 | Products, cart, checkout, seller |
| Live Commerce | 6 | Week 13–14 | Live streaming, pinned products |
| Communication | 8 | Week 15–16 | Messaging |
| AI | 9–11 | Week 17–22 | ML ranking, moderation, AI seller/video |
| Analytics & Admin | 12, 14 | Week 21–24 | Dashboards, moderation |
| Growth | 13 | Week 23–24 | Referrals, coupons, campaigns |
| Scaling & Launch | 15–16 | Week 25–32 | Performance, production release |

**v1.0 target:** ~8–9 months from Sprint 0 start.

Full details: [Product Roadmap](./13_ROADMAP.md)

---

## Sprint Overview (MVP Critical Path)

```
Sprint 0  → Foundation (docs ✔, repo ☐)
Sprint 1  → Auth & Users
Sprint 2  → Social & Notifications
Sprint 3  → Video Platform & Feed
Sprint 4  → Marketplace (products, cart, orders)
Sprint 5  → Seller Platform
Sprint 6  → Live Commerce
Sprint 7  → Payments
Sprint 14 → Admin & Moderation (core)
Sprint 15 → Scaling & Performance
Sprint 16 → Production Release v1.0
```

---

## Engineering Standards

| Area | Standard | Document |
|------|----------|----------|
| Backend coding | PSR-12, PHPStan Level 6 | [Engineering Rules](./docs/06_ENGINEERING_RULES.md) |
| Mobile coding | flutter_lints, dart format | [Engineering Rules](./docs/06_ENGINEERING_RULES.md) |
| Architecture | Clean Architecture, SOLID | [System Architecture](./docs/02_SYSTEM_ARCHITECTURE.md) |
| API design | REST, versioned, JSON envelope | [API Specification](./docs/04_API_SPECIFICATION.md) |
| Database | PostgreSQL, UUID, soft deletes | [Database Design](./docs/03_DATABASE_DESIGN.md) |
| Git | Conventional Commits, feature branches | [Engineering Rules](./docs/06_ENGINEERING_RULES.md) |
| Testing | 80% services, all endpoints tested | [Engineering Rules](./docs/06_ENGINEERING_RULES.md) |
| UI | Design system tokens, 44dp targets | [Design System](./11_DESIGN_SYSTEM.md) |
| Events | Event-driven side effects | [Event Flow](./09_EVENT_FLOW.md) |
| State | Documented state machines | [State Machines](./10_STATE_MACHINES.md) |

---

## Security Strategy

| Layer | Approach |
|-------|----------|
| Transport | TLS 1.2+ everywhere |
| Authentication | JWT (15 min access + 30 day refresh with rotation) |
| Authorization | Laravel Policies, role middleware |
| Input | Form Request validation on every write endpoint |
| Rate limiting | Redis-backed: 60/min auth, 20/min guest |
| Payments | PCI delegated to gateway; no card data stored |
| Uploads | Pre-signed S3 URLs; MIME + size validation |
| Audit | All admin/seller actions logged |
| Encryption | Phone numbers encrypted at rest |
| Secrets | Environment variables only; never in code |

---

## AI Strategy

### Philosophy

AI is integrated via an **adapter pattern** from day one. Core features work without AI. AI enhances but never blocks.

### Phases

| Phase | Sprint | Features |
|-------|--------|----------|
| MVP | 3 | Rule-based recommendations and trending |
| Phase 2 | 9 | ML feed ranking, content moderation, AI search |
| Phase 2 | 10 | AI product descriptions, titles, tags, pricing |
| Phase 2 | 11 | Auto captions, translation, thumbnails, highlights |
| Phase 3 | Future | Seller assistant, voice translation, fraud detection, shopping agent |

### Rules

1. All AI calls are **async** (queued jobs).
2. AI failures **fail gracefully** — fallback to rule-based.
3. AI outputs are **stored** (not re-computed).
4. Provider is **swappable** via `AiProviderInterface`.

Full details: [System Architecture § AI Integration](./docs/02_SYSTEM_ARCHITECTURE.md)

---

## Deployment Strategy

| Environment | Purpose | Infrastructure |
|-------------|---------|----------------|
| local | Development | Docker Compose |
| staging | QA, demos | Mirrors production (reduced scale) |
| production | Live users | Multi-AZ, auto-scaling, managed DB |

**Deploy flow:** PR → CI (lint, test, build) → merge to develop → deploy staging → smoke test → manual approval → deploy production.

**MVP infrastructure:** 2 API servers, 2 workers, 1 PostgreSQL primary + 1 replica, 1 Redis, S3/R2 + CDN.

Full details: [System Architecture § Infrastructure](./docs/02_SYSTEM_ARCHITECTURE.md)

---

## Risk Register

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Low seller adoption | High | Medium | Pre-launch anchor sellers, zero commission 3 months |
| Payment integration delays | High | Medium | Stub in Sprint 4, full in Sprint 7 |
| Video CDN costs at scale | High | Low (early) | Upload limits, adaptive bitrate, CDN in Sprint 15 |
| Small team bottleneck | High | High | Strict sprint scope, parallel backend/mobile |
| Streaming provider issues | Medium | Low | Abstracted provider, test Agora early |
| App store rejection | Medium | Low | Follow guidelines from Sprint 1 |
| Content moderation gap | Medium | Medium | Manual queue Sprint 14, AI Sprint 9 |
| Scope creep | High | High | MVP boundary enforced, post-MVP backlog |

---

## Success Metrics

### User Metrics

| Metric | MVP Target | Year 1 Target |
|--------|------------|-----------------|
| DAU | 1,000 (beta) | 50,000 |
| MAU | 5,000 (beta) | 200,000 |
| Avg session duration | 15 min | 25 min |
| D1 retention | 40% | 45% |
| D7 retention | 20% | 30% |
| D30 retention | 10% | 15% |

### Commerce Metrics

| Metric | MVP Target | Year 1 Target |
|--------|------------|-----------------|
| Orders/day | 50 (beta) | 2,000 |
| Average order value | 200,000 UZS | 250,000 UZS |
| Cart conversion | 5% | 8% |
| Checkout conversion | 60% | 75% |
| Repeat purchase rate | 10% | 25% |

### Content Metrics

| Metric | MVP Target | Year 1 Target |
|--------|------------|-----------------|
| Videos uploaded/day | 50 (beta) | 1,000 |
| Live streams/day | 10 (beta) | 100 |
| Avg stream duration | 20 min | 30 min |
| Products sold per stream | 3 | 10 |

---

## Future Versions

### v2.0 (Months 9–18)
Cross-border commerce, AI shopping assistant, AR preview, video calls, creator subscriptions, social login, Elasticsearch, WebSocket chat.

### v3.0 (Year 2+)
Global marketplace, voice commerce, AI shopping agent, public API, developer platform, virtual gifts, multi-region deployment.

Full details: [Roadmap § Future Versions](./13_ROADMAP.md)

---

## Architecture Freeze Policy

1. **Architecture Phase (current):** Documents are written and reviewed. No production code.
2. **Freeze point:** Architecture is frozen when all docs (07–13, 02–06) are approved by Founder & CTO.
3. **After freeze:** Implementation follows documents. Changes require ADR.
4. **Change process:** Propose ADR → review → update affected documents → implement.
5. **Emergency changes:** Hotfix can skip ADR but must be documented within 48 hours.

**Current status:** Architecture Freeze v1.0 **APPROVED** (2026-06-27). Documentation consistency 100%. P0 issues resolved. Sprint 0 implementation authorized.

---

## Decision-Making Process

| Decision Type | Who Decides | Process |
|---------------|-------------|---------|
| Product scope | Founder & CTO | PRD update + roadmap adjustment |
| Architecture | Founder & CTO + lead dev | ADR required |
| Technology choice | Lead dev proposes, CTO approves | ADR required |
| API contract change (breaking) | CTO + mobile/backend leads | API Spec update + version bump |
| Database schema change | Backend lead | Database Design update + migration |
| UI/UX change | Design lead + CTO | Design System update |
| Sprint scope change | CTO | Roadmap update, stakeholder approval |
| Emergency hotfix | On-call dev | Fix → document within 48h |

---

## Long-Term Vision

**Year 1:** Launch in Uzbekistan. Prove product-market fit with video + live commerce. 50K DAU, 500 sellers, $5M GMV.

**Year 2:** Expand to Central Asia (Kazakhstan, Kyrgyzstan). AI-powered platform. Creator economy features. 500K DAU.

**Year 3:** Turkey and Middle East entry. Global marketplace capabilities. Public API. 2M+ DAU.

**Year 5:** Top 3 live commerce platform in Central Asia and Middle East. AI shopping agent. Developer ecosystem. IPO-ready metrics.

---

## Current Status

| Area | Status |
|------|--------|
| PRD | ✔ Complete |
| Project Context | ✔ Complete |
| System Architecture | ✔ Complete |
| Database Design | ✔ Complete |
| API Specification | ✔ Complete |
| Project Structure | ✔ Complete |
| Engineering Rules | ✔ Complete |
| ADR (07) | ✔ Complete (12 decisions) |
| Modules (08) | ✔ Complete (18 modules) |
| Event Flow (09) | ✔ Complete (16 events) |
| State Machines (10) | ✔ Complete (10 entities) |
| Design System (11) | ✔ Complete |
| Master Plan (12) | ✔ Complete |
| Roadmap (13) | ✔ Complete |
| Repository scaffold | ☐ Pending |
| Docker environment | ☐ Pending |
| CI/CD pipelines | ☐ Pending |
| **Architecture freeze** | ☑ Conditional (see [Freeze Report](./docs/18_ARCHITECTURE_FREEZE_REPORT.md)) |

**Next action:** Apply 7 P0 documentation patches → Sprint 0 scaffold → formal sign-off → begin Sprint 1.

---

## Document Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-06-27 | Founder & CTO | Initial master plan connecting all project documents |

---

**This document must always reflect the current direction of the project and be updated whenever significant product or technical decisions are made.**
