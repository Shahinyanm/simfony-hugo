# Notes

**The invariant.** No credential is enabled while its organization is at or over quota. It lives in one place, `QuotaGuard::synchronize()`, which drives credentials to the required state rather than toggling them and reports what actually changed, so the broker hears about each real transition once. Cut-off and top-up recovery are the same rule at two moments. One gap was easy to miss: the guard only reaches credentials that already exist, so a credential created for an organization already over quota would have been born enabled — `Credential::__construct` now decides its own initial state.

**Idempotent ingestion.** Every event is a row in `usage_events`, unique on `(organization_id, event_id)`, inserted with `ON CONFLICT DO NOTHING`; the affected row count decides whether it is new. Catching Doctrine's unique-violation exception instead would close the EntityManager and kill the worker on a perfectly normal at-least-once retry. Events arriving after the quota is gone are recorded as `rejected_over_quota` rather than dropped — dropped, a redelivery after a top-up would take the other branch, and idempotency would silently depend on timing.

**Concurrency.** Usage and top-up both open with `SELECT ... FOR UPDATE` on the organization row and finish inside that transaction. The invariant spans two tables, so an atomic `consumption + n` would guard only half of it and leave the credential decision racing; the row is where both halves meet. That is also what makes a top-up landing mid-ingestion safe — it queues on the same lock. Deadlock is not a concern: every path locks in the same order and every transaction touches one organization.

`make concurrency` runs 8 parallel workers, 2 concurrent top-ups, 200 events, and checks four things: no credential enabled over quota, consumption equal to the sum of applied events, every event recorded once, none lost. It was validated against a deliberately broken build — with the lock removed it reports 250 000 of 2 000 000 bytes applied and exits non-zero.

**Notifying the fleet.** The domain event goes to a Doctrine-backed outbox in the same transaction as the state change; a separate worker republishes it to RabbitMQ. Database and broker cannot be written atomically, so the question is how to be wrong: announce a change that may roll back, lose the notification if the process dies after the commit, or pay one extra hop. Only the last repairs itself. Delivery is at-least-once, so the message carries target state and `occurred_at` rather than a delta, and the relay runs as a single consumer — two could deliver "disabled" and "enabled" out of order.

**Operational.** `lock_timeout` is 5s on the database role. Without it a stuck transaction blocks an organization indefinitely and top-ups hang until the web server gives up; with it the problem surfaces as a retry.

**Limits.**
- Per-organization throughput is capped by the row lock. Batch ingest amortises it; sharding the queue by `organization_id` removes the contention while keeping order.
- The fleet learns one hop late — bounded and self-healing, unlike the alternative.
- `usage_events` grows without bound; partition by month and archive.
- `QuotaGuard` loads the whole credential collection. Fine for tens of logins per organization, a bulk `UPDATE ... RETURNING id` beyond that.
- Top-up waits for the lock inside the HTTP request. Queueing it would mean the response can no longer return current state.

**Deliberate non-decisions.** Top-up is not idempotent: it has no operation id, only `bytes`, so two identical requests are two real payments and must add up. Faking it on a synthetic key would quietly swallow a legitimate second payment; the right moment is a payment provider, where the payment id becomes the key and the mechanism is the one already used for usage. Rejecting usage for an exhausted organization is a product call, not a technical one — the edge may have moved those bytes before it was told to stop. Implemented as `reject` per the brief; in production it should be a setting.

**With more time.** The 80% threshold and batch ingest are designed but unbuilt — four required invariants with proof beat two more features without it. Then queue metrics, especially age of the oldest outbox row, which is the real divergence signal.
