# Design System

Version: 1.0  
Project: LiveCommerce Platform  
Status: Architecture Phase  
Document Owner: Founder & CTO  
Last Updated: 2026-06-27

---

## Purpose

Define a single visual language for the entire LiveCommerce platform. Every UI component in the Flutter mobile app must follow these standards.

The design philosophy: **feel like TikTok, not like a marketplace.** Entertainment first. Shopping is seamless and secondary.

---

## Design Principles

1. **Content First** — Video fills the screen. Chrome is minimal.
2. **Thumb-Friendly** — All primary actions reachable with one hand.
3. **Instant Feedback** — Every tap produces immediate visual response.
4. **Minimal Friction** — Maximum 3 taps from video to cart.
5. **Bold & Energetic** — Vibrant accents on dark backgrounds (feed); clean whites (commerce).
6. **Consistent Motion** — Animations are quick (200–300ms), purposeful, never decorative.
7. **Accessible** — WCAG 2.1 AA contrast ratios, 44dp minimum touch targets.

---

## Color Palette

### Brand Colors

| Token | Light Mode | Dark Mode | Usage |
|-------|------------|-----------|-------|
| `primary` | `#FF3366` | `#FF4D7A` | CTAs, likes, live indicator, brand accent |
| `primaryDark` | `#E6295C` | `#E6295C` | Pressed state |
| `secondary` | `#6C5CE7` | `#7C6CF0` | Links, badges, secondary actions |
| `accent` | `#FFAA00` | `#FFBB33` | Discounts, deals, highlights |

### Semantic Colors

| Token | Light Mode | Dark Mode | Usage |
|-------|------------|-----------|-------|
| `success` | `#00C48C` | `#00D999` | Order confirmed, payment success |
| `warning` | `#FFAA00` | `#FFBB33` | Low stock, pending actions |
| `error` | `#FF3B30` | `#FF5757` | Errors, validation, destructive |
| `info` | `#0984E3` | `#29ABE2` | Informational banners |

### Neutral Colors

| Token | Light Mode | Dark Mode | Usage |
|-------|------------|-----------|-------|
| `background` | `#FFFFFF` | `#0D0D0D` | Screen background |
| `surface` | `#F8F9FA` | `#1A1A1A` | Cards, sheets, inputs |
| `surfaceVariant` | `#F0F1F3` | `#262626` | Secondary surfaces |
| `border` | `#E8EAED` | `#333333` | Dividers, borders |
| `textPrimary` | `#1A1A2E` | `#FFFFFF` | Headings, primary text |
| `textSecondary` | `#6B7280` | `#A0A0A0` | Subtitles, metadata |
| `textDisabled` | `#C4C4C4` | `#555555` | Disabled text |
| `overlay` | `#000000` 40% | `#000000` 60% | Video overlays, modals |

### Live Commerce Specific

| Token | Value | Usage |
|-------|-------|-------|
| `liveRed` | `#FF0000` | Live badge, recording indicator |
| `priceGreen` | `#00C48C` | Price tags on video overlay |
| `discountRed` | `#FF3B30` | Strikethrough compare-at price |
| `shoppableTag` | `#FFFFFF` 90% bg | Product tag pill on video |

---

## Typography

**Font Family:** Inter (primary), system fallback (SF Pro / Roboto)

| Token | Size | Weight | Line Height | Usage |
|-------|------|--------|-------------|-------|
| `displayLarge` | 32sp | Bold (700) | 1.2 | Splash, onboarding headlines |
| `displayMedium` | 28sp | Bold (700) | 1.2 | Section titles |
| `headlineLarge` | 24sp | SemiBold (600) | 1.3 | Screen titles |
| `headlineMedium` | 20sp | SemiBold (600) | 1.3 | Card titles, product names |
| `titleLarge` | 18sp | Medium (500) | 1.4 | List item titles |
| `titleMedium` | 16sp | Medium (500) | 1.4 | Subtitles, usernames |
| `bodyLarge` | 16sp | Regular (400) | 1.5 | Body text, descriptions |
| `bodyMedium` | 14sp | Regular (400) | 1.5 | Secondary body, comments |
| `bodySmall` | 12sp | Regular (400) | 1.4 | Captions, timestamps, metadata |
| `labelLarge` | 14sp | SemiBold (600) | 1.2 | Buttons, tabs |
| `labelMedium` | 12sp | SemiBold (600) | 1.2 | Badges, chips, tags |
| `labelSmall` | 10sp | Medium (500) | 1.2 | Tiny labels (LIVE badge) |
| `priceLarge` | 22sp | Bold (700) | 1.2 | Product price (commerce) |
| `priceMedium` | 16sp | SemiBold (600) | 1.2 | Cart item price |

**Rules:**
- Maximum 2 font weights per screen.
- Prices always use `priceLarge` or `priceMedium`.
- Usernames use `titleMedium` with `@` prefix.
- Video captions use `bodyMedium` with max 3 lines.

---

## Spacing

Base unit: **4dp**. All spacing is a multiple of 4.

| Token | Value | Usage |
|-------|-------|-------|
| `xs` | 4dp | Icon-to-text gap |
| `sm` | 8dp | Inner padding (chips, tags) |
| `md` | 12dp | Card inner padding |
| `lg` | 16dp | Screen horizontal padding |
| `xl` | 24dp | Section spacing |
| `xxl` | 32dp | Large section gaps |
| `xxxl` | 48dp | Screen top/bottom safe areas |

**Screen Padding:** 16dp horizontal on all screens except full-screen video feed (edge-to-edge).

---

## Corner Radius

| Token | Value | Usage |
|-------|-------|-------|
| `radiusXs` | 4dp | Chips, small tags |
| `radiusSm` | 8dp | Input fields, small cards |
| `radiusMd` | 12dp | Cards, product images |
| `radiusLg` | 16dp | Bottom sheets, modals |
| `radiusXl` | 24dp | Large cards, profile avatar square |
| `radiusFull` | 999dp | Pills, avatars, circular buttons |

---

## Buttons

### Primary Button
- Background: `primary`
- Text: white, `labelLarge`
- Height: 48dp
- Radius: `radiusFull` (pill shape)
- Pressed: `primaryDark`
- Disabled: `textDisabled` background, 50% opacity

### Secondary Button
- Background: transparent
- Border: 1.5dp `primary`
- Text: `primary`, `labelLarge`
- Height: 48dp
- Radius: `radiusFull`

### Ghost Button
- Background: transparent
- Text: `textPrimary`, `labelLarge`
- No border

### Icon Button
- Size: 44dp × 44dp (minimum touch target)
- Icon size: 24dp
- Background: `overlay` (on video) or transparent (on surfaces)
- Used for: like, comment, share, bookmark on video overlay

### Floating Action Button (Go Live / Upload)
- Size: 56dp
- Background: `primary`
- Icon: 24dp white
- Shadow: elevation 6
- Position: bottom center or bottom right

---

## Cards

### Product Card (Grid)
- Background: `surface`
- Radius: `radiusMd`
- Image aspect ratio: 1:1
- Padding: `sm`
- Title: `titleMedium`, max 2 lines
- Price: `priceMedium`
- Shadow (light): 0 2dp 8dp rgba(0,0,0,0.06)

### Video Card (Grid / Thumbnail)
- Aspect ratio: 9:16
- Radius: `radiusSm`
- Overlay: view count bottom-left, duration bottom-right

### Order Card (List)
- Background: `surface`
- Radius: `radiusMd`
- Padding: `md`
- Status badge top-right
- Divider between items: 1dp `border`

---

## Input Fields

- Height: 48dp
- Background: `surface` (light) / `surfaceVariant` (dark)
- Border: 1dp `border` (default), 2dp `primary` (focused), 2dp `error` (validation)
- Radius: `radiusSm`
- Text: `bodyLarge`
- Label: `bodySmall`, `textSecondary`
- Placeholder: `textDisabled`
- Error message: `bodySmall`, `error`, below field

---

## Navigation

### Bottom Navigation Bar
- Height: 56dp + safe area
- Background: `background` with top border 1dp `border`
- Items: 5 max (Home, Discover, Upload, Cart, Profile)
- Active icon: `primary`, filled variant
- Inactive icon: `textSecondary`, outlined variant
- Label: `labelSmall`
- Upload tab: elevated FAB-style icon (larger, `primary`)

### Top App Bar
- Height: 56dp
- Background: transparent (on feed) or `background` (on other screens)
- Title: `headlineMedium`
- Back button: icon 24dp, 44dp touch target

---

## Bottom Sheets

- Background: `surface`
- Top radius: `radiusLg` (16dp)
- Drag handle: 36dp × 4dp, `border` color, centered, 8dp from top
- Max height: 90% screen
- Used for: product detail from video, comments, checkout summary, filters

---

## Dialogs

- Background: `surface`
- Radius: `radiusLg`
- Padding: `xl`
- Title: `headlineMedium`
- Body: `bodyLarge`
- Actions: right-aligned, Primary + Ghost buttons
- Backdrop: `overlay`

---

## Icons

- Style: Outlined (inactive), Filled (active)
- Set: Material Symbols Rounded (or custom SVG for brand icons)
- Sizes: 20dp (inline), 24dp (standard), 32dp (video overlay actions)
- Video overlay action column: like, comment, bookmark, share (bottom-right, vertical stack, 24dp icons, 44dp touch targets, white with shadow)

---

## Illustrations & Empty States

- Style: Minimal flat vector, brand colors
- Empty feed: "No videos yet" + upload CTA
- Empty cart: Shopping bag illustration + "Browse products" CTA
- Empty orders: Package illustration + "Start shopping" CTA
- Error state: Simple retry illustration + "Try again" button

---

## Animations

| Animation | Duration | Curve | Usage |
|-----------|----------|-------|-------|
| Page transition | 300ms | easeInOut | Screen navigation |
| Bottom sheet | 250ms | easeOut | Sheet open/close |
| Like heart | 400ms | elasticOut | Double-tap like |
| Button press | 100ms | easeInOut | Scale 0.95 |
| Fade in | 200ms | easeIn | Content appear |
| Skeleton shimmer | 1200ms | linear | Loading placeholder |
| Tab switch | 200ms | easeInOut | Bottom nav |
| Product tag appear | 250ms | easeOut | Tag overlay on video |

**Rules:**
- Respect `prefers-reduced-motion` — disable non-essential animations.
- Feed scroll must never drop below 60 FPS — no heavy animations during scroll.
- Video overlay animations run on GPU (Transform/Opacity only).

---

## Loading States

| State | Pattern | Usage |
|-------|---------|-------|
| **Full screen** | Centered circular progress, `primary` color | Initial app load, auth |
| **Feed** | Skeleton cards (9:16 shimmer rectangles) | Feed first load |
| **List** | Skeleton rows (avatar + text lines) | Orders, notifications |
| **Button** | Inline circular progress (20dp), replace label | Submit actions |
| **Image** | `surfaceVariant` placeholder → fade in | Product images, avatars |
| **Video** | Black screen → first frame / thumbnail | Video player |
| **Pull to refresh** | Platform-native refresh indicator, `primary` | Feed, lists |

---

## Error States

| Type | Display | Action |
|------|---------|--------|
| Network error | Inline banner or full-screen | "Retry" button |
| Validation | Red border + message below field | Fix input |
| Server error (500) | Toast + retry option | "Try again" |
| Not found (404) | Empty state illustration | Navigate back |
| Auth expired | Redirect to login | Re-authenticate |
| Payment failed | Dialog with reason | "Retry payment" or "Cancel" |

---

## Empty States

Every list/feed screen must have an empty state:

| Screen | Message | CTA |
|--------|---------|-----|
| Feed (following) | "Follow creators to see their videos" | "Discover" |
| Cart | "Your cart is empty" | "Browse products" |
| Orders | "No orders yet" | "Start shopping" |
| Notifications | "No notifications" | — |
| Bookmarks | "No saved videos" | "Explore feed" |
| Search | "No results for '{query}'" | — |
| Seller products | "Add your first product" | "Add product" |

---

## Accessibility

| Rule | Standard |
|------|----------|
| Touch targets | Minimum 44 × 44 dp |
| Color contrast (text) | 4.5:1 minimum (WCAG AA) |
| Color contrast (large text) | 3:1 minimum |
| Screen reader | Semantics labels on all interactive elements |
| Focus order | Logical top-to-bottom, left-to-right |
| Motion | Respect system reduced-motion setting |
| Text scaling | Support up to 1.3× without layout break |
| Color alone | Never use color as only indicator (add icon/text) |

---

## Dark Theme

Dark theme is the **default for video feed and live streams** (immersive content consumption).

| Context | Default Theme |
|---------|---------------|
| Video feed | Dark |
| Live stream | Dark |
| Product pages | Light |
| Cart / Checkout | Light |
| Profile | System preference |
| Settings | System preference |

Theme follows system setting with manual override in Settings.

---

## Light Theme

Used for commerce flows (product, cart, checkout, orders, seller dashboard) where readability and trust are prioritized.

- Background: white
- Cards: `surface` (#F8F9FA)
- Primary actions: `primary` pill buttons
- Price emphasis: `priceLarge` in `textPrimary`

---

## Responsive Rules

| Breakpoint | Width | Layout |
|------------|-------|--------|
| Mobile (default) | < 600dp | Single column, full-width |
| Tablet | 600–840dp | Two-column product grid, centered feed (max 480dp) |
| Large tablet | > 840dp | Not MVP priority |

- Video feed: always full-width, 9:16 aspect ratio.
- Product grid: 2 columns (mobile), 3 columns (tablet).
- Bottom sheet: max 480dp width centered on tablet.

---

## Localization Rules

| Rule | Detail |
|------|--------|
| Languages (MVP) | Uzbek (uz), Russian (ru) |
| Default | Uzbek |
| Text expansion | Allow 30% expansion for Russian |
| RTL | Not MVP (planned for Arabic in v2) |
| Numbers | Locale-formatted (space thousands separator for UZS) |
| Currency | `250 000 so'm` (Uzbek), `250 000 сум` (Russian) |
| Dates | Locale format (`27 iyun 2026` / `27 июня 2026`) |
| Pluralization | Use ICU plural rules (.arb files) |
| Hardcoded strings | Forbidden — all via `AppLocalizations` |

---

## Motion Guidelines

1. **Purpose over decoration** — Every animation communicates state change.
2. **Speed** — UI transitions ≤ 300ms. Users expect instant response.
3. **Like animation** — Heart scale 0 → 1.3 → 1.0 with particle burst (400ms).
4. **Tab transitions** — Crossfade, no slide (prevents disorientation).
5. **Add to cart** — Product image flies to cart icon (300ms arc) — optional delight.
6. **Live badge** — Pulsing red dot (1.5s loop) on live streams.
7. **Pull to refresh** — Native indicator, no custom animation.
8. **Reduced motion** — Replace animations with instant state changes.

---

## Component Library (Flutter)

All reusable components live in `mobile/lib/shared/widgets/`:

| Component | File | Description |
|-----------|------|-------------|
| AppButton | `app_button.dart` | Primary, secondary, ghost variants |
| AppTextField | `app_text_field.dart` | Styled input with validation |
| UserAvatar | `user_avatar.dart` | Circle avatar with fallback |
| CachedImage | `cached_image.dart` | Network image with placeholder |
| PriceTag | `price_tag.dart` | Formatted price with currency |
| LoadingIndicator | `loading_indicator.dart` | Branded spinner |
| ErrorWidget | `error_widget.dart` | Retry error state |
| EmptyState | `empty_state.dart` | Illustration + message + CTA |
| BottomSheetWrapper | `bottom_sheet_wrapper.dart` | Consistent sheet styling |
| ProductCard | `product_card.dart` | Grid product card |
| VideoOverlay | `video_overlay.dart` | Action buttons on video |
| LikeAnimation | `like_animation.dart` | Double-tap heart |
| LiveBadge | `live_badge.dart` | Pulsing LIVE indicator |
| StatusBadge | `status_badge.dart` | Order/product status pill |

---

## Document Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-06-27 | Founder & CTO | Initial design system |

---

**Related Documents:** [Master Plan](./12_MASTER_PLAN.md) · [PRD](./docs01_PRD.md) · [Project Structure](./docs/05_PROJECT_STRUCTURE.md)
