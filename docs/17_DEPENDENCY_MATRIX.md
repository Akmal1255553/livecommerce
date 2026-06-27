# Module Dependency Matrix

Version: 1.0  
Project: LiveCommerce Platform  
Audit Type: Architecture Freeze v1.0 — Task 4  
Date: 2026-06-27  
Status: Complete

---

## Purpose

Define explicit dependencies between all 18 business modules. Used for sprint ordering, impact analysis, and preventing circular coupling.

**Legend:**
- **Hard** — Module cannot function without dependency
- **Soft** — Module degrades gracefully without dependency
- **Future** — Post-MVP dependency
- **Event** — Dependency via domain events only (decoupled)

---

## Dependency Matrix

Rows depend on columns. `●` = Hard, `○` = Soft, `◐` = Event-only, `—` = No dependency

| Module ↓ depends on → | Auth | Users | Follow | Video | Feed | Rec | Product | Cat | Order | Pay | Seller | Live | Msg | Notif | Search | Analyt | Admin | AI |
|------------------------|------|-------|--------|-------|------|-----|---------|-----|-------|-----|--------|------|-----|-------|--------|--------|-------|-----|
| **Authentication** | — | ● | — | — | — | — | — | — | — | — | — | — | — | — | — | — | — | — |
| **Users / Profiles** | ● | — | — | — | — | — | — | — | — | — | — | — | — | — | — | — | ○ | — |
| **Followers** | ● | ● | — | — | — | — | — | — | — | — | — | — | — | ◐ | — | — | — | — |
| **Videos** | ● | ● | — | — | — | — | ○ | — | — | — | — | — | — | ◐ | ○ | — | ○ | ◐ |
| **Feed** | ○ | — | ○ | ● | — | ● | — | — | — | — | — | — | — | — | — | — | — | ○ |
| **Recommendations** | ○ | ○ | ○ | ● | — | — | ○ | — | ○ | — | — | — | — | — | — | — | — | ◐ |
| **Products** | — | — | — | ○ | — | — | — | ● | — | — | ● | — | — | — | — | — | ○ | ◐ |
| **Categories** | — | — | — | — | — | — | — | — | — | — | — | — | — | — | — | — | ● | — |
| **Orders** | ● | ● | — | — | — | — | ● | — | — | ● | ● | — | — | ◐ | — | — | — | — |
| **Payments** | — | — | — | — | — | — | — | — | ● | — | — | — | — | — | — | — | — | — |
| **Seller** | ● | ● | — | ○ | — | — | ● | ● | ● | — | — | ○ | — | — | — | ○ | ◐ | — |
| **Live Streaming** | ● | — | — | — | — | — | ● | — | — | — | ● | — | — | ◐ | — | — | ○ | — |
| **Messaging** | ● | ● | ○ | — | — | — | — | — | ○ | — | ○ | — | — | ◐ | — | — | — | — |
| **Notifications** | ● | ● | — | — | — | — | — | — | — | — | — | — | — | — | — | — | — | — |
| **Search** | — | ○ | — | ● | — | — | ● | ○ | — | — | — | — | — | — | — | — | — | ◐ |
| **Analytics** | — | — | — | ● | — | — | ● | — | ● | — | ● | ● | — | — | — | — | ○ | — |
| **Admin** | ● | ● | — | ● | — | — | ● | ● | ● | ○ | ● | ● | ○ | — | — | ○ | — | ○ |
| **AI** | — | — | — | ● | ○ | ● | ● | — | ○ | — | — | — | — | — | ○ | — | — | — |

---

## Per-Module Dependency Detail

### 1. Authentication

| Depends On | Type | Reason |
|------------|------|--------|
| — | — | Root module; no upstream dependencies |

| Depended On By | Type |
|----------------|------|
| Users, Followers, Videos, Orders, Live, Messaging, Notifications, Admin | Hard |
| Feed, Recommendations | Soft |

---

### 2. Users / Profiles

| Depends On | Type | Reason |
|------------|------|--------|
| Authentication | Hard | User record created during registration |

| Depended On By | Type |
|----------------|------|
| Followers, Videos, Orders, Seller, Messaging, Notifications | Hard |
| Recommendations, Search, Admin | Soft |

---

### 3. Followers (Social Graph)

| Depends On | Type | Reason |
|------------|------|--------|
| Authentication | Hard | Actor must be authenticated |
| Users | Hard | Follow relationship between users |

| Depended On By | Type |
|----------------|------|
| Feed (following feed) | Soft |
| Recommendations | Soft |
| Notifications | Event (UserFollowed) |
| Messaging (block check) | Soft |

---

### 4. Videos

| Depends On | Type | Reason |
|------------|------|--------|
| Authentication | Hard | Uploader identity |
| Users | Hard | Video belongs to user |
| Products | Soft | Product tagging (optional) |
| Notifications | Event | Like/comment notifications |
| Search | Soft | Indexed for search |
| Admin | Soft | Moderation |
| AI | Event | Moderation job (Phase 2) |

| Depended On By | Type |
|----------------|------|
| Feed | Hard |
| Recommendations | Hard |
| Search | Hard |
| Analytics | Hard |
| Admin | Soft |
| AI | Hard (Phase 2) |

---

### 5. Feed

| Depends On | Type | Reason |
|------------|------|--------|
| Videos | Hard | Feed displays videos |
| Recommendations | Hard | Ranking logic |
| Followers | Soft | Following feed filter |
| Authentication | Soft | Personalization requires user |
| AI | Soft | ML ranking (Phase 2) |

| Depended On By | Type |
|----------------|------|
| — | — |

---

### 6. Recommendations

| Depends On | Type | Reason |
|------------|------|--------|
| Videos | Hard | Candidates to rank |
| Users | Soft | Personalization signals |
| Followers | Soft | Social graph signals |
| Products | Soft | Product recommendations |
| Orders | Soft | Purchase history |
| AI | Event | ML model (Phase 2) |

| Depended On By | Type |
|----------------|------|
| Feed | Hard |

---

### 7. Products

| Depends On | Type | Reason |
|------------|------|--------|
| Categories | Hard | Product categorization |
| Seller (Store) | Hard | Product belongs to store |
| Videos | Soft | Shoppable video tags (reverse ref) |
| Admin | Soft | Category approval |
| AI | Event | Description generation (Phase 2) |

| Depended On By | Type |
|----------------|------|
| Orders | Hard |
| Live Streaming | Hard |
| Videos | Soft |
| Search | Hard |
| Analytics | Hard |
| Recommendations | Soft |
| AI | Hard (Phase 2) |

---

### 8. Categories

| Depends On | Type | Reason |
|------------|------|--------|
| Admin | Hard | Admin manages categories |

| Depended On By | Type |
|----------------|------|
| Products | Hard |
| Seller | Soft |
| Search | Soft |

---

### 9. Orders

| Depends On | Type | Reason |
|------------|------|--------|
| Authentication | Hard | Buyer identity |
| Users | Hard | Buyer profile |
| Products | Hard | Order items |
| Payments | Hard | Checkout completion |
| Seller | Hard | Order belongs to store |
| Notifications | Event | Order status notifications |

| Depended On By | Type |
|----------------|------|
| Payments | Hard |
| Analytics | Hard |
| Messaging | Soft |
| Recommendations | Soft |
| Admin | Soft |

---

### 10. Payments

| Depends On | Type | Reason |
|------------|------|--------|
| Orders | Hard | Payment linked to order |

| Depended On By | Type |
|----------------|------|
| Orders | Hard (checkout calls payment) |

**Note:** Orders ↔ Payments is a **collaboration**, not a circular dependency. Call flow: Orders → Payment adapter → webhook → Orders (via event).

---

### 11. Seller

| Depends On | Type | Reason |
|------------|------|--------|
| Authentication | Hard | Seller is a user |
| Users | Hard | Profile, role |
| Products | Hard | Manages products |
| Categories | Soft | Store category |
| Orders | Hard | Manages orders |
| Videos | Soft | Seller content |
| Analytics | Soft | Dashboard data |
| Admin | Event | Seller verification |

| Depended On By | Type |
|----------------|------|
| Products | Hard |
| Orders | Hard |
| Live Streaming | Hard |
| Messaging | Soft |
| Analytics | Hard |
| Admin | Soft |

---

### 12. Live Streaming

| Depends On | Type | Reason |
|------------|------|--------|
| Authentication | Hard | Seller identity |
| Seller | Hard | Active store required |
| Products | Hard | Pinned products |
| Notifications | Event | Notify followers on go-live |
| Admin | Soft | Moderation |

| Depended On By | Type |
|----------------|------|
| Analytics | Hard |

**External:** Agora SDK (streaming provider adapter)

---

### 13. Messaging

| Depends On | Type | Reason |
|------------|------|--------|
| Authentication | Hard | Sender identity |
| Users | Hard | Participants |
| Followers | Soft | Block check |
| Orders | Soft | Order-linked chat |
| Seller | Soft | Seller conversations |
| Notifications | Event | New message push |

| Depended On By | Type |
|----------------|------|
| — | — |

---

### 14. Notifications

| Depends On | Type | Reason |
|------------|------|--------|
| Authentication | Hard | Recipient identity |
| Users | Hard | User devices, preferences |

| Depended On By | Type |
|----------------|------|
| Followers, Videos, Orders, Live, Messaging | Event (consumers dispatch notifications) |

**Pattern:** Notifications is a **leaf module** — many modules write to it; it depends on almost nothing.

---

### 15. Search

| Depends On | Type | Reason |
|------------|------|--------|
| Videos | Hard | Search videos |
| Products | Hard | Search products |
| Users | Soft | Search users |
| Categories | Soft | Filter by category |
| AI | Event | Semantic search (Phase 2) |

| Depended On By | Type |
|----------------|------|
| — | — |

---

### 16. Analytics

| Depends On | Type | Reason |
|------------|------|--------|
| Orders | Hard | Revenue data |
| Products | Hard | Product performance |
| Videos | Hard | View/engagement data |
| Seller | Hard | Store-level aggregation |
| Live Streaming | Hard | Stream metrics |
| Admin | Soft | Platform overview |

| Depended On By | Type |
|----------------|------|
| Seller (dashboard) | Soft |
| Admin | Soft |

---

### 17. Admin

| Depends On | Type | Reason |
|------------|------|--------|
| Authentication | Hard | Admin auth |
| Users | Hard | User management |
| Videos | Soft | Content moderation |
| Products | Soft | Product review |
| Categories | Hard | Category CRUD |
| Orders | Soft | Order oversight |
| Payments | Soft | Refund approval |
| Seller | Hard | Seller verification |
| Live Streaming | Soft | Stream moderation |
| Messaging | Soft | Reported messages |
| Analytics | Soft | Platform metrics |
| AI | Soft | Moderation queue (Phase 2) |

| Depended On By | Type |
|----------------|------|
| Categories | Hard |
| Seller | Event (verification) |

---

### 18. AI

| Depends On | Type | Reason |
|------------|------|--------|
| Videos | Hard | Content moderation, subtitles |
| Products | Hard | Description generation |
| Recommendations | Soft | Feed ranking output |
| Search | Soft | Semantic search |
| Orders | Soft | Fraud detection (Phase 3) |

| Depended On By | Type |
|----------------|------|
| Recommendations | Event (Phase 2) |
| Videos | Event (moderation) |
| Products | Event (generation) |
| Search | Event (Phase 2) |
| Admin | Soft (moderation queue) |

---

## Sprint Dependency Order (Validated)

```
Sprint 0: Foundation
    ↓
Sprint 1: Auth → Users
    ↓
Sprint 2: Followers, Notifications, Search(users)
    ↓
Sprint 3: Videos → Feed → Recommendations(basic)
    ↓
Sprint 4: Categories → Products → Orders(cart)
    ↓
Sprint 5: Seller
    ↓
Sprint 6: Live Streaming (needs Products + Seller)
    ↓
Sprint 7: Payments (needs Orders)
    ↓
Sprint 8: Messaging (needs Users, Seller, Orders)
    ↓
Sprint 9–11: AI (needs Videos, Products, Recommendations)
    ↓
Sprint 12: Analytics (needs Orders, Videos, Seller, Live)
    ↓
Sprint 13: Growth (needs Auth, Orders)
    ↓
Sprint 14: Admin (needs all core modules)
    ↓
Sprint 15–16: Scaling + Launch
```

**No circular sprint dependencies detected.**

---

## External Service Dependencies

| Module | External Service | Required MVP? | Adapter Interface |
|--------|-----------------|---------------|-------------------|
| Authentication | SMS Provider | Yes (OTP) | `SmsProviderInterface` |
| Videos | S3 / MinIO / R2 | Yes | Laravel Filesystem |
| Videos | CDN (Cloudflare) | Yes | CDN URLs in MediaService |
| Videos | FFmpeg | Sprint 15 | ProcessVideoJob |
| Live Streaming | Agora | Yes | `StreamingProviderInterface` |
| Payments | Click/Payme/Uzum | Yes (Sprint 7) | `PaymentGatewayInterface` |
| Notifications | FCM | Yes | `PushNotificationInterface` |
| Auth (email) | Mailpit (local) / SES (prod) | Yes | Laravel Mailer |
| AI | OpenAI / local ML | No (Phase 2) | `AiProviderInterface` |
| Search | Elasticsearch | No (Phase 2) | Future adapter |
| Monitoring | Sentry | Sprint 15 | SDK integration |

---

## Coupling Risk Assessment

| Module Pair | Coupling Level | Risk | Mitigation |
|-------------|---------------|------|------------|
| Orders ↔ Payments | Tight collaboration | Medium | Event-driven webhook; interface adapter |
| Feed ↔ Recommendations | Tight | Low | Recommendations is swappable (rules → ML) |
| Videos ↔ Products | Loose (tagging) | Low | Pivot table only |
| Seller ↔ Products | Tight | Low | Natural domain boundary |
| Admin → All | Read/manage | Medium | Admin uses service APIs, not direct DB |
| Notifications ← All | Event fan-in | Low | Leaf module pattern |
| AI → Videos/Products | Async jobs | Low | Graceful fallback |

---

## Document Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-06-27 | Architecture Review | Initial dependency matrix for 18 modules |

---

**Related:** [Modules](../08_MODULES.md) · [Validation Report](./14_VALIDATION_REPORT.md) · [Architecture Freeze Report](./18_ARCHITECTURE_FREEZE_REPORT.md)
