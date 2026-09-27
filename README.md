# LMS Financial Core

A Laravel 11 financial core for an LMS subscription system.

The application handles subscriptions, payments, revenue allocation, monthly revenue accrual, refunds, instructor balances, and instructor payouts.

The implementation focuses on financial correctness, idempotency, concurrency safety, and safe handling of uncertain external provider responses.

---

## Tech Stack

* PHP 8.4
* Laravel 11
* MySQL
* Livewire 3
* Filament 3
* Pest
* Docker / Docker Compose

---

## Features

### Subscriptions

Supports:

* Monthly subscriptions
* 3-month subscriptions
* Annual subscriptions
* Upfront payment

Subscription creation uses idempotency keys to prevent duplicate payment operations.

### Revenue Allocation

Subscription revenue is split between:

* Platform revenue
* Instructor revenue

The platform percentage is configurable through:

```env
PLATFORM_REVENUE_PERCENTAGE=20
```

Instructor allocations are deterministic and use integer minor currency units.

### Monthly Revenue Accrual

Subscriptions are paid upfront, but revenue is accrued monthly.

The accrual process creates idempotent ledger entries for:

* Instructor revenue
* Platform revenue

### Ledger

The ledger records financial events using integer minor currency units.

Supported entry types include:

* Subscription revenue
* Refund
* Adjustment

Each ledger entry has a unique idempotency key.

### Refunds

Refunds are calculated based on unused subscription months.

Refund processing protects against:

* Duplicate refunds
* Concurrent refund requests
* Provider timeout ambiguity

### Instructor Payouts

Eligible instructor revenue is grouped into payouts by period.

Payout processing uses:

* Database transactions
* `lockForUpdate()`
* Idempotency keys
* Unique database constraints
* Queued jobs

### Filament Dashboard

A read-only Filament interface displays:

* Total earned
* Total paid
* Outstanding balance
* Payout history

---

# Setup

## 1. Clone the repository

```bash
git clone <repository-url>
cd lms-mony-core
```

## 2. Start Docker

```bash
docker compose up -d
```

## 3. Install dependencies

If dependencies are not already installed:

```bash
docker compose exec php composer install
```

## 4. Configure environment

Copy the environment file:

```bash
cp src/app/.env.example src/app/.env
```

Generate the application key:

```bash
docker compose exec php php artisan key:generate
```

Configure the database and application settings in `.env`.

The platform revenue percentage can be configured with:

```env
PLATFORM_REVENUE_PERCENTAGE=20
```

## 5. Run migrations

```bash
docker compose exec php php artisan migrate
```

## 6. Seed demo data

```bash
docker compose exec php php artisan db:seed
```

The seeders provide plans and demo data used by the application and Filament dashboard.

---

# Running the Application

The application is available through the configured Docker web server.

The Filament panel is available at:

```text
http://localhost:8060/admin
```

---

# Financial Commands

## Accrue Revenue

Accrues instructor and platform revenue for the previous month:

```bash
docker compose exec php php artisan revenue:accrue
```

## Process Payouts

Creates eligible instructor payouts and dispatches payout processing jobs:

```bash
docker compose exec php php artisan payouts:process
```

## Process Queue

Run a queue worker:

```bash
docker compose exec php php artisan queue:work
```

For a single job:

```bash
docker compose exec php php artisan queue:work --once
```

---

# Scheduler

The application uses Laravel's scheduler for recurring financial processes.

Current schedule:

```text
Revenue accrual:
1st day of every month at 00:10

Payout processing:
2nd day of every month at 00:10
```

To inspect the schedule:

```bash
docker compose exec php php artisan schedule:list
```

For local development, the scheduler can be started with:

```bash
docker compose exec php php artisan schedule:work
```

A queue worker is also required to process queued payout jobs.

---

# Running Tests

Run the complete test suite:

```bash
docker compose exec php php artisan test
```

The test suite covers:

* Subscription creation
* Payment processing
* Revenue allocation
* Revenue accrual
* Ledger idempotency
* Refund calculation
* Refund concurrency
* Provider timeout handling
* Payout creation
* Payout processing
* Payout concurrency
* Instructor balance calculation

---

# Financial Correctness

The implementation uses multiple layers of protection against duplicate financial operations.

## Idempotency

Important financial operations use unique idempotency keys.

Examples include:

```text
subscription payment
revenue allocation
ledger entry
refund
payout
```

## Database Constraints

The database contains unique constraints for critical financial records.

For example:

```text
ledger_entries.idempotency_key
payouts.idempotency_key
payout_items.ledger_entry_id
```

## Concurrency

Critical state transitions use database transactions and row-level locking.

Example:

```php
lockForUpdate()
```

This prevents concurrent workers from processing the same financial state transition incorrectly.

## Provider Timeouts

A provider timeout does not automatically mean that an operation failed.

Operations that can have an uncertain outcome can be marked as `UNKNOWN` and reconciled through the provider's status endpoint.

---

# Assumptions

The implementation makes the following assumptions:

1. Subscription plans are paid upfront.
2. Platform revenue percentage is configurable.
3. Instructor revenue is split equally among instructors associated with a subscription.
4. Remainders are distributed deterministically.
5. Financial amounts are stored as integer minor currency units.
6. Revenue is accrued monthly even though subscriptions are paid upfront.
7. Refunds cover unused subscription months.
8. Already-earned revenue is not reversed by the current refund policy.
9. A ledger entry can be included in only one payout.
10. Partial payouts are not required by the current business rules.
11. External payment, refund, and payout providers are represented by mock implementations for the assignment.
12. Database transactions and row-level locks are sufficient for the current modular-monolith architecture.

---

# Architecture

Detailed architectural decisions are documented in:

```text
docs/ARCHITECTURE.md
```

The document covers:

* Domain model
* Database design
* Revenue allocation
* Idempotency
* Provider timeout handling
* Concurrency
* Scaling considerations
* Trade-offs
* Known limitations

---

# AI Usage

AI usage and engineering decisions are documented in:

```text
docs/AI_USAGE.md
```

This document explains how AI was used during implementation, which decisions were made manually, rejected suggestions, trade-offs, and the engineering considerations behind the final design.

---

# Demo

The project includes a read-only Filament interface for viewing instructor financial balances and payout history.

---

# Production Considerations

For a production deployment, potential improvements include:

* Redis-backed queues
* Multiple queue workers
* Queue monitoring
* Structured financial logging
* Provider reconciliation workers
* Read models for frequently accessed balances
* Ledger partitioning and archival
* Additional observability and alerting
* Database performance tuning based on real workload

These improvements should be introduced based on measured bottlenecks rather than adding unnecessary infrastructure prematurely.
