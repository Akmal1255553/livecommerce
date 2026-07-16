# Sprint 6.2 — Commerce inside Live · Blueprint

**Version:** 1  
**Sprint:** 6.2  
**Status:** Ready for deploy (`feature/sprint-6.2-live-commerce`)  
**Depends on:** Sprint 6.0 API + Sprint 6.1 mobile live UI  
**ADR:** [ADR-019](../07_ADR.md#adr-019-live-as-content--livesession)

## Goal

Let viewers add **pinned** products to cart from the live room without leaving the stream. Record attribution via analytics events and `commerce` chat messages.

## In scope

| Area | Notes |
|------|--------|
| API | `POST /live/{id}/add-to-cart` — auth required |
| Rules | Product must be **pinned** on active session; uses existing `CartService` |
| Attribution | `live_analytics_events.product_added_to_cart` with `offset_seconds` |
| Chat | `commerce` message: "{name} added {product} to cart" |
| Mobile | `+` on pinned chip, cart badge in room header, snackbar on success |

## Out of scope

- Checkout from live without leaving (checkout screen still separate)
- `purchase_from_live` / order-line attribution (Sprint 6.4+)
- Agora SDK

## API

```http
POST /api/v1/live/{id}/add-to-cart
Authorization: Bearer …
Content-Type: application/json

{ "product_id": "uuid", "quantity": 1 }
```

Response:

```json
{
  "data": {
    "cart": { … },
    "chat_message": { "type": "commerce", … }
  }
}
```

## Release

- Branch: `feature/sprint-6.2-live-commerce`  
- Deploy: push → switch Render branch to this feature branch (or merge to 6.0 line)  
- Mobile: hot restart after pull

## Test plan

1. Seller goes live, pins a product  
2. Buyer joins room, taps `+` on pinned chip  
3. Cart badge increments; commerce message appears in chat  
4. Unpinned product → 404 from add-to-cart API
