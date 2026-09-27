# Architecture

## 1. Overview

This project implements the financial core of an LMS using Laravel 11.

The system handles:

* Paid subscriptions
* Subscription payments
* Revenue allocation between the platform and instructors
* Monthly revenue accrual
* Ledger-based financial records
* Refund processing
* Instructor payouts
* Idempotent and concurrency-safe financial operations
* Read-only instructor financial visibility through Filament

The main architectural goal is to keep financial operations deterministic and safe under retries, concurrent workers, and uncertain external provider responses.

---

## 2. Domain Model

The main financial entities are:

### Plan

Defines the subscription type, price, currency, and duration in months.

Supported durations include monthly, three-month, and annual plans.

### Subscription

Represents a user's active subscription.

A subscription stores:

* User
* Plan
* Amount in minor currency units
* Currency
* Status
* Start and end dates
* Idempotency key

### Subscription Payment

Represents the payment attempt associated with a subscription.

Payment state is kept separate from subscription state because the payment provider has its own lifecycle and may return success, failure, or an uncertain result.

### Subscription Instructor

Connects subscriptions with the instructors whose content is included in the subscription.

A unique constraint prevents the same instructor from being attached to the same subscription more than once.

### Revenue Allocation

Stores the total revenue entitlement for each instructor for a subscription.

The allocation is calculated once and then used as the source for monthly accrual.

### Ledger Entry

Represents a financial event.

The ledger supports:

* Instructor revenue
* Platform revenue
* Refunds
* Adjustments

Ledger entries use integer minor currency units and unique idempotency keys.

### Payout

Represents a settlement operation for an instructor over a specific period.

### Payout Item

Connects individual ledger entries to a payout.

Each ledger entry can belong to at most one payout through a unique constraint on `ledger_entry_id`.

### Refund

Represents a refund operation associated with a subscription payment.

Refunds have their own idempotency key and provider state.

---

## 3. Revenue Allocation Strategy

The platform revenue percentage is configurable through:

```env
PLATFORM_REVENUE_PERCENTAGE=20
```

For a subscription amount of 12,000:

```text
Platform percentage = 20%
Platform revenue     = 2,400
Instructor pool      = 9,600
```

The instructor pool is divided equally among all instructors associated with the subscription.

All calculations use integer minor currency units.

### Remainder handling

When the amount cannot be divided equally, the remainder is distributed deterministically according to instructor ID ordering.

For example:

```text
100 / 3

Instructor 1 = 34
Instructor 2 = 33
Instructor 3 = 33
```

This guarantees that:

```text
sum(all allocations) = original amount
```

The allocation is stored in `revenue_allocations` and is not recalculated during every monthly accrual.

---

## 4. Monthly Revenue Accrual

Although subscriptions are paid upfront, revenue is recognized monthly.

The allocation is divided across the subscription's duration in months.

Any remainder from the division is assigned to the earliest months so that the total accrued amount exactly matches the original allocation.

The accrual process creates ledger entries with deterministic idempotency keys.

Example:

```text
revenue:subscription:{subscription_id}:instructor:{instructor_id}:{YYYY-MM}
```

Platform revenue uses a separate idempotency key:

```text
platform-revenue:subscription:{subscription_id}:{YYYY-MM}
```

Because these keys are unique at the database level, rerunning the accrual command does not create duplicate ledger entries.

---

## 5. Ledger and Instructor Balance

The ledger is used as the source of truth for earned financial activity.

Instructor balance is calculated as:

```text
Total Earned
    -
Total Paid
    =
Outstanding
```

### Total Earned

The sum of the instructor's revenue ledger entries.

### Total Paid

The sum of payout items belonging to payouts with status `PAID`.

### Outstanding

The difference between earned and paid amounts, with a lower bound of zero.

This approach avoids maintaining a mutable balance column that could become inconsistent with the underlying financial history.

---

## 6. Idempotency Approach

Idempotency is implemented at multiple levels.

### Application-level idempotency

Services check existing state before performing an operation.

Examples include:

* Already paid payouts
* Existing revenue allocations
* Existing refunds
* Existing ledger entries

### Database-level idempotency

Important financial identifiers have unique database constraints.

Examples:

```text
subscription.idempotency_key
subscription_payments.idempotency_key
revenue_allocations(subscription_id, instructor_id)
ledger_entries.idempotency_key
payouts.idempotency_key
refunds.idempotency_key
payout_items.ledger_entry_id
```

The database constraints are an additional protection layer against duplicate operations caused by retries or concurrent workers.

---

## 7. Concurrency Control

Financial state transitions use database transactions and row-level locking where required.

For example, payout processing locks the payout record using:

```php
lockForUpdate()
```

This prevents concurrent workers from processing the same payout state transition simultaneously.

The design intentionally combines:

1. Database transactions
2. Row-level locking
3. Unique constraints
4. Explicit state checks

The goal is to make retries and concurrent execution safe without relying only on application-level checks.

---

## 8. Payout Architecture

Payout processing is split into two stages.

### Stage 1 — Payout creation

The `payouts:process` command:

1. Finds eligible revenue ledger entries.
2. Groups them by instructor and period.
3. Creates an instructor payout.
4. Dispatches `ProcessInstructorPayoutJob`.

### Stage 2 — Provider processing

The queued job calls the payout processing service.

The service:

1. Locks the payout.
2. Checks its current state.
3. Marks it as processing.
4. Calls the external payout provider.
5. Stores the provider result.
6. Marks the payout as paid or failed.

This separation keeps the command responsible for orchestration while external provider communication is handled asynchronously.

---

## 9. Provider Timeout Handling

External providers can return uncertain results.

A timeout does not necessarily mean that the provider did not process the operation.

For that reason, the design distinguishes an uncertain provider outcome from a confirmed failure.

For refunds, an uncertain operation can enter an `UNKNOWN` state and later be reconciled using the provider's status endpoint.

The reconciliation flow is:

```text
Provider timeout
       ↓
UNKNOWN
       ↓
Reconciliation
       ↓
Provider status check
       ↓
Final state
```

This avoids blindly resubmitting an operation that may already have succeeded.

The same principle can be extended to payment and payout providers in a production implementation.

---

## 10. Refund Strategy

Refunds are calculated based on unused subscription months.

For example:

```text
Annual subscription = 12,000
Refund during month 5
Unused months       = 7
Refund              = 7,000
```

The refund calculation uses integer minor units and preserves deterministic rounding.

Refund processing also uses locking and idempotency to prevent the same amount from being refunded multiple times during concurrent requests.

---

## 11. Key Trade-offs

### Database locks instead of distributed locks

The current implementation uses database row-level locking because the critical financial state transitions are already performed inside database transactions.

This keeps the implementation simpler and avoids introducing distributed locking infrastructure prematurely.

### Ledger instead of mutable balance

A ledger provides an auditable financial history and allows balances to be derived from recorded financial events.

The trade-off is that balance queries can become more expensive as the ledger grows.

### No partial payout state

The implementation does not introduce a partial payout state because it is not required by the current business rules.

Adding it would introduce additional state transitions and reconciliation complexity.

### Modular monolith instead of microservices

The project uses Laravel services, queries, jobs, and commands within one application.

The system can later be split into services if actual workload characteristics justify that complexity.

---

## 12. Scaling Considerations

The assignment targets approximately 500,000 active subscriptions and tens of millions of financial records.

The current design includes several measures intended to support this scale:

* Indexed foreign keys
* Composite indexes for common queries
* `chunkById()` for large subscription processing
* Queued payout processing
* Idempotent commands and jobs
* Database constraints for correctness
* Ledger queries scoped by instructor and period

For a significantly larger production workload, additional improvements could include:

* Redis-backed queues
* Multiple queue workers
* Queue monitoring
* Read models or balance projections
* Ledger partitioning
* Archival strategies
* More extensive reconciliation workers
* Database read replicas where appropriate

These optimizations should be driven by measured workload and query performance rather than introduced prematurely.

---

## 13. Known Limitations

The current implementation intentionally keeps the architecture focused on the assignment requirements.

Known limitations include:

* The payout provider is represented by a mock implementation.
* Production-grade distributed observability is not implemented.
* Balance queries are calculated from ledger and payout data rather than maintained as a dedicated projection.
* The current application does not implement a distributed locking service.
* Provider reconciliation can be expanded for additional provider-specific failure modes.
* The current implementation is a modular monolith rather than a distributed architecture.

These are considered production-hardening improvements rather than requirements for the current assignment.

---

## 14. Commands

### Accrue monthly revenue

```bash
php artisan revenue:accrue
```

Accrues instructor and platform revenue for the previous month.

### Process instructor payouts

```bash
php artisan payouts:process
```

Creates eligible instructor payouts and dispatches payout processing jobs.

### Run queue worker

```bash
php artisan queue:work
```

Processes queued payout and other asynchronous jobs.

### Run tests

```bash
php artisan test
```

---

## 15. Scheduler

The Laravel scheduler runs the financial commands automatically.

Current schedule:

```text
Revenue accrual:
1st day of every month at 00:10

Payout processing:
2nd day of every month at 00:10
```

The scheduler can be inspected with:

```bash
php artisan schedule:list
```
