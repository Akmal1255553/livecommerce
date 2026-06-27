# PROJECT_CONTEXT.md

# LiveCommerce Platform

## Project Overview

This project aims to build a modern Live Commerce platform inspired by TikTok, Douyin (China), TikTok Shop, Instagram Reels, and Shopify.

The platform is NOT a TikTok clone.

The goal is to build the next generation AI-powered Live Commerce ecosystem where users can:

* Watch short videos
* Watch live shopping streams
* Buy products directly inside the app
* Create stores
* Sell products
* Interact with sellers
* Receive personalized recommendations
* Use AI-powered features

The application must be production-ready, scalable, clean, maintainable, and follow enterprise-level architecture.

---

# Development Philosophy

The project must be written as if it will eventually support millions of users.

Never prioritize quick hacks over maintainable architecture.

Every feature should be modular.

Code quality is more important than writing code quickly.

Always think about scalability.

---

# Tech Stack

## Mobile

Flutter

State Management:
Riverpod

Architecture:
Clean Architecture

Offline support:
Required

Localization:
Required

Platforms:
Android
iOS

---

## Backend

Laravel 12

PHP 8.4+

REST API

JWT Authentication

Repository Pattern

Service Layer

Form Requests

Policies

Events

Queues

Jobs

OpenAPI Documentation

Feature Tests

Unit Tests

---

## Database

PostgreSQL

Redis

---

## Storage

S3 Compatible Storage

Examples:

Cloudflare R2

MinIO

AWS S3

---

## Streaming

The first version should use a third-party streaming provider.

Examples:

Agora

100ms

ZEGOCLOUD

Streaming logic should remain abstract so the provider can be replaced later.

---

# User Roles

Guest

User

Seller

Moderator

Administrator

---

# Core Modules

Authentication

Profiles

Followers

Video Feed

Video Upload

Likes

Comments

Bookmarks

Search

Categories

Products

Shopping Cart

Orders

Payments

Seller Dashboard

Live Streaming

Notifications

Messaging

Admin Panel

Analytics

AI Services

---

# AI Features

The platform must be designed with AI in mind.

Future AI modules include:

AI Product Description

AI Product Title Generator

AI Price Recommendation

AI Moderation

AI Video Recommendation

AI Feed Ranking

AI Subtitle Generation

AI Translation

AI Voice Translation

AI Video Highlights

AI Thumbnail Generation

AI Product Recognition

AI Fraud Detection

AI Chat Assistant

AI Seller Assistant

Architecture should allow adding AI services without changing the core business logic.

---

# Architecture Principles

Use SOLID.

Use Clean Architecture.

Use Repository Pattern.

Use Service Layer.

Separate Business Logic from Controllers.

Controllers should remain thin.

Never place business logic inside controllers.

Never duplicate logic.

Prefer dependency injection.

Prefer interfaces over implementations.

Write reusable components.

---

# API Principles

REST API

Versioned API

/api/v1/

Standard JSON responses

Pagination

Filtering

Sorting

Validation

Rate limiting

Proper HTTP status codes

Consistent error responses

---

# Database Principles

Avoid duplicated data.

Use UUID where appropriate.

Create indexes.

Support soft deletes.

Support audit logs.

Support future sharding.

Support future microservices.

---

# Mobile Principles

Modern UI

Smooth animations

TikTok-style scrolling

Fast startup

Responsive

Offline cache

Dark mode

Light mode

Localization

Accessibility

---

# Video Feed

Vertical feed

Infinite scrolling

Auto play

Pause on scroll

Like animation

Comment overlay

Share

Follow

Recommended videos

Trending videos

---

# Live Streaming

Live chat

Pinned products

Real-time reactions

Gifts (future)

Moderation

Recording

Replay

Viewer analytics

Seller dashboard

---

# Product System

Categories

Variants

Inventory

Images

Videos

Discounts

Coupons

Reviews

Ratings

Favorites

Recommendations

---

# Order System

Cart

Checkout

Payment

Order Tracking

Refunds

Seller Management

Customer History

---

# Notification System

Push Notifications

Email

SMS

In-App Notifications

---

# Security

JWT

CSRF protection

Rate limiting

Input validation

XSS protection

SQL Injection protection

Authorization policies

Role permissions

Audit logging

Encrypted sensitive data

---

# Performance

Lazy loading

Queue heavy tasks

Redis cache

Image optimization

Video CDN

Background jobs

Database optimization

---

# Coding Standards

Always write clean code.

Always follow PSR standards.

Always use meaningful names.

Avoid magic numbers.

Avoid duplicated code.

Use comments only when necessary.

Every function should have one responsibility.

---

# Git Workflow

main

develop

feature/*

hotfix/*

release/*

---

# Cursor Instructions

Before implementing any feature:

1. Understand the business goal.

2. Analyze the existing architecture.

3. Reuse existing components whenever possible.

4. Avoid breaking changes.

5. Maintain consistency.

6. Follow Clean Architecture.

7. Explain major architectural decisions when necessary.

Never generate code that ignores this document.

This document is considered the project's source of truth.

Always prioritize long-term maintainability over short-term implementation speed.

---

# Project Goal

Build the best AI-powered Live Commerce platform capable of serving millions of users while maintaining excellent performance, code quality, scalability, and developer experience.

---

# Project Structure

## Repository Layout (Monorepo)

```
livecommerce/
├── mobile/                  # Flutter application
├── backend/                 # Laravel 12 API
├── docs/                    # Documentation
├── docker/                  # Docker configs for local dev
├── .github/                 # CI/CD workflows
└── README.md
```

---

## Backend Structure (Laravel)

```
backend/
├── app/
│   ├── Console/
│   ├── Events/
│   ├── Exceptions/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       └── V1/
│   │   ├── Middleware/
│   │   ├── Requests/
│   │   └── Resources/
│   ├── Jobs/
│   ├── Listeners/
│   ├── Models/
│   ├── Policies/
│   ├── Providers/
│   ├── Repositories/
│   │   ├── Contracts/
│   │   └── Eloquent/
│   └── Services/
│       ├── Auth/
│       ├── Video/
│       ├── Product/
│       ├── Order/
│       ├── LiveStream/
│       ├── Notification/
│       └── Recommendation/
├── config/
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
├── routes/
│   └── api.php
├── tests/
│   ├── Feature/
│   └── Unit/
└── storage/
```

### Backend Layer Responsibilities

**Controllers** — Receive HTTP request, delegate to service, return JSON resource. No business logic.

**Form Requests** — Validation rules and authorization checks for incoming requests.

**Services** — All business logic lives here. Orchestrates repositories, events, and jobs.

**Repositories** — Database access abstraction. Implements repository interfaces.

**Models** — Eloquent models with relationships, scopes, and casts.

**Resources** — Transform models into consistent API JSON responses.

**Jobs** — Async tasks (video transcoding, send notification, process order).

**Events/Listeners** — Decouple side effects (order placed → send email + push notification).

**Policies** — Authorization rules per model/action.

---

## Mobile Structure (Flutter)

```
mobile/
├── lib/
│   ├── main.dart
│   ├── app/
│   │   ├── app.dart
│   │   └── router.dart
│   ├── core/
│   │   ├── constants/
│   │   ├── errors/
│   │   ├── network/
│   │   ├── storage/
│   │   ├── theme/
│   │   └── utils/
│   ├── features/
│   │   ├── auth/
│   │   │   ├── data/
│   │   │   ├── domain/
│   │   │   └── presentation/
│   │   ├── feed/
│   │   ├── video/
│   │   ├── product/
│   │   ├── cart/
│   │   ├── checkout/
│   │   ├── orders/
│   │   ├── profile/
│   │   ├── search/
│   │   ├── notifications/
│   │   ├── live/
│   │   └── seller/
│   └── shared/
│       ├── widgets/
│       └── providers/
├── assets/
│   ├── images/
│   ├── fonts/
│   └── l10n/
├── test/
└── pubspec.yaml
```

### Mobile Layer Responsibilities (Clean Architecture)

**Presentation** — UI widgets, screens, Riverpod providers/notifiers, state classes.

**Domain** — Entities, repository interfaces, use cases. Pure Dart, no Flutter imports.

**Data** — Repository implementations, API clients, local database (Hive/Drift), DTOs/models.

---

# Database Schema Overview

## Core Tables

```
users
  id (uuid), username, email, phone, password, role, avatar_url,
  bio, is_verified, created_at, updated_at, deleted_at

user_profiles
  user_id, display_name, follower_count, following_count,
  video_count, locale, notification_settings (jsonb)

follows
  id, follower_id, following_id, created_at

videos
  id (uuid), user_id, title, description, video_url, thumbnail_url,
  duration, view_count, like_count, comment_count, status,
  created_at, updated_at, deleted_at

video_products
  video_id, product_id

video_likes
  id, user_id, video_id, created_at

comments
  id, user_id, video_id, parent_id, body, created_at, deleted_at

bookmarks
  id, user_id, video_id, created_at

categories
  id, name, slug, parent_id, image_url, sort_order

products
  id (uuid), seller_id, category_id, title, description, price,
  compare_at_price, sku, stock_quantity, status,
  created_at, updated_at, deleted_at

product_images
  id, product_id, url, sort_order

product_variants
  id, product_id, name, value, price_adjustment, stock_quantity

stores
  id (uuid), user_id, name, slug, logo_url, description, status,
  created_at, updated_at

carts
  id, user_id, created_at, updated_at

cart_items
  id, cart_id, product_id, variant_id, quantity

orders
  id (uuid), user_id, status, subtotal, shipping_cost, discount,
  total, shipping_address (jsonb), payment_method, payment_status,
  created_at, updated_at

order_items
  id, order_id, product_id, variant_id, quantity, unit_price, total

live_streams
  id (uuid), seller_id, title, stream_key, status, viewer_count,
  started_at, ended_at, replay_url

live_stream_products
  live_stream_id, product_id, is_pinned, pinned_at

live_chat_messages
  id, live_stream_id, user_id, message, created_at

notifications
  id, user_id, type, title, body, data (jsonb), read_at, created_at

coupons
  id, code, discount_type, discount_value, min_order_amount,
  usage_limit, used_count, expires_at

reviews
  id, user_id, product_id, order_id, rating, comment, created_at
```

## Indexing Strategy

Index all foreign keys.

Index `videos.status, created_at` for feed queries.

Index `products.category_id, status` for catalog queries.

Index `orders.user_id, status` for order history.

Index `follows.follower_id` and `follows.following_id`.

Index `notifications.user_id, read_at`.

Full-text search index on `products.title, products.description`.

---

# API Design Standards

## Response Format

Success:

```json
{
  "success": true,
  "data": { },
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 20,
    "total": 200
  }
}
```

Error:

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

## HTTP Status Codes

200 — OK

201 — Created

204 — No Content (delete)

400 — Bad Request

401 — Unauthorized

403 — Forbidden

404 — Not Found

422 — Validation Error

429 — Too Many Requests

500 — Internal Server Error

## Pagination

Cursor-based pagination for feeds (video feed, notifications).

Offset-based pagination for admin lists (orders, products).

Default page size: 20. Max page size: 50.

## Authentication

All protected routes require `Authorization: Bearer {access_token}` header.

Access token TTL: 15 minutes.

Refresh token TTL: 30 days.

Refresh token rotation on each use.

---

# Third-Party Integrations

## Live Streaming (Abstracted)

```
App/Services/LiveStream/
├── Contracts/
│   └── StreamingProviderInterface.php
├── Providers/
│   ├── AgoraProvider.php
│   ├── HundredMsProvider.php
│   └── ZegocloudProvider.php
└── LiveStreamService.php
```

The active provider is configured via `.env`:

```
STREAMING_PROVIDER=agora
AGORA_APP_ID=
AGORA_APP_CERTIFICATE=
```

Switching providers requires only a config change, not code changes.

---

## Payment Gateway

Use a local Uzbekistan-compatible payment gateway for MVP.

Payment flow:

1. Mobile app initiates checkout.
2. Backend creates order with status `pending_payment`.
3. Backend returns payment URL or token.
4. User completes payment on gateway.
5. Gateway sends webhook to backend.
6. Backend verifies webhook signature, updates order to `confirmed`.

Never store card numbers or CVV.

---

## Push Notifications

Firebase Cloud Messaging (FCM) for Android and iOS.

Store FCM tokens in `user_devices` table.

Notification types: `new_follower`, `video_liked`, `new_comment`, `order_update`, `live_started`.

Use Laravel queues to send notifications asynchronously.

---

## Video Processing Pipeline

1. Mobile uploads raw video to S3 pre-signed URL.
2. Backend receives upload confirmation webhook/event.
3. `ProcessVideoJob` queued:
   - Transcode to HLS (720p, 480p, 360p adaptive).
   - Generate thumbnail at 1-second mark.
   - Update video record with processed URLs.
4. CDN serves transcoded video.

---

# Environment Setup

## Prerequisites

PHP 8.4+

Composer 2.x

PostgreSQL 16+

Redis 7+

Node.js 20+ (for asset building)

Flutter 3.x SDK

Docker & Docker Compose (recommended for local dev)

---

## Local Development (Docker Compose)

```yaml
services:
  app:
    build: ./docker/php
    volumes:
      - ./backend:/var/www
  nginx:
    image: nginx:alpine
    ports:
      - "8080:80"
  postgres:
    image: postgres:16
    environment:
      POSTGRES_DB: livecommerce
      POSTGRES_USER: livecommerce
      POSTGRES_PASSWORD: secret
    ports:
      - "5432:5432"
  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
  minio:
    image: minio/minio
    ports:
      - "9000:9000"
      - "9001:9001"
```

Backend API available at: `http://localhost:8080/api/v1`

MinIO console at: `http://localhost:9001`

---

## Environment Variables (Backend)

```
APP_NAME=LiveCommerce
APP_ENV=local
APP_KEY=
APP_URL=http://localhost:8080

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=livecommerce
DB_USERNAME=livecommerce
DB_PASSWORD=secret

REDIS_HOST=redis
REDIS_PORT=6379

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=
AWS_BUCKET=
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=true

JWT_SECRET=
JWT_TTL=15
JWT_REFRESH_TTL=43200

STREAMING_PROVIDER=agora
AGORA_APP_ID=
AGORA_APP_CERTIFICATE=

FCM_SERVER_KEY=

PAYMENT_GATEWAY_URL=
PAYMENT_GATEWAY_KEY=
PAYMENT_GATEWAY_SECRET=

SMS_PROVIDER=
SMS_API_KEY=
```

---

# Testing Strategy

## Backend

**Unit Tests** — Services, repositories, helpers. Mock dependencies.

**Feature Tests** — Full HTTP request/response cycle per endpoint.

**Coverage Target** — Minimum 80% for services and repositories.

Test structure mirrors app structure:

```
tests/
├── Feature/
│   ├── Auth/
│   ├── Video/
│   ├── Product/
│   ├── Order/
│   └── LiveStream/
└── Unit/
    ├── Services/
    └── Repositories/
```

Run tests: `php artisan test`

---

## Mobile

**Unit Tests** — Use cases, repositories, utilities.

**Widget Tests** — Individual UI components.

**Integration Tests** — Full user flows (login, add to cart, checkout).

Run tests: `flutter test`

---

# CI/CD Pipeline

## GitHub Actions Workflow

On pull request to `develop`:

1. Run backend linter (`./vendor/bin/pint --test`).
2. Run backend tests (`php artisan test`).
3. Run Flutter analyzer (`flutter analyze`).
4. Run Flutter tests (`flutter test`).
5. Build check (Flutter APK/IPA compile check).

On merge to `main`:

1. All PR checks pass.
2. Build and push Docker image to registry.
3. Deploy to staging environment.
4. Run smoke tests.
5. Manual approval for production deploy.

---

# Deployment Architecture

## Production Environment

```
                    ┌─────────────┐
                    │   CDN       │
                    │ (Cloudflare)│
                    └──────┬──────┘
                           │
              ┌────────────┴────────────┐
              │       Load Balancer     │
              └────────────┬────────────┘
                           │
         ┌─────────────────┼─────────────────┐
         │                 │                 │
   ┌─────▼─────┐   ┌──────▼──────┐  ┌──────▼──────┐
   │  API App  │   │  API App    │  │  Worker     │
   │  (Laravel)│   │  (Laravel)  │  │  (Queues)   │
   └─────┬─────┘   └──────┬──────┘  └──────┬──────┘
         │                │                 │
         └────────────────┼─────────────────┘
                          │
         ┌────────────────┼────────────────┐
         │                │                │
   ┌─────▼─────┐   ┌──────▼──────┐  ┌──────▼──────┐
   │ PostgreSQL│   │    Redis    │  │  S3 / R2    │
   │  Primary  │   │   Cache +   │  │   Storage   │
   │ + Replica │   │   Queues    │  │             │
   └───────────┘   └─────────────┘  └─────────────┘
```

## Environments

**local** — Developer machine with Docker Compose.

**staging** — Mirrors production; used for QA and demo.

**production** — Live environment serving real users.

---

# Development Workflow

## Starting a New Feature

1. Create branch from `develop`: `feature/video-upload`
2. Read relevant PRD section and this document.
3. Write migration if database changes needed.
4. Implement repository interface and Eloquent implementation.
5. Implement service with business logic.
6. Implement controller (thin), form request, and API resource.
7. Write feature tests.
8. Implement mobile data layer (API client, repository).
9. Implement mobile domain layer (use case).
10. Implement mobile presentation layer (screen, provider).
11. Open pull request to `develop`.
12. Code review and CI checks must pass before merge.

---

## Code Review Checklist

- Business logic is in services, not controllers.
- Validation is in form requests.
- Authorization is in policies.
- API responses use resources.
- Database queries are in repositories.
- Heavy tasks are queued as jobs.
- Tests cover happy path and main error cases.
- No hardcoded strings (use localization).
- No secrets in code.
- Mobile follows Clean Architecture layers.

---

# Monitoring & Observability

**Sentry** — Error tracking for backend and mobile.

**Laravel Telescope** — Local debugging (disabled in production).

**Redis Monitor** — Queue depth and cache hit rate.

**PostgreSQL Slow Query Log** — Queries exceeding 100ms logged.

**Uptime Monitoring** — Health check endpoint `/api/v1/health`.

Health check response:

```json
{
  "status": "ok",
  "database": "connected",
  "redis": "connected",
  "queue": "running"
}
```

---

# Security Checklist

- [ ] All endpoints authenticated except public feed and product browse
- [ ] Rate limiting: 60 req/min for authenticated, 20 req/min for guest
- [ ] CORS configured for mobile app origins only
- [ ] All user input validated and sanitized
- [ ] SQL injection prevented via Eloquent/prepared statements
- [ ] XSS prevented via output encoding
- [ ] File uploads validated (type, size, MIME)
- [ ] Pre-signed URLs for direct S3 uploads (expiry: 15 minutes)
- [ ] JWT secret rotated periodically
- [ ] Audit log for admin and seller actions
- [ ] Sensitive fields encrypted at rest (phone, payment tokens)

---

# Document Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-06-27 | Founder & CTO | Initial project context |
| 1.1 | 2026-06-27 | Founder & CTO | Added project structure, DB schema, API standards, integrations, setup, testing, CI/CD, deployment, workflow |
