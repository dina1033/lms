# AI Usage

AI tools were used throughout this task primarily as an engineering discussion and validation tool. I used AI to challenge design decisions, explore edge cases, generate test scenarios, and speed up implementation of supporting code. The final architecture and business decisions were reviewed, modified, and validated by me.

## 1. How I Used AI During the Task

I mainly used AI in the following areas:

* Discussing and clarifying the business rules before implementation.
* Exploring how the system should behave at large scale, including hundreds of thousands of active subscriptions and tens of millions of ledger records.
* Designing and reviewing concurrency scenarios.
* Writing and improving automated test cases, especially concurrency and idempotency tests.
* Reviewing approaches for preventing duplicate payouts, duplicate refunds, and duplicate ledger entries.
* Configuring Filament resources and read-only balance/history screens.

## 2. Main Prompts and Workflows

The main workflows I relied on were iterative discussions around specific engineering problems.

Examples included:

* Discussing how to model subscription revenue and instructor entitlements.
* Asking how the payout flow should behave when two workers process the same instructor and period concurrently.
* Designing tests for two concurrent workers attempting to create or process the same payout.
* Discussing provider failure, timeout-after-success, retry, and reconciliation scenarios.
* Reviewing refund behavior when revenue for previous months has already been paid out.
* Asking for test cases covering race conditions and duplicate execution.
* Using AI to help configure Filament resources after the core business logic was already designed.

The workflow was generally:

1. Define the business rule.
2. Discuss possible designs with AI.
3. Identify edge cases and failure scenarios.
4. Choose the design based on the requirements and engineering trade-offs.
5. Implement it.
6. Write automated tests.
7. Run the tests and use failures to refine the implementation.
8. Manually review the resulting behavior.

## 3. Fully Generated vs. Manually Designed or Modified

AI assisted with implementation details, test structure, and boilerplate, but the important business and architectural decisions were manually reviewed and selected by me.

### AI-assisted

Examples include:

* Initial test-case structures.
* Concurrency test scenarios.
* Some Pest test implementation details.
* Filament resource configuration and boilerplate.
* Identifying additional edge cases that should be covered by tests.
* Assistance with structuring and refining project documentation.

### Manually designed or significantly modified

I personally designed and/or modified:

* The revenue allocation model.
* The monthly revenue accrual approach.
* The payout lifecycle and idempotency strategy.
* The refund business rule.
* The ledger structure and its role as the financial source of record.
* The concurrency boundaries and database locking strategy.
* The decision to prevent duplicate payout items at the database level.
* The final test scenarios and their expected behavior.
* The final architecture and project structure.


## 4. Engineering Decisions I Personally Made

One of the main decisions I made was how instructor revenue should become payable.

Instead of considering the instructor's entire subscription entitlement payable immediately, I chose to **accrue instructor revenue month by month**.

For example, if an annual subscription generates an instructor entitlement of 80,000 minor units, the entitlement is distributed across the subscription months. Each month creates the corresponding ledger entry.

This means that only revenue that has actually been accrued becomes eligible for payout.

This design also makes the financial history easier to reason about and supports large-scale periodic processing without treating the entire subscription amount as immediately payable.

### Refund decision

I also intentionally chose a month-based refund rule.

When a subscription is refunded, only the unused subscription months are refundable. Revenue that has already been earned through previous months is not reversed under this business rule.

I explicitly rejected an alternative approach where the refund process would try to take money back from an instructor's already-earned ledger balance.

This keeps the refund calculation independent from already-paid instructor payouts and avoids introducing a second financial reversal mechanism that was not required by the assignment's business rules.


## 5. What Differentiates the Solution

A major focus of the implementation was not only making the happy path work, but making the financial operations safe under repeated execution and concurrency.

The solution explicitly addresses:

* Duplicate payout creation.
* Duplicate payout processing.
* Duplicate ledger entries.
* Concurrent workers attempting the same operation.
* Provider failures.
* Provider timeout/unknown states.
* Refund idempotency.
* Reconciliation of unknown refund states.
* Deterministic distribution of rounding remainders.
* Monthly revenue accrual.
* Large-volume query considerations.

The financial data is modeled around immutable/idempotent ledger entries and explicit payout relationships instead of calculating balances from mutable counters.

This makes the system easier to audit and safer to operate when jobs are retried.

## 6. Trade-offs, Architectural Decisions, and Intentional Improvements

### Monthly accrual vs. immediate entitlement

I chose monthly accrual because it provides a clearer relationship between earned revenue and payable revenue.

The trade-off is that the system creates more ledger records and requires periodic accrual processing. However, this is preferable for the assignment because it provides a better financial audit trail and prevents future revenue from becoming payable prematurely.

### Database constraints vs. application-only checks

I intentionally used database uniqueness constraints in addition to application-level checks.

Application checks improve normal execution, while database constraints protect against race conditions between workers.

The trade-off is that duplicate attempts can result in constraint violations that need to be handled appropriately, but the resulting system is safer under concurrency.

### Row locking

`lockForUpdate()` is used around critical state transitions.

The trade-off is reduced concurrency for the same financial entity while it is being processed. This is intentional because correctness is more important than parallel processing of the exact same payout/refund.

### Mock external providers

The assignment uses mock providers for payments, payouts, and refunds.

I kept the provider interactions behind interfaces so that the application logic does not depend directly on the mock implementation. This makes it possible to replace the provider implementation without changing the core financial workflows.

### Deterministic rounding

When revenue cannot be divided evenly, the remainder is distributed deterministically using instructor ordering.

This avoids losing money while also ensuring that repeated calculations produce the same result.

