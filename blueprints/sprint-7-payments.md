# Sprint 7 — Payments · Blueprint

**Version:** 1  
**Sprint:** 7  
**Status:** Ready for deploy (`feature/sprint-7-payments`)  
**Depends on:** Sprint 4 (checkout / orders)  
**ADR:** [ADR-016](../07_ADR.md#adr-016-commerce-core-cart-orders-inventory-payment), [ADR-017](../07_ADR.md#adr-017-order-state-machine-and-order-aggregate)

## Goal

Async payment: checkout starts payment → order stays `awaiting_payment` → HMAC webhook (or sandbox complete) marks `paid` / `failed`. No card data on platform.

## In scope

| Area | Notes |
|------|--------|
| Drivers | `fake` (instant paid, tests) · `local`/`click`/`payme`/`uzum` (redirect + webhook) |
| API | `POST /webhooks/payment`, `POST /payments/sandbox/{id}/complete` |
| Job | `ProcessPaymentWebhookJob` + idempotent `payment_webhook_events` |
| Payouts | `seller_payouts` row on payment success (`pending`, manual for MVP) |
| Mobile | Click/Payme/Uzum select → Payment screen → Pay/Cancel sandbox · Refund request |

## Env

```
PAYMENT_GATEWAY=local
PAYMENT_WEBHOOK_SECRET=...
```

Tests force `PAYMENT_GATEWAY=fake` via `phpunit.xml`.

## Out of scope

- Real Click / Payme / Uzum merchant SDK credentials
- Automated seller bank payouts
- Card vault / PCI storage

## Release

1. Render branch → `feature/sprint-7-payments`
2. Set `PAYMENT_GATEWAY=local` (+ webhook secret)
3. Checkout → Payment screen → **Pay now** → order success
4. Optional: signed `POST /api/v1/webhooks/payment` with `X-Signature`
