# LiveCommerce Mobile

Flutter mobile application for the LiveCommerce platform.

## Requirements

- Flutter 3.x (stable channel)
- Dart 3.7+

## First-Time Setup

If platform folders (`android/`, `ios/`) are missing, generate them:

```bash
flutter create . --org uz.livecommerce --project-name livecommerce_mobile
```

Then install dependencies:

```bash
flutter pub get
```

## Run

```bash
# Android emulator (API points to host machine)
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8080/api/v1

# iOS simulator
flutter run --dart-define=API_BASE_URL=http://localhost:8080/api/v1
```

## Verify

```bash
flutter analyze
flutter test
```

## Architecture

Clean Architecture with Riverpod. See [Project Structure](../docs/05_PROJECT_STRUCTURE.md).

Sprint 0.1 provides scaffold only — no business features yet.
