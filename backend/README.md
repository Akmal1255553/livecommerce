# LiveCommerce Backend

Laravel 12 REST API for the LiveCommerce platform.

## Requirements

- PHP 8.4+ (via Docker recommended)
- PostgreSQL 16, Redis 7, MinIO, Mailpit (via Docker Compose)

## Setup (Docker)

From repository root:

```bash
docker compose -f docker/docker-compose.yml up -d --build
docker compose -f docker/docker-compose.yml exec app composer install
docker compose -f docker/docker-compose.yml exec app cp .env.example .env
docker compose -f docker/docker-compose.yml exec app php artisan key:generate
docker compose -f docker/docker-compose.yml exec app php artisan migrate
```

## Verify

```bash
curl http://localhost:8080/api/v1/health
```

## Development Commands

```bash
# Tests
docker compose -f docker/docker-compose.yml exec app php artisan test

# Code style
docker compose -f docker/docker-compose.yml exec app ./vendor/bin/pint

# Queue worker
docker compose -f docker/docker-compose.yml --profile workers up -d worker
```

## Architecture

See [Project Structure](../docs/05_PROJECT_STRUCTURE.md) and [Engineering Rules](../docs/06_ENGINEERING_RULES.md).

Business logic belongs in `app/Services/`. Controllers stay thin. No business code in Sprint 0.1 — infrastructure only.
