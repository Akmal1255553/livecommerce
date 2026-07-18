# API Specification

Version: 1.0  
Project: LiveCommerce Platform  
Base URL: `/api/v1`  
Status: Architecture Phase — Approved for Implementation Planning  
Document Owner: Founder & CTO  
Last Updated: 2026-06-27

---

## Document Purpose

This document defines the complete REST API specification for the LiveCommerce platform MVP. It is the authoritative reference for all API endpoints, request/response formats, authentication, and error handling.

All future API implementation must strictly follow this document.

---

## Table of Contents

1. [General Conventions](#1-general-conventions)
2. [Authentication](#2-authentication)
3. [Versioning](#3-versioning)
4. [Pagination](#4-pagination)
5. [Error Responses](#5-error-responses)
6. [Resources (Response Schemas)](#6-resources-response-schemas)
7. [Endpoints](#7-endpoints)
8. [Rate Limiting](#8-rate-limiting)
9. [Webhooks](#9-webhooks)
10. [Document Revision History](#10-document-revision-history)

---

## 1. General Conventions

### 1.1 Protocol

- HTTPS only (TLS 1.2+).
- Request and response bodies: `application/json`.
- Character encoding: UTF-8.
- Timestamps: ISO 8601 UTC (`2026-06-27T10:30:00Z`).

### 1.2 Request Headers

| Header | Required | Description |
|--------|----------|-------------|
| `Authorization` | Protected routes | `Bearer {access_token}` |
| `Accept` | Yes | `application/json` |
| `Content-Type` | Write routes | `application/json` |
| `Accept-Language` | No | `uz`, `ru` (default: `uz`) |
| `X-App-Version` | No | Mobile app version (e.g., `1.0.0`) |
| `X-Platform` | No | `android`, `ios` |
| `X-Device-Id` | No | Unique device identifier |

### 1.3 Success Response Envelope

All successful responses follow this structure:

```json
{
  "success": true,
  "data": { },
  "meta": { }
}
```

- `data` — Single resource object or array of resources.
- `meta` — Present only for paginated responses.

### 1.4 HTTP Methods

| Method | Usage |
|--------|-------|
| GET | Retrieve resource(s) |
| POST | Create resource or perform action |
| PUT | Full update of resource |
| PATCH | Partial update of resource |
| DELETE | Remove resource (soft delete where applicable) |

### 1.5 Naming Conventions

- URLs: lowercase, kebab-case, plural nouns (`/api/v1/videos`).
- Route parameters: `{id}` for UUID, `{itemId}` for nested resources.
- Query parameters: snake_case (`?sort_by=created_at`).

---

## 2. Authentication

### 2.1 Overview

Authentication uses **JWT** (JSON Web Tokens) with access + refresh token pair.

| Token | TTL | Storage (Mobile) |
|-------|-----|------------------|
| Access Token | 15 minutes | Memory (provider state) |
| Refresh Token | 30 days | Secure storage (flutter_secure_storage) |

### 2.2 Registration

```
POST /api/v1/auth/register
```

**Request:**
```json
{
  "username": "johndoe",
  "email": "john@example.com",
  "phone": "+998901234567",
  "password": "SecurePass123!",
  "password_confirmation": "SecurePass123!"
}
```

At least one of `email` or `phone` is required.

**Response: 201 Created**
```json
{
  "success": true,
  "data": {
    "user": { UserResource },
    "access_token": "eyJ...",
    "refresh_token": "eyJ...",
    "token_type": "Bearer",
    "expires_in": 900
  }
}
```

If phone registration, OTP verification is required before full access (see 2.4).

---

### 2.3 Login

```
POST /api/v1/auth/login
```

**Request:**
```json
{
  "login": "john@example.com",
  "password": "SecurePass123!"
}
```

`login` accepts email, phone, or username.

**Response: 200 OK**
```json
{
  "success": true,
  "data": {
    "user": { UserResource },
    "access_token": "eyJ...",
    "refresh_token": "eyJ...",
    "token_type": "Bearer",
    "expires_in": 900
  }
}
```

---

### 2.4 OTP Verification

```
POST /api/v1/auth/verify-otp
```

**Request:**
```json
{
  "phone": "+998901234567",
  "otp": "123456"
}
```

**Response: 200 OK** — Same as login response.

---

### 2.5 Resend OTP

```
POST /api/v1/auth/resend-otp
```

**Request:**
```json
{
  "phone": "+998901234567"
}
```

**Response: 200 OK**
```json
{
  "success": true,
  "data": {
    "message": "OTP sent successfully.",
    "retry_after": 60
  }
}
```

Rate limited: 1 OTP per 60 seconds per phone.

---

### 2.6 Refresh Token

```
POST /api/v1/auth/refresh
```

**Request:**
```json
{
  "refresh_token": "eyJ..."
}
```

**Response: 200 OK**
```json
{
  "success": true,
  "data": {
    "access_token": "eyJ...",
    "refresh_token": "eyJ...",
    "token_type": "Bearer",
    "expires_in": 900
  }
}
```

Previous refresh token is revoked (rotation).

---

### 2.7 Logout

```
POST /api/v1/auth/logout
```

**Auth:** Required

**Request:**
```json
{
  "refresh_token": "eyJ..."
}
```

**Response: 200 OK**
```json
{
  "success": true,
  "data": {
    "message": "Logged out successfully."
  }
}
```

Revokes the provided refresh token.

---

### 2.8 Forgot Password

```
POST /api/v1/auth/forgot-password
```

**Request:**
```json
{
  "email": "john@example.com"
}
```

**Response: 200 OK** — Always returns success (prevents email enumeration).

---

### 2.9 Reset Password

```
POST /api/v1/auth/reset-password
```

**Request:**
```json
{
  "email": "john@example.com",
  "token": "reset-token-from-email",
  "password": "NewSecurePass123!",
  "password_confirmation": "NewSecurePass123!"
}
```

**Response: 200 OK**

---

### 2.10 JWT Payload

Access token payload:

```json
{
  "sub": "user-uuid",
  "role": "user",
  "iat": 1719484200,
  "exp": 1719485100
}
```

---

## 3. Versioning

### 3.1 URL Versioning

All endpoints are prefixed with `/api/v1/`.

Future breaking changes will be released as `/api/v2/`. Both versions may run concurrently during migration.

### 3.2 Version Lifecycle

| Phase | Duration | Policy |
|-------|----------|--------|
| Current | Indefinite | Full support |
| Deprecated | 6 months after successor | Returns `Deprecation` header |
| Sunset | After deprecation period | Returns 410 Gone |

### 3.3 Non-Breaking Changes (Same Version)

- Adding new optional fields to responses.
- Adding new optional query parameters.
- Adding new endpoints.
- Adding new enum values (clients must handle unknown values).

### 3.4 Breaking Changes (New Version Required)

- Removing or renaming fields.
- Changing field types.
- Changing authentication mechanism.
- Changing URL structure.

---

## 4. Pagination

### 4.1 Cursor-Based Pagination

Used for: video feeds, notifications, live chat messages.

**Request:**
```
GET /api/v1/feed/for-you?cursor={cursor}&limit=20
```

| Parameter | Type | Default | Max | Description |
|-----------|------|---------|-----|-------------|
| cursor | string | null | — | Opaque cursor from previous response |
| limit | integer | 20 | 50 | Items per page |

**Response:**
```json
{
  "success": true,
  "data": [ { VideoResource } ],
  "meta": {
    "next_cursor": "eyJpZCI6...",
    "prev_cursor": null,
    "has_more": true,
    "limit": 20
  }
}
```

Cursor is an opaque base64-encoded string. Clients must not parse or construct cursors.

---

### 4.2 Offset-Based Pagination

Used for: orders, products (seller), admin lists, search results.

**Request:**
```
GET /api/v1/orders?page=1&per_page=20
```

| Parameter | Type | Default | Max | Description |
|-----------|------|---------|-----|-------------|
| page | integer | 1 | — | Page number |
| per_page | integer | 20 | 50 | Items per page |

**Response:**
```json
{
  "success": true,
  "data": [ { OrderResource } ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 97
  }
}
```

---

### 4.3 Sorting & Filtering

**Sorting:**
```
GET /api/v1/products?sort_by=price&sort_order=asc
```

**Filtering:**
```
GET /api/v1/seller/orders?status=confirmed&date_from=2026-06-01
```

| Parameter | Type | Description |
|-----------|------|-------------|
| sort_by | string | Field to sort by |
| sort_order | string | `asc` or `desc` (default: `desc`) |
| status | string | Filter by status enum |
| date_from | date | Filter from date (ISO 8601) |
| date_to | date | Filter to date (ISO 8601) |
| category_id | integer | Filter by category |
| q | string | Search query |

---

## 5. Error Responses

### 5.1 Error Envelope

```json
{
  "success": false,
  "message": "Human-readable error description.",
  "errors": {
    "field_name": ["Specific validation error."]
  }
}
```

- `message` — Always present. Top-level error description.
- `errors` — Present for validation errors (422). Field-level details.

### 5.2 HTTP Status Codes

| Code | Meaning | When |
|------|---------|------|
| 200 | OK | Successful GET, PUT, PATCH, action |
| 201 | Created | Successful POST creating a resource |
| 202 | Accepted | Async operation started (video processing) |
| 204 | No Content | Successful DELETE |
| 400 | Bad Request | Malformed request body |
| 401 | Unauthorized | Missing or invalid token |
| 403 | Forbidden | Valid token but insufficient permissions |
| 404 | Not Found | Resource does not exist |
| 409 | Conflict | Duplicate resource (username taken) |
| 422 | Validation Error | Input validation failed |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Internal Server Error | Unexpected server error |
| 503 | Service Unavailable | Maintenance or overload |

### 5.3 Standard Error Examples

**401 Unauthorized:**
```json
{
  "success": false,
  "message": "Unauthenticated."
}
```

**403 Forbidden:**
```json
{
  "success": false,
  "message": "You do not have permission to perform this action."
}
```

**404 Not Found:**
```json
{
  "success": false,
  "message": "Video not found."
}
```

**422 Validation Error:**
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

**429 Too Many Requests:**
```json
{
  "success": false,
  "message": "Too many requests. Please try again later.",
  "errors": {
    "retry_after": 45
  }
}
```

Response includes header: `Retry-After: 45`

---

## 6. Resources (Response Schemas)

### 6.1 UserResource

```json
{
  "id": "uuid",
  "username": "johndoe",
  "display_name": "John Doe",
  "avatar_url": "https://cdn.example.com/avatars/uuid.jpg",
  "bio": "Fashion lover",
  "is_verified": false,
  "role": "user",
  "follower_count": 1200,
  "following_count": 350,
  "video_count": 42,
  "is_following": false,
  "created_at": "2026-01-15T08:00:00Z"
}
```

`is_following` is included only when the authenticated user views another user's profile.

---

### 6.2 VideoResource

```json
{
  "id": "uuid",
  "user": { UserResource (compact) },
  "title": "Summer collection haul",
  "description": "Check out these amazing finds!",
  "video_url": "https://cdn.example.com/videos/uuid/playlist.m3u8",
  "thumbnail_url": "https://cdn.example.com/videos/uuid/thumb.jpg",
  "duration": 45,
  "view_count": 15000,
  "like_count": 2300,
  "comment_count": 145,
  "is_liked": true,
  "is_bookmarked": false,
  "products": [ { ProductResource (compact) } ],
  "status": "published",
  "created_at": "2026-06-20T14:30:00Z"
}
```

**UserResource (compact):**
```json
{
  "id": "uuid",
  "username": "johndoe",
  "avatar_url": "https://cdn.example.com/avatars/uuid.jpg",
  "is_verified": false
}
```

---

### 6.3 CommentResource

```json
{
  "id": 12345,
  "user": { UserResource (compact) },
  "body": "Love this product!",
  "like_count": 12,
  "replies": [ { CommentResource } ],
  "created_at": "2026-06-21T10:00:00Z"
}
```

---

### 6.4 ProductResource

```json
{
  "id": "uuid",
  "store": { StoreResource (compact) },
  "category": { CategoryResource },
  "title": "Summer Dress",
  "description": "Light cotton dress perfect for summer.",
  "price": 250000.00,
  "compare_at_price": 350000.00,
  "currency": "UZS",
  "sku": "DRS-001",
  "stock_quantity": 50,
  "status": "active",
  "rating_avg": 4.5,
  "review_count": 23,
  "images": [
    { "id": 1, "url": "https://cdn.example.com/products/uuid/1.jpg", "sort_order": 0 }
  ],
  "variants": [
    { "id": 1, "name": "Size", "value": "M", "price_adjustment": 0, "stock_quantity": 20 }
  ],
  "is_favorited": false,
  "created_at": "2026-05-10T09:00:00Z"
}
```

**ProductResource (compact):** id, title, price, thumbnail (first image), store name.

---

### 6.5 StoreResource

```json
{
  "id": "uuid",
  "name": "Fashion Hub",
  "slug": "fashion-hub",
  "logo_url": "https://cdn.example.com/stores/uuid/logo.jpg",
  "description": "Trendy fashion for everyone.",
  "status": "active",
  "product_count": 150,
  "owner": { UserResource (compact) },
  "created_at": "2026-03-01T00:00:00Z"
}
```

---

### 6.6 CategoryResource

```json
{
  "id": 1,
  "name": "Fashion",
  "slug": "fashion",
  "image_url": "https://cdn.example.com/categories/fashion.jpg",
  "parent_id": null,
  "children": [ { CategoryResource } ]
}
```

---

### 6.7 CartResource

```json
{
  "id": 1,
  "type": "user",
  "version": 3,
  "items": [
    {
      "id": 1,
      "product": { ProductCardResource },
      "variant": { "id": 1, "name": "Size", "value": "M" },
      "quantity": 2,
      "unit_price": { "amount": 250000, "currency": "UZS" },
      "line_total": { "amount": 500000, "currency": "UZS" },
      "discount_amount": { "amount": 0, "currency": "UZS" }
    }
  ],
  "summary": {
    "subtotal": { "amount": 500000, "currency": "UZS" },
    "discount_total": { "amount": 0, "currency": "UZS" },
    "shipping_estimate": { "amount": 25000, "currency": "UZS" },
    "currency": "UZS",
    "item_count": 2
  }
}
```

Guest carts use `type: "guest"` and string item IDs (`{productId}:{variantId|null}`). `version` increments on every mutation.

---

### 6.8 OrderResource

```json
{
  "id": "uuid",
  "order_number": "LC-20260627-0042",
  "status": "confirmed",
  "store": { StoreResource (compact) },
  "items": [
    {
      "id": 1,
      "product_title": "Summer Dress",
      "variant_name": "M",
      "quantity": 1,
      "unit_price": 250000.00,
      "total": 250000.00,
      "product": { ProductResource (compact) }
    }
  ],
  "subtotal": 250000.00,
  "shipping_cost": 25000.00,
  "discount": 0,
  "total": 275000.00,
  "currency": "UZS",
  "shipping_address": {
    "full_name": "John Doe",
    "phone": "+998901234567",
    "region": "Tashkent",
    "city": "Tashkent",
    "address_line": "123 Main St",
    "postal_code": "100000"
  },
  "payment_method": "click",
  "payment_status": "paid",
  "created_at": "2026-06-27T10:00:00Z",
  "shipped_at": null,
  "delivered_at": null
}
```

---

### 6.9 RefundResource

```json
{
  "id": "uuid",
  "order_id": "uuid",
  "user_id": "uuid",
  "reason": "Product arrived damaged",
  "status": "requested",
  "created_at": "2026-06-27T10:30:00Z",
  "updated_at": "2026-06-27T10:30:00Z"
}
```

---

### 6.10 LiveStreamResource

```json
{
  "id": "uuid",
  "seller": { UserResource (compact) },
  "store": { StoreResource (compact) },
  "title": "Summer Sale Live!",
  "status": "live",
  "viewer_count": 342,
  "pinned_products": [ { ProductResource (compact) } ],
  "publisher_token": "token-for-seller-only",
  "subscriber_token": "token-for-viewers",
  "channel_id": "channel-123",
  "started_at": "2026-06-27T15:00:00Z",
  "ended_at": null
}
```

`publisher_token` returned only to the stream owner. `subscriber_token` returned to viewers.

---

### 6.11 NotificationResource

```json
{
  "id": 1,
  "type": "video_liked",
  "title": "New like",
  "body": "janedoe liked your video.",
  "data": {
    "entity_type": "video",
    "entity_id": "uuid",
    "actor_id": "uuid"
  },
  "read_at": null,
  "created_at": "2026-06-27T12:00:00Z"
}
```

---

### 6.12 ReviewResource

```json
{
  "id": 1,
  "user": { UserResource (compact) },
  "rating": 5,
  "comment": "Great quality, fast delivery!",
  "created_at": "2026-06-25T18:00:00Z"
}
```

---

## 7. Endpoints

### 7.1 Health Check

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/health` | No | System health status |

**Response: 200 OK**
```json
{
  "success": true,
  "data": {
    "status": "ok",
    "database": "connected",
    "redis": "connected",
    "queue": "running"
  }
}
```

---

### 7.2 Authentication

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/auth/register` | No | Register new account |
| POST | `/auth/login` | No | Login |
| POST | `/auth/verify-otp` | No | Verify phone OTP |
| POST | `/auth/resend-otp` | No | Resend OTP |
| POST | `/auth/refresh` | No | Refresh access token |
| POST | `/auth/logout` | Yes | Logout (revoke refresh token) |
| POST | `/auth/forgot-password` | No | Request password reset |
| POST | `/auth/reset-password` | No | Reset password with token |

---

### 7.3 User Profile

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/me` | Yes | Get authenticated user profile |
| PUT | `/me` | Yes | Update own profile |
| PUT | `/me/avatar` | Yes | Update avatar (via pre-signed upload) |
| PUT | `/me/password` | Yes | Change password |
| DELETE | `/me` | Yes | Request account deletion |
| GET | `/users/{id}` | Optional | Get user public profile |
| GET | `/users/{id}/videos` | Optional | Get user's videos |
| POST | `/users/{id}/follow` | Yes | Follow user |
| DELETE | `/users/{id}/follow` | Yes | Unfollow user |
| POST | `/users/{id}/block` | Yes | Block user |
| DELETE | `/users/{id}/block` | Yes | Unblock user |
| POST | `/users/{id}/block` | Yes | Block user |
| DELETE | `/users/{id}/block` | Yes | Unblock user |
| GET | `/users/{id}/followers` | Optional | List followers |
| GET | `/users/{id}/following` | Optional | List following |

**PUT /me Request:**
```json
{
  "display_name": "John Doe",
  "bio": "Updated bio",
  "locale": "uz"
}
```

---

### 7.4 Video Feed

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/feed/for-you` | Optional | Personalized video feed (cursor) |
| GET | `/feed/trending` | Optional | Trending videos (cursor) |
| GET | `/feed/following` | Yes | Videos from followed users (cursor) |

---

### 7.5 Videos

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/videos/{id}` | Optional | Get video detail |
| POST | `/videos` | Yes | Create video (initiate upload) |
| POST | `/videos/{id}/confirm-upload` | Yes | Confirm upload complete |
| PUT | `/videos/{id}` | Yes | Update video metadata |
| DELETE | `/videos/{id}` | Yes | Delete video (soft) |
| POST | `/videos/{id}/like` | Yes | Like video |
| DELETE | `/videos/{id}/like` | Yes | Unlike video |
| POST | `/videos/{id}/view` | Optional | Record view (debounced) |
| GET | `/videos/{id}/comments` | Optional | List comments (offset) |
| POST | `/videos/{id}/comments` | Yes | Add comment |
| DELETE | `/videos/{id}/comments/{commentId}` | Yes | Delete comment |
| POST | `/videos/{id}/bookmark` | Yes | Bookmark video |
| DELETE | `/videos/{id}/bookmark` | Yes | Remove bookmark |
| GET | `/bookmarks` | Yes | List bookmarked videos (cursor) |

**POST /videos Request:**
```json
{
  "title": "My new video",
  "description": "Check this out",
  "product_ids": ["uuid1", "uuid2"],
  "visibility": "public"
}
```

**Response: 201 Created**
```json
{
  "success": true,
  "data": {
    "video": { VideoResource },
    "upload_url": "https://s3.example.com/presigned-url",
    "upload_fields": { }
  }
}
```

---

### 7.6 Products

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/products` | Optional | List products (offset, filterable) |
| GET | `/products/{id}` | Optional | Get product detail |
| GET | `/products/search` | Optional | Search products |
| GET | `/products/{id}/reviews` | Optional | List product reviews (offset) |
| POST | `/products/{id}/reviews` | Yes | Add review (verified purchase) |
| POST | `/products/{id}/favorite` | Yes | Add to favorites |
| DELETE | `/products/{id}/favorite` | Yes | Remove from favorites |
| GET | `/favorites` | Yes | List favorited products (offset) |

**GET /products/search Query:** `?q=dress&category_id=1&min_price=100000&max_price=500000&sort_by=price&sort_order=asc`

---

### 7.7 Categories

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/categories` | No | List all categories (tree) |
| GET | `/categories/{id}/products` | Optional | Products in category (offset) |

---

### 7.8 Cart

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/cart/guest` | No | Create guest cart token |
| GET | `/cart` | Optional | Get current cart (auth or `X-Guest-Cart-Token`) |
| POST | `/cart/items` | Yes* | Add item to cart |
| PUT | `/cart/items/{itemId}` | Yes* | Update item quantity |
| DELETE | `/cart/items/{itemId}` | Yes* | Remove item from cart |
| DELETE | `/cart` | Yes* | Clear cart |

\* Authenticated user **or** guest via `X-Guest-Cart-Token` header.

**POST /cart/guest Response: 201 Created**
```json
{
  "success": true,
  "data": {
    "guest_cart_token": "uuid",
    "type": "guest",
    "version": 1,
    "items": []
  }
}
```

**POST /cart/items Request:**
```json
{
  "product_id": "uuid",
  "variant_id": 1,
  "quantity": 2
}
```

---

### 7.9 Checkout & Orders

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/checkout` | Yes | Create order from cart (`Idempotency-Key` required) |
| GET | `/orders` | Yes | List own orders (offset) |
| GET | `/orders/{id}` | Yes | Get order detail |
| POST | `/orders/{id}/cancel` | Yes | Cancel order (`pending` / `awaiting_payment`) |
| POST | `/orders/{id}/refund` | Yes | Request refund (paid/delivered/completed) |
| GET | `/refunds/{id}` | Yes | Get refund request detail |
| POST | `/payments/sandbox/{id}/complete` | Yes | Sandbox Pay/Cancel (disabled when `PAYMENT_SANDBOX_ENABLED=false`) |

**POST /checkout Request:**
```json
{
  "cart_version": 2,
  "shipping_address": {
    "full_name": "John Doe",
    "phone": "+998901234567",
    "region": "Tashkent",
    "city": "Tashkent",
    "address_line": "123 Main St",
    "postal_code": "100000"
  },
  "payment_method": "click",
  "coupon_code": "SUMMER20",
  "notes": "Leave at door"
}
```

**Headers:** `Idempotency-Key: {uuid}` (required). Same key + same body returns the cached order; same key + different body → 422.

**Response: 201 Created**
```json
{
  "success": true,
  "data": {
    "order": { OrderResource },
    "payment_url": "https://…/api/v1/payments/sandbox/{order_id}?txn=…"
  }
}
```

When `PAYMENT_GATEWAY=fake`, order is `paid` immediately and `payment_url` is null.  
When `local|click|payme|uzum`, order stays `awaiting_payment` until webhook / sandbox complete.

**POST /orders/{id}/refund Request:**
```json
{
  "reason": "Product arrived damaged"
}
```

**Response: 200 OK** — order with `status: refund_requested`.

**GET /refunds/{id} Response:** Returns `{ RefundResource }` (see §6.9).

> **Note:** Older drafts used `POST /orders` for checkout. Runtime truth is `POST /checkout` (Sprint 4.5+).

---

### 7.9c Messaging (Sprint 8)

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/conversations` | Yes | List conversations (cursor) |
| POST | `/conversations` | Yes | Create/get buyer↔seller thread (`seller_id`, optional `order_id`, `message`) |
| GET | `/conversations/unread-count` | Yes | Total unread messages |
| GET | `/conversations/{id}/messages` | Yes | List messages (cursor) |
| POST | `/conversations/{id}/messages` | Yes | Send text and/or `image_url` |
| PUT | `/conversations/{id}/read` | Yes | Mark conversation read |

Block enforcement: either-direction block → `403` on send. Push: `NEW_MESSAGE` via stub FCM. Media: `purpose=message_image` on `POST /media/presigned-url`.

---

### 7.9a Live Commerce (Sprint 6)

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/discover` | Optional | Mixed discover feed |
| GET | `/live` | Optional | Live now |
| GET | `/live/replays` | Optional | Ended sessions with replay |
| POST | `/live/start` | Seller | Start live |
| GET | `/live/{id}` | Optional | Live session detail |
| POST | `/live/{id}/end` | Seller | End live |
| POST | `/live/{id}/pin-product` | Seller | Pin product |
| DELETE | `/live/{id}/pin-product/{productId}` | Seller | Unpin |
| GET | `/live/{id}/chat` | Optional | Chat history |
| POST | `/live/{id}/chat` | Yes | Send chat |
| POST | `/live/{id}/join` | Yes | Join session |
| POST | `/live/{id}/leave` | Yes | Leave session |
| POST | `/live/{id}/add-to-cart` | Yes | Add pinned product to cart |
| GET | `/live/{id}/assistant/suggestions` | Seller | Host assistant tips |
| GET | `/seller/live/analytics` | Seller | Live analytics overview |
| GET | `/seller/live/{id}/analytics` | Seller | Session analytics |

### 7.9b Feed extras

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/feed/for-you` | Optional | Personalized feed |
| GET | `/feed/trending` | Optional | Trending |
| GET | `/feed/popular` | Optional | Popular |
| GET | `/feed/new` | Optional | Newest |
| GET | `/feed/following` | Yes | Following feed |

---

### 7.10 Stores

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/stores/{slug}` | Optional | Get store by slug |
| GET | `/stores/{slug}/products` | Optional | Store products (offset) |
| GET | `/stores/{slug}/videos` | Optional | Store videos (cursor) |

---

### 7.11 Seller

All seller endpoints require `role: seller` and an active store.

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/seller/apply` | Yes | Apply for seller account |
| GET | `/seller/dashboard` | Seller | Dashboard summary |
| GET | `/seller/products` | Seller | List own products (offset) |
| POST | `/seller/products` | Seller | Create product |
| GET | `/seller/products/{id}` | Seller | Get own product detail |
| PUT | `/seller/products/{id}` | Seller | Update product |
| DELETE | `/seller/products/{id}` | Seller | Delete product (soft) |
| GET | `/seller/orders` | Seller | List store orders (offset) |
| GET | `/seller/orders/{id}` | Seller | Get order detail |
| PUT | `/seller/orders/{id}/status` | Seller | Update order status |
| GET | `/seller/analytics/summary` | Seller | Sales summary |

**POST /seller/apply Request:**
```json
{
  "store_name": "Fashion Hub",
  "category_id": 1,
  "description": "Trendy fashion store"
}
```

**POST /seller/products Request:**
```json
{
  "title": "Summer Dress",
  "description": "Light cotton dress",
  "category_id": 1,
  "price": 250000,
  "compare_at_price": 350000,
  "sku": "DRS-001",
  "stock_quantity": 50,
  "variants": [
    { "name": "Size", "value": "S", "stock_quantity": 10 },
    { "name": "Size", "value": "M", "stock_quantity": 20 }
  ],
  "images": ["upload-url-1", "upload-url-2"]
}
```

**PUT /seller/orders/{id}/status Request:**
```json
{
  "status": "shipped"
}
```

Allowed transitions: `confirmed → processing → shipped → delivered`.

**GET /seller/analytics/summary Response:**
```json
{
  "success": true,
  "data": {
    "total_revenue": 15000000.00,
    "total_orders": 120,
    "pending_orders": 5,
    "total_products": 45,
    "currency": "UZS",
    "period": "last_30_days"
  }
}
```

---

### 7.12 Live Sessions & Discover

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/discover` | Optional | Mixed ContentItem cursor page (video + live) |
| GET | `/feed/for-you` | Optional | Mixed ContentItem (same shape as discover) |
| GET | `/live` | Optional | Live-only list (ops/debug; not primary discovery) |
| POST | `/live/start` | Seller | Start a LiveSession |
| GET | `/live/{id}` | Optional | Detail + publisher/subscriber tokens |
| POST | `/live/{id}/end` | Seller | End session |
| POST | `/live/{id}/pin-product` | Seller | Pin product (`offset_seconds` from `started_at`) |
| DELETE | `/live/{id}/pin-product/{productId}` | Seller | Unpin product |
| GET | `/live/{id}/chat` | Optional | Chat messages (`?after_id=&limit=`) |
| POST | `/live/{id}/chat` | Yes | Send `type=user` message |
| POST | `/live/{id}/join` | Yes | Viewer join → metrics + analytics |
| POST | `/live/{id}/leave` | Yes | Viewer leave → metrics + analytics |

**ContentItem shape:**
```json
{
  "type": "video|live",
  "id": "uuid",
  "payload": { }
}
```

**POST /live/start Request:**
```json
{
  "title": "Summer Sale Live!",
  "product_ids": ["uuid1", "uuid2", "uuid3"]
}
```

**POST /live/{id}/chat Request:**
```json
{
  "message": "Great products!"
}
```

Chat message `type`: `user` | `system` | `commerce`. Pin actions emit `system` messages.

---

### 7.13 Search

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/search` | Optional | Global search |
| GET | `/search/suggestions` | Optional | Search autocomplete |

**GET /search Query:** `?q=dress&type=products&page=1`

`type` values: `all`, `videos`, `products`, `users`, `hashtags`.

**Response:**
```json
{
  "success": true,
  "data": {
    "videos": [ { VideoResource (compact) } ],
    "products": [ { ProductResource (compact) } ],
    "users": [ { UserResource (compact) } ]
  },
  "meta": { "current_page": 1, "last_page": 3, "per_page": 20, "total": 52 }
}
```

---

### 7.14 Notifications

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/notifications` | Yes | List notifications (cursor) |
| GET | `/notifications/unread-count` | Yes | Get unread count |
| PUT | `/notifications/{id}/read` | Yes | Mark as read |
| PUT | `/notifications/read-all` | Yes | Mark all as read |
| PUT | `/me/notification-settings` | Yes | Update notification preferences |

---

### 7.15 Media Upload

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/media/presigned-url` | Yes | Get pre-signed upload URL |

**Request:**
```json
{
  "file_name": "video.mp4",
  "mime_type": "video/mp4",
  "file_size": 52428800,
  "purpose": "video"
}
```

`purpose` values: `video`, `product_image`, `avatar`, `store_logo`.

**Response: 200 OK**
```json
{
  "success": true,
  "data": {
    "upload_id": "uuid",
    "upload_url": "https://s3.example.com/bucket/key?signature=...",
    "expires_at": "2026-06-27T10:15:00Z"
  }
}
```

---

### 7.16 Device Registration

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/devices` | Yes | Register FCM device token |
| DELETE | `/devices/{token}` | Yes | Unregister device token |

**POST /devices Request:**
```json
{
  "fcm_token": "firebase-device-token",
  "platform": "android",
  "device_id": "unique-device-id"
}
```

---

### 7.17 Admin & Moderation (Sprint 14)

All admin endpoints require `role: admin` or `role: moderator` unless noted. Base path: `/admin`.

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/admin/users` | Admin | List users (offset, filter by status/role) |
| PUT | `/admin/users/{id}/suspend` | Admin | Suspend user account |
| PUT | `/admin/users/{id}/ban` | Admin | Ban user account |
| PUT | `/admin/users/{id}/activate` | Admin | Reactivate suspended/banned user |
| GET | `/admin/videos/pending` | Moderator | Moderation queue (offset) |
| PUT | `/admin/videos/{id}/approve` | Moderator | Approve video |
| PUT | `/admin/videos/{id}/reject` | Moderator | Reject video (status → `rejected`) |
| PUT | `/admin/videos/{id}/hide` | Moderator | Hide video (status → `hidden`) |
| GET | `/admin/refunds` | Admin | List refund requests (offset, filter by status) |
| PUT | `/admin/refunds/{id}/approve` | Admin | Approve refund request |
| PUT | `/admin/refunds/{id}/reject` | Admin | Reject refund request |
| GET | `/admin/stores/pending` | Admin | Seller applications pending approval |
| PUT | `/admin/stores/{id}/approve` | Admin | Approve seller store |
| PUT | `/admin/stores/{id}/reject` | Admin | Reject seller application |
| GET | `/admin/audit-logs` | Admin | View audit log entries (offset) |

**PUT /admin/refunds/{id}/approve Response:**
```json
{
  "success": true,
  "data": {
    "refund": { RefundResource }
  }
}
```

**PUT /admin/videos/{id}/reject Request:**
```json
{
  "reason": "Policy violation"
}
```

---

## 8. Rate Limiting

| Scope | Limit | Window | Implementation |
|-------|-------|--------|----------------|
| Guest (unauthenticated) | 20 requests | 1 minute | `throttle:api` |
| Authenticated user | 180 requests | 1 minute | `throttle:api` |
| Auth endpoints (login, register, OTP, refresh) | 5 requests | 1 minute | `throttle:auth` |
| OTP resend | 1 request | 60 seconds | App-level cooldown |
| OTP verify attempts | 5 failures | 15 minute lockout | `OtpService` |
| Checkout | 30 requests | 1 minute | `throttle:checkout` |
| Live chat POST | 30 requests | 1 minute | `throttle:live-chat` |
| Live chat GET (room poll) | 120 requests | 1 minute | `throttle:live-poll` |
| Video upload | 10 requests | 1 hour | `VideoUploadService` |
| Search | 30 requests | 1 minute | `throttle:search` |

Rate limit headers on every response:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1719484260
```

---

## 9. Webhooks

### 9.1 Payment Gateway Webhook

```
POST /api/v1/webhooks/payment
```

**Auth:** Signature verification (HMAC-SHA256 in `X-Signature` header).

**Payload (example):**
```json
{
  "event": "payment.success",
  "transaction_id": "gateway-txn-id",
  "order_id": "uuid",
  "amount": 275000,
  "currency": "UZS",
  "timestamp": "2026-06-27T10:05:00Z"
}
```

`amount` is integer minor/major units matching `orders.total`.  
`transaction_id` must match the order’s stored `payment_transaction_id` / `payment_reference`.

**Response: 200 OK** — Always return 200 to prevent retries on processed events.

Idempotency: Duplicate webhook events for the same `event:transaction_id` are ignored.

---

## 10. Document Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-06-27 | Founder & CTO | Initial API specification for MVP |
| 1.1 | 2026-06-27 | Architecture Review | P0 patches: refunds, blocks, admin §7.17, RefundResource |
| 1.2 | 2026-07-17 | RC1 hardening | `/checkout` replaces `/orders` for checkout; Sprint 6–7 surfaces; rate limits match code; webhook txn bind |

---

**Related Documents:**
- [System Architecture](./02_SYSTEM_ARCHITECTURE.md)
- [Database Design](./03_DATABASE_DESIGN.md)
- [Project Structure](./05_PROJECT_STRUCTURE.md)
- [Engineering Rules](./06_ENGINEERING_RULES.md)

**Status:** Living spec — keep aligned with `backend/routes/api.php`
