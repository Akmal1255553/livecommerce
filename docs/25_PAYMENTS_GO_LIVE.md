# 25 — Payments Go-Live Checklist

Status: **code ready; Bitcoin/card in app — deploy + NOWPayments IPN still required**
Last updated: 2026-08-09

What has to happen for wallet top-ups and order payments to move real money.

---

## 1. What the platform already does

| Concern | State |
|---------|-------|
| Order payment via Click / Payme / Uzum | Adapters + webhooks implemented |
| Wallet top-up via the same providers | Implemented — routed through `PaymentGatewayInterface` |
| **Bitcoin (NOWPayments)** | Gateway + `POST /api/v1/webhooks/bitcoin` — see §3.1 / §4 |
| **Saved bank card (top-up)** | CRUD `/wallet/cards` + sandbox `CardPaymentGateway` — real PSP token later |
| Method → gateway | `PaymentGatewayResolver`: `bitcoin` / `card` / else `PAYMENT_GATEWAY` |
| Payme Merchant API protocol | Full method set implemented — see §5 |
| Webhook signature verification | Click MD5 `sign_string`, Payme Basic auth, Uzum HMAC-SHA256, NOWPayments HMAC-SHA512, generic HMAC |
| Duplicate callbacks | Ignored (`payment_webhook_events.idempotency_key` + final-status guard) |
| Amount tampering | Callback amount is checked against the order total / pending top-up |
| Sandbox self-confirmation | Disabled unless `WALLET_SANDBOX_ENABLED=true` |
| **Withdrawals (payouts)** | **Not connected to any provider** — see §7 |

A top-up is credited **only** when a signed provider callback reaches the backend. The app
cannot credit a balance by itself.

---

## 2. What we need from you (business)

To get merchant credentials, each provider asks for roughly the same package:

- Юрлицо (ООО / ЯТТ) — свидетельство о регистрации, ИНН
- Расчётный счёт в узбекском банке
- Договор с провайдером (Click Merchant / Payme Business / Uzum)
- Описание сервиса и домен, на котором работает API

Ask each provider for **sandbox (test) credentials first** — they let us run the whole flow end
to end without real money.

---

## 3. Credentials to hand over

Fill these in the Render dashboard (or `.env` locally). Nothing else needs to change in code.

### Click

```
PAYMENT_GATEWAY=click
CLICK_MERCHANT_ID=
CLICK_SERVICE_ID=
CLICK_MERCHANT_USER_ID=
CLICK_SECRET=
CLICK_RETURN_URL=https://<app-domain>/payment/return
```

### Payme

```
PAYMENT_GATEWAY=payme
PAYME_MERCHANT_ID=
PAYME_SECRET=
```

### Uzum

```
PAYMENT_GATEWAY=uzum
UZUM_MERCHANT_ID=
UZUM_SECRET=
UZUM_CHECKOUT_URL=https://checkout.uzumbank.uz
UZUM_RETURN_URL=https://<app-domain>/payment/return
```

### Bitcoin / NOWPayments

```
BITCOIN_API_KEY=<NOWPayments API key>
BITCOIN_API_URL=https://api.nowpayments.io/v1
BITCOIN_IPN_SECRET=<IPN secret from NOWPayments IPN settings — NOT the API key>
BITCOIN_IPN_CALLBACK_URL=https://livecommerce-api.onrender.com/api/v1/webhooks/bitcoin
BITCOIN_INVOICE_TTL_MINUTES=30
```

Empty `BITCOIN_API_KEY` → local sandbox address + fixed rate (dev/tests only).

In the NOWPayments dashboard IPN / callback field, use the **same** URL as
`BITCOIN_IPN_CALLBACK_URL`. Localhost will never receive IPN — the API must be
public HTTPS (Render: `livecommerce-api.onrender.com`).

### Card (saved payment methods)

MVP stores **last4 / brand / expiry only** (`user_payment_cards`). Charges go through
`CardPaymentGateway` sandbox confirm until a Click/Payme card-token contract is wired.
No full PAN in the database.

Also set, regardless of provider:

```
WALLET_SANDBOX_ENABLED=false
PAYMENT_SANDBOX_ENABLED=false
```

Leaving sandbox on in production would let a user credit their own balance.

---

## 4. Webhook URLs to register with the provider

Replace `<api-domain>` with the deployed API host.

| Provider | URL |
|----------|-----|
| Click | `https://<api-domain>/api/v1/webhooks/click` |
| Payme | `https://<api-domain>/api/v1/webhooks/payme` |
| Uzum | `https://<api-domain>/api/v1/webhooks/uzum` |
| **Bitcoin (NOWPayments)** | `https://<api-domain>/api/v1/webhooks/bitcoin` |

For the current Render API host that is:

`https://livecommerce-api.onrender.com/api/v1/webhooks/bitcoin`

Click uses one endpoint for both `Prepare` (`action=0`) and `Complete` (`action=1`).

**Payme account field:** the merchant cabinet must be configured with a single account
parameter named `order_id`. We send our own reference in it, which is either an order UUID or
a wallet top-up reference (see §6) — Payme does not need to know the difference.

---

## 5. Payme Merchant API protocol

Payme never settles in one call. It asks whether an account can be billed, creates a
transaction, and performs it separately, then may ask about it again for days. All of it lives
in `PaymeMerchantService`; transaction state is persisted in `payme_transactions` because the
protocol outlives any single request.

| Method | Behaviour |
|--------|-----------|
| `CheckPerformTransaction` | Resolves the reference, checks the amount, refuses an order that is no longer awaiting payment |
| `CreateTransaction` | Records the transaction; repeating the same Payme id returns the same answer; a second open transaction for one account is refused (`-31008`) |
| `PerformTransaction` | The only place money moves — hands the callback to the shared webhook processor, so orders and top-ups settle exactly as they do for other providers |
| `CancelTransaction` | Before perform: releases the order / fails the top-up, state `-1`. After perform: refused with `-31007`, see below |
| `CheckTransaction` | Reports create / perform / cancel times, state, reason |
| `GetStatement` | Lists transactions in a time window for Payme's reconciliation |

Amounts on the wire are **tiyin** (1 UZS = 100 tiyin) and times are epoch milliseconds. A
transaction created but never performed within 12 hours is cancelled with reason `4`, as the
protocol requires.

**Cancelling a settled payment is refused.** Sending money back to a card needs a payout
contract we do not have, so `CancelTransaction` on a performed transaction answers `-31007`
("unable to cancel") and logs `payment.payme.cancel.refused`. Refunds go through the Payme
merchant cabinet manually until a payout API exists. Expect Payme to ask about this during
certification — the answer is that goods/credit have already been delivered.

---

## 6. How a reference identifies what was paid

Providers echo back whatever merchant reference we send them. Two kinds exist:

| Paid thing | Reference sent as `merchant_trans_id` / `order_id` |
|------------|---------------------------------------------------|
| Order | the order UUID, e.g. `9f2c…` |
| Wallet top-up | `wt-` + the wallet transaction UUID, e.g. `wt-9f2c…` |

`PaymentWebhookProcessor` routes on that prefix. Orders keep their bare UUID so references
already in flight at a provider stay resolvable.

---

## 7. Withdrawals are still not real

`POST /api/v1/wallet/withdrawals` freezes the amount (`available_balance` → `held_balance`) and
records a request. Nothing pays it out: `WalletService::settleWithdrawal()` exists but has no
caller — no admin endpoint, no payout provider.

Consequences today:

- A user can cancel their own request while it is `requested` and get the money back, so funds
  are not permanently stuck.
- Nobody can mark a withdrawal as paid or rejected.

Two ways forward, whenever you want to pick one up:

1. **Manual payouts** — an admin screen listing pending requests; the founder transfers money by
   bank and marks the request completed, which calls `settleWithdrawal(true)`. Small amount of
   work, real money, manual process.
2. **Payout API** — Click/Payme mass-payout contract (a separate agreement from accepting
   payments, and harder to obtain). Then a `PayoutGatewayInterface` mirrors the payment side.

---

## 8. Verifying before announcing it works

### Click / Payme / Uzum

With sandbox credentials from the provider:

1. Set the provider env vars, `WALLET_SANDBOX_ENABLED=false`, redeploy.
2. In the app, start a top-up. It must open the **provider's** page, not ours.
3. Pay with the provider's test card.
4. The balance must appear only after the callback lands. Check `payment_webhook_events` for a
   row with the matching `reference`.
5. Repeat the callback (providers usually have a "resend" button). The balance must not change.
6. Buy something with `payment_method=wallet` and confirm the balance drops by the order total.

If step 4 never happens, the webhook URL registered with the provider is wrong or unreachable —
check the provider's callback log before touching our code.

### Bitcoin (NOWPayments)

1. Deploy a build that includes `BitcoinWebhookController` (until then
   `POST …/webhooks/bitcoin` returns **404**).
2. On Render set `BITCOIN_API_KEY`, `BITCOIN_IPN_SECRET` (from IPN page), and
   `BITCOIN_IPN_CALLBACK_URL` as above; run migrations (`user_payment_cards`).
3. In NOWPayments, register the same IPN URL.
4. App → Wallet → Top-up → Bitcoin → real invoice address/QR (not sandbox).
5. Pay a small test amount; balance credits only after IPN with valid signature.
6. Wrong IPN secret → signature fail, no credit. Fix secret and resend IPN.
