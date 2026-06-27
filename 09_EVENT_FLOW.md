# Event Flow Specification

Version: 1.1  
Project: LiveCommerce Platform  
Status: Architecture Phase  
Document Owner: Founder & CTO  
Last Updated: 2026-06-27

---

## Purpose

Describe every important business event in the LiveCommerce platform. The system is **event-driven** wherever side effects can be decoupled from core business logic.

Events enable: module decoupling, async background processing, scalability, and future microservice extraction.

---

## Goals

- Decouple modules (producer does not know consumers)
- Simplify background processing via queued listeners
- Improve scalability (async by default for side effects)
- Support future microservice boundaries
- Enable audit trails and analytics from event streams

---

## Event Architecture

```mermaid
flowchart LR
    SVC[Service Layer] -->|dispatch| EVT[Domain Event]
    EVT --> L1[Listener: Sync]
    EVT --> L2[Listener: Queue Job]
    L1 --> DB[(Database Update)]
    L2 --> Q[Laravel Queue]
    Q --> JOB[Job Handler]
    JOB --> EXT[External Service]
```

### Rules

1. Events are dispatched from the **Service layer** only.
2. Events are **past tense** named (`VideoUploaded`, not `UploadVideo`).
3. Listeners that call external services or take > 100ms **must be queued**.
4. Event payloads contain **IDs and minimal data** — listeners fetch full records if needed.
5. Events are **immutable** — once dispatched, payload does not change.
6. Failed queued listeners retry per policy; dead-letter after max attempts.

---

## Event Template

| Field | Description |
|-------|-------------|
| **Event Name** | Past tense domain event |
| **Trigger** | Action that causes the event |
| **Source Module** | Module that dispatches |
| **Payload** | Data included in event |
| **Consumers** | Listeners that react |
| **Queue** | Queue name (if async) |
| **Retry Policy** | Max attempts, backoff |
| **Failure Handling** | What happens on permanent failure |
| **Logging** | Structured log fields |
| **Metrics** | Counters/timers to track |

---

## Event Catalog

### UserRegistered

| Field | Value |
|-------|-------|
| **Trigger** | User completes registration (email or OTP verified) |
| **Source Module** | Authentication |
| **Payload** | `{ userId: UUID, method: "email"|"phone", role: "user" }` |
| **Consumers** | CreateUserProfile, SendWelcomeNotification |
| **Queue** | `default` |
| **Retry Policy** | 3 attempts, exponential backoff (10s, 60s, 300s) |
| **Failure Handling** | Log error; user account exists but profile/notification may be missing (reconciliation job) |
| **Logging** | `event=user_registered user_id={id} method={method}` |
| **Metrics** | `registrations.total` counter by method |

**Flow:**
```
AuthService.register() → dispatch(UserRegistered)
  → CreateUserProfile: create user_profiles record
  → SendWelcomeNotification: queue SendPushNotificationJob
```

---

### VideoUploaded

| Field | Value |
|-------|-------|
| **Trigger** | Mobile confirms upload complete (`POST /videos/{id}/confirm-upload`) |
| **Source Module** | Videos |
| **Payload** | `{ videoId: UUID, userId: UUID, storagePath: string }` |
| **Consumers** | QueueVideoProcessing, NotifyUploadReceived |
| **Queue** | `video-processing` |
| **Retry Policy** | 3 attempts for notification; 5 for processing |
| **Failure Handling** | Video status → `failed`; notify uploader |
| **Logging** | `event=video_uploaded video_id={id} user_id={userId}` |
| **Metrics** | `videos.uploaded.total`, `videos.upload_size_bytes` histogram |

**Flow:**
```
VideoService.confirmUpload() → dispatch(VideoUploaded)
  → QueueVideoProcessing: dispatch ProcessVideoJob
  → NotifyUploadReceived: in-app notification "Processing your video"
```

---

### VideoProcessed

| Field | Value |
|-------|-------|
| **Trigger** | ProcessVideoJob completes transcoding |
| **Source Module** | Videos (Job) |
| **Payload** | `{ videoId: UUID, hlsUrl: string, thumbnailUrl: string, duration: int }` |
| **Consumers** | PublishVideo, NotifyVideoReady, QueueModeration (Phase 2) |
| **Queue** | `default` |
| **Retry Policy** | 3 attempts |
| **Failure Handling** | Video stays in `processing` status; alert ops |
| **Logging** | `event=video_processed video_id={id} duration={duration}` |
| **Metrics** | `videos.processed.total`, `videos.processing_duration_ms` histogram |

**Flow:**
```
ProcessVideoJob.handle() → dispatch(VideoProcessed)
  → PublishVideo: update status to `published`
  → NotifyVideoReady: push notification to uploader
  → QueueModeration (Phase 2): dispatch ModerateContentJob
```

---

### VideoPublished

| Field | Value |
|-------|-------|
| **Trigger** | Video status set to `published` (after processing or manual) |
| **Source Module** | Videos |
| **Payload** | `{ videoId: UUID, userId: UUID }` |
| **Consumers** | UpdateUserVideoCount, InvalidateFeedCache, IndexForSearch |
| **Queue** | `default` |
| **Retry Policy** | 3 attempts |
| **Failure Handling** | Log error; video is published but counters/cache may be stale |
| **Logging** | `event=video_published video_id={id}` |
| **Metrics** | `videos.published.total` |

---

### ProductCreated

| Field | Value |
|-------|-------|
| **Trigger** | Seller creates a new product |
| **Source Module** | Products |
| **Payload** | `{ productId: UUID, storeId: UUID, sellerId: UUID, categoryId: int }` |
| **Consumers** | IndexForSearch, UpdateStoreProductCount, AuditLog |
| **Queue** | `default` |
| **Retry Policy** | 3 attempts |
| **Failure Handling** | Product exists; search index may lag |
| **Logging** | `event=product_created product_id={id} store_id={storeId}` |
| **Metrics** | `products.created.total` |

---

### OrderPlaced

| Field | Value |
|-------|-------|
| **Trigger** | Buyer submits checkout (`POST /orders`) |
| **Source Module** | Orders |
| **Payload** | `{ orderId: UUID, userId: UUID, storeId: UUID, total: decimal }` |
| **Consumers** | InitiatePayment, NotifySellerNewOrder, AuditLog |
| **Queue** | `payments` for payment; `default` for notifications |
| **Retry Policy** | 5 attempts for payment; 3 for notifications |
| **Failure Handling** | Order stays `pending_payment`; buyer notified to retry |
| **Logging** | `event=order_placed order_id={id} total={total}` |
| **Metrics** | `orders.placed.total`, `orders.total_amount` histogram |

**Flow:**
```
OrderService.create() → dispatch(OrderPlaced)
  → InitiatePayment: call PaymentGatewayService
  → NotifySellerNewOrder: queue SendPushNotificationJob to seller
  → AuditLog: write audit_logs record
```

---

### OrderPaid

| Field | Value |
|-------|-------|
| **Trigger** | Payment webhook confirmed (`payment.success`) |
| **Source Module** | Payments |
| **Payload** | `{ orderId: UUID, paymentReference: string, amount: decimal }` |
| **Consumers** | UpdateOrderStatus, DecrementInventory, NotifyBuyer, NotifySeller, AuditLog |
| **Queue** | `default` |
| **Retry Policy** | 5 attempts (critical — financial) |
| **Failure Handling** | Alert ops immediately; order may show paid but inventory not decremented |
| **Logging** | `event=order_paid order_id={id} ref={paymentReference}` |
| **Metrics** | `orders.paid.total`, `orders.paid_amount` histogram, `payments.success.total` |

**Flow:**
```
PaymentGatewayService.handleWebhook() → dispatch(OrderPaid)
  → UpdateOrderStatus: status → `confirmed`, payment_status → `paid`
  → DecrementInventory: queue DecrementInventoryJob
  → NotifyBuyer: push "Order confirmed"
  → NotifySeller: push "New paid order"
  → AuditLog: write audit_logs record
```

---

### OrderCancelled

| Field | Value |
|-------|-------|
| **Trigger** | Buyer or seller cancels order (before shipping) |
| **Source Module** | Orders |
| **Payload** | `{ orderId: UUID, cancelledBy: UUID, reason: string }` |
| **Consumers** | RestoreInventory, InitiateRefund (if paid), NotifyParties, AuditLog |
| **Queue** | `default` |
| **Retry Policy** | 5 attempts |
| **Failure Handling** | Alert ops; manual reconciliation |
| **Logging** | `event=order_cancelled order_id={id} by={cancelledBy}` |
| **Metrics** | `orders.cancelled.total` |

---

### LiveStreamStarted

| Field | Value |
|-------|-------|
| **Trigger** | Seller starts live stream (`POST /live/start`) |
| **Source Module** | Live Streaming |
| **Payload** | `{ streamId: UUID, sellerId: UUID, storeId: UUID, title: string }` |
| **Consumers** | NotifyFollowers, UpdateActiveStreamsCache, AuditLog |
| **Queue** | `notifications` (bulk push to followers) |
| **Retry Policy** | 3 attempts |
| **Failure Handling** | Stream is live; followers may not be notified |
| **Logging** | `event=live_started stream_id={id} seller_id={sellerId}` |
| **Metrics** | `live.streams.started.total`, `live.streams.active` gauge |

**Flow:**
```
LiveStreamService.start() → dispatch(LiveStreamStarted)
  → NotifyFollowers: queue SendBulkPushNotificationJob
  → UpdateActiveStreamsCache: Redis SET active streams
  → AuditLog: write audit_logs record
```

---

### LiveStreamEnded

| Field | Value |
|-------|-------|
| **Trigger** | Seller ends stream or timeout |
| **Source Module** | Live Streaming |
| **Payload** | `{ streamId: UUID, sellerId: UUID, duration: int, peakViewers: int }` |
| **Consumers** | CloseProviderChannel, UpdateStreamRecord, QueueReplayProcessing (Phase 1.1), RemoveFromActiveCache |
| **Queue** | `default` |
| **Retry Policy** | 3 attempts |
| **Failure Handling** | Stream marked ended; provider channel may remain open (cleanup job) |
| **Logging** | `event=live_ended stream_id={id} duration={duration}` |
| **Metrics** | `live.streams.ended.total`, `live.streams.duration_seconds` histogram |

---

### CommentCreated

| Field | Value |
|-------|-------|
| **Trigger** | User posts a comment on a video |
| **Source Module** | Videos |
| **Payload** | `{ commentId: int, videoId: UUID, userId: UUID, videoOwnerId: UUID }` |
| **Consumers** | NotifyVideoOwner, IncrementCommentCount, QueueModeration (Phase 2) |
| **Queue** | `default` |
| **Retry Policy** | 3 attempts |
| **Failure Handling** | Comment exists; owner may not be notified |
| **Logging** | `event=comment_created comment_id={id} video_id={videoId}` |
| **Metrics** | `comments.created.total` |

---

### VideoLiked

| Field | Value |
|-------|-------|
| **Trigger** | User likes a video |
| **Source Module** | Videos |
| **Payload** | `{ videoId: UUID, userId: UUID, videoOwnerId: UUID }` |
| **Consumers** | IncrementLikeCount, NotifyVideoOwner (if not self-like) |
| **Queue** | `default` |
| **Retry Policy** | 3 attempts |
| **Failure Handling** | Like recorded; counter may lag (Redis flush job reconciles) |
| **Logging** | `event=video_liked video_id={videoId} user_id={userId}` |
| **Metrics** | `likes.created.total` |

---

### UserFollowed

| Field | Value |
|-------|-------|
| **Trigger** | User follows another user |
| **Source Module** | Followers |
| **Payload** | `{ followerId: UUID, followingId: UUID }` |
| **Consumers** | UpdateFollowCounters, NotifyFollowedUser |
| **Queue** | `default` |
| **Retry Policy** | 3 attempts |
| **Failure Handling** | Follow recorded; counters may lag |
| **Logging** | `event=user_followed follower={followerId} following={followingId}` |
| **Metrics** | `follows.created.total` |

---

### NotificationSent

| Field | Value |
|-------|-------|
| **Trigger** | Push notification successfully delivered to FCM |
| **Source Module** | Notifications |
| **Payload** | `{ notificationId: int, userId: UUID, type: string, fcmSuccess: bool }` |
| **Consumers** | TrackDeliveryMetrics |
| **Queue** | `default` |
| **Retry Policy** | 1 attempt (tracking only) |
| **Failure Handling** | Metrics gap only |
| **Logging** | `event=notification_sent id={id} type={type} success={fcmSuccess}` |
| **Metrics** | `notifications.sent.total`, `notifications.failed.total` by type |

---

### AIRecommendationUpdated

| Field | Value |
|-------|-------|
| **Trigger** | AI ranking job completes (Phase 2) |
| **Source Module** | AI / Recommendations |
| **Payload** | `{ userId: UUID, feedType: string, videoIds: UUID[] }` |
| **Consumers** | CacheFeedRanking |
| **Queue** | `ai-processing` |
| **Retry Policy** | 3 attempts |
| **Failure Handling** | Fall back to rule-based ranking |
| **Logging** | `event=ai_recommendation_updated user_id={userId}` |
| **Metrics** | `ai.recommendations.generated.total`, `ai.recommendations.latency_ms` |

---

### SellerVerified

| Field | Value |
|-------|-------|
| **Trigger** | Admin approves seller application |
| **Source Module** | Admin / Seller |
| **Payload** | `{ userId: UUID, storeId: UUID, approvedBy: UUID }` |
| **Consumers** | UpdateUserRole, ActivateStore, NotifySeller, AuditLog |
| **Queue** | `default` |
| **Retry Policy** | 3 attempts |
| **Failure Handling** | Alert ops; manual role fix |
| **Logging** | `event=seller_verified user_id={userId} store_id={storeId}` |
| **Metrics** | `sellers.verified.total` |

---

## Queue Configuration

| Queue Name | Purpose | Workers (Prod) | Priority |
|------------|---------|----------------|----------|
| `default` | Notifications, counters, cache | 2 | Normal |
| `video-processing` | Transcoding, thumbnails | 2 | High |
| `payments` | Payment initiation, webhooks | 1 | Critical |
| `notifications` | Bulk push (live stream alerts) | 2 | Normal |
| `ai-processing` | AI moderation, ranking (Phase 2) | 1 | Low |

---

## Event Flow Diagrams

### Complete Purchase Flow

```mermaid
sequenceDiagram
    participant B as Buyer App
    participant OS as OrderService
    participant PS as PaymentService
    participant PG as Payment Gateway
    participant Q as Queue

    B->>OS: POST /orders
    OS->>OS: dispatch(OrderPlaced)
    OS->>PS: InitiatePayment listener
    PS->>PG: Create payment
    PG-->>B: Payment URL
    B->>PG: Complete payment
    PG->>PS: Webhook payment.success
    PS->>PS: dispatch(OrderPaid)
    PS->>Q: DecrementInventoryJob
    PS->>Q: SendPushNotificationJob (buyer)
    PS->>Q: SendPushNotificationJob (seller)
```

### Video Lifecycle

```mermaid
stateDiagram-v2
    [*] --> Uploading: POST /videos
    Uploading --> Processing: VideoUploaded event
    Processing --> Published: VideoProcessed event
    Processing --> Failed: Processing error
    Published --> Hidden: User/moderator action
    Failed --> [*]
    Hidden --> [*]
```

---

## Document Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-06-27 | Founder & CTO | Initial event flow specification with 16 events |
| 1.1 | 2026-06-27 | Architecture Review | P0 patches: standardize event names, `failed` video status |

---

**Related Documents:** [System Architecture](./docs/02_SYSTEM_ARCHITECTURE.md) · [State Machines](./10_STATE_MACHINES.md) · [Modules](./08_MODULES.md)
