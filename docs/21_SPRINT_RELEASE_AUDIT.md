# Sprint Release Audit

**Version:** 1  
**Status:** Approved process  
**Applies to:** Every major sprint release (`4.5`, `4.5M`, `5.0`, `6.0`, …)  
**Owner:** Founder & CTO  
**Last updated:** 2026-07-03

---

## Purpose

After each major sprint, run a short **Release Audit** before tagging and before starting the next sprint. This prevents the codebase, API spec, and mobile client from drifting apart as the project grows.

**Rule:** No sprint **N+1** starts until sprint **N** is released **and** its Release Audit is signed off (see [Quality gates](#quality-gates-sign-off)).

---

## Audit types

| # | Audit | Question answered | Primary artifacts |
|---|--------|-------------------|-------------------|
| 1 | **Architecture Audit** | Does code match ADR and Blueprint? | `07_ADR.md`, `blueprints/`, `08_MODULES.md`, `23_COMMERCE_CORE_ARCHITECTURE.md` |
| 2 | **API Audit** | Does `04_API_SPECIFICATION.md` match real routes and contracts? | `routes/api.php`, Scramble/OpenAPI, Pest Feature tests |
| 3 | **Mobile Audit** | Does mobile call real APIs (no stubs / hardcoded mocks in prod paths)? | `mobile/lib/features/`, `api_constants.dart` |
| 4 | **E2E Smoke Test** | Does the full user journey work end-to-end? | Manual script or automated integration test |

---

## 1. Architecture Audit

**When:** Every major sprint release.

**Checklist:**

- [ ] All new behaviour implemented in **Services** + **Repositories** (no business logic in controllers)
- [ ] State transitions go through documented state machines (e.g. ADR-017 `OrderStateMachine`)
- [ ] Events dispatched for side effects; listeners registered once in `RepositoryServiceProvider`
- [ ] Module contracts in `app/Contracts/Services/`; implementations bound in service provider
- [ ] Blueprint **In scope / Out of scope** respected — no scope creep without ADR
- [ ] New cross-module coupling documented in ADR if touching ≥2 bounded contexts
- [ ] `phpstan` + `pint` + full Pest suite green (`scripts/qa.ps1` or Docker `composer qa`)

**Output:** Short note in PR / release ticket: ADR + blueprint compliance ✅ or list of exceptions with ADR ticket.

---

## 2. API Audit

**When:** Every major sprint release (mandatory if sprint adds or changes HTTP endpoints).

**Checklist:**

- [ ] Export live routes: `php artisan route:list --path=api/v1` (see `scripts/sprint-audit.ps1`)
- [ ] Every new/changed endpoint documented in `docs/04_API_SPECIFICATION.md`
- [ ] Request/response shapes match `*Resource` classes and Pest Feature tests
- [ ] Auth middleware correct (`auth.api`, `seller`, `auth.api.optional`)
- [ ] Error envelopes consistent (`ApiResponse`, domain exceptions → HTTP status)
- [ ] Idempotency / concurrency headers documented where required (`Idempotency-Key`, `If-Match`, `cart_version`)
- [ ] No “spec-only” endpoints — every path in the spec exists in `routes/api.php` **or** is explicitly marked *Planned*

**Output:** Diff note: spec sections updated; list any intentional spec-ahead items.

---

## 3. Mobile Audit

**When:** Every sprint that ships or changes mobile features (`4.5M`, `5.0`, …).

**Checklist:**

- [ ] New screens use `ApiClient` / repositories — **no** hardcoded product/order data in presentation layer
- [ ] Endpoints match `04_API_SPECIFICATION.md` and `mobile/lib/core/constants/api_constants.dart`
- [ ] Auth tokens and guest cart headers wired (`Authorization`, `X-Guest-Cart-Token`)
- [ ] Error handling uses shared `error_handler` / failure types
- [ ] `flutter analyze` clean
- [ ] Widget or integration tests for new critical flows

**Output:** Screen → API endpoint mapping table in sprint PR.

---

## 4. E2E Smoke Test

**When:**

| Scope | Trigger |
|-------|---------|
| **Sprint-scoped smoke** | After backend-only sprints (e.g. 4.5) — API/curl or Pest journey |
| **Full Commerce MVP smoke** | After **Phase A** (4.5 + 4.5M) — required before Sprint 5 / Sprint 6 |
| **Full milestone smoke** | After Sprint 5.0, 6.0, etc. — extend script with new flows |

### Phase A — Commerce MVP (mandatory gate)

Run on staging or local Docker **after** `v0.4.5m-mobile-commerce`:

| Step | Action | Pass criteria |
|------|--------|---------------|
| 1 | Register new user | JWT issued, profile accessible |
| 2 | Open For You feed | Videos load with product overlay where tagged |
| 3 | Tap product tag | Product Page opens with correct price/stock |
| 4 | Add to cart | Cart shows line; totals match API |
| 5 | Checkout | Order created; `order_number` returned |
| 6 | Order Success | Confirmation screen shows order id |
| 7 | Order History / My Orders | Order listed; detail shows status + timeline |

**Optional (API-only path for 4.5 release):** steps 4–7 via Pest or curl before mobile ships.

**Output:** Checklist signed off in `CHANGELOG.md` release notes or GitHub Release body.

---

## Quality gates sign-off

A sprint is **release-ready** only when:

| Gate | Tool / doc |
|------|------------|
| CI green | `scripts/qa.ps1` or `docker compose exec app composer qa` |
| Architecture Audit | Checklist §1 |
| API Audit | Checklist §2 (if HTTP changed) |
| Mobile Audit | Checklist §3 (if mobile changed) |
| E2E Smoke | Checklist §4 (scope per table above) |
| Docs | `CHANGELOG.md`, `13_ROADMAP.md`, blueprint status |
| Tag | `v0.x.y-<name>` on `develop` or release branch |

---

## Recommended sequence (Commerce MVP → Live)

Architect-recommended order — **do not skip Phase A E2E**:

```
✅ Sprint 4.4  Order System (backend)          — shipped
→  Sprint 4.5   Checkout (backend)            — close commerce loop
→  Sprint 4.5M  Mobile Commerce              — mobile catches up to backend
→  Full E2E Audit (Phase A)                  — registration → feed → buy → orders
─── Commerce MVP complete ───
→  Sprint 4.6 / 5.0  Seller Center
→  Sprint 6.0        Live Streaming
→  Sprint 7.0        Payment gateways (Click/Payme)
```

**Hard rule:** Sprint 5 (Seller Platform) and Sprint 6 (Live Commerce) **blocked** until Phase A **and** Full E2E Audit pass.

---

## Automation helpers

```powershell
# Windows — QA + route export for API audit
.\scripts\sprint-audit.ps1

# Unix
./scripts/sprint-audit.sh
```

Manual sections (Architecture, Mobile, E2E) use checklists above until dedicated tooling is added.

---

## Related documents

| Document | Role |
|----------|------|
| [13_ROADMAP.md](../13_ROADMAP.md) | Milestones and sprint gates |
| [SPRINT_4_COMMERCE_PLAN.md](./SPRINT_4_COMMERCE_PLAN.md) | Phase A sub-sprints |
| [06_ENGINEERING_RULES.md](./06_ENGINEERING_RULES.md) | Coding standards + PR template |
| [04_API_SPECIFICATION.md](./04_API_SPECIFICATION.md) | API source of truth |
| [07_ADR.md](../07_ADR.md) | Architecture decisions |

---

## Revision history

| Version | Date | Change |
|---------|------|--------|
| 1.0 | 2026-07-03 | Initial Release Audit process (Phase A E2E gate) |
