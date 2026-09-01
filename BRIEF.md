# Relay — take-home (senior PHP / Symfony)

Timebox: **4 to 6 hours**. Stop when the time is up; a partial, well-reasoned solution beats an unfinished rewrite.

This repository is already a working Symfony 7.4 app. **Do not spend time on Docker, Flex, or project bootstrap** — that is not what we evaluate.

## Product

Relay sells proxy bandwidth. An **organization** has a monthly data **quota** (bytes) and a running **consumption**. It owns **credentials** (proxy logins) that the edge uses to authenticate traffic.

Usage is reported asynchronously: `POST /api/organizations/{id}/usage` accepts one event and dispatches `IngestUsageMessage`. The handler currently does a naive `consumption += bytes`.

Auth: header `X-Api-Key` (one key per organization).

This starter is a **simplistic in-process model**. That is not how this would run in production.

**Your job is to take it to a production-ready model**: durable async processing, safe under retries and concurrency, domain invariants held.

The stack is open: Redis, RabbitMQ, or anything else that fits. **If you use Redis, RabbitMQ, or another tool, use it for real** — running infra, code that actually talks to it. A line in `.env` or `NOTES.md` with no working path is not enough; leave it out rather than half-wire it.

## What already works

```bash
make install   # docker, composer, migrations, demo seed
make test      # suite green (keep or update the existing tests)
```

| Method | Path | Body |
|---|---|---|
| `GET` | `/api/organizations/{id}` | — |
| `POST` | `/api/organizations/{id}/usage` | `{ "bytes": 1000000, "event_id": "evt-1" }` → **202** |
| `POST` | `/api/organizations/{id}/topups` | `{ "bytes": 5000000 }` → **200** (quota increased) |
| `GET` | `/api/credentials/{id}` | — |

Demo data after `make seed`: API key `demo-acme-key`, two credentials. See also `http/demo.http`.

The current implementation is **intentionally incomplete**. `event_id` is accepted and passed to the message, then ignored. Top-up only bumps `quota_bytes`.

## Your work

Implement the behaviour below. Keep `make test` green: existing tests should still pass, **or be updated** when your design makes the old assertions obsolete. Add tests for every invariant you claim.

### 1. Idempotent ingestion

The same `event_id` for the same organization must never increase consumption twice (retries, at-least-once delivery).

A *different* `event_id` with the same `bytes` is a new event.

### 2. Hard cut-off

When `consumption >= quota` after applying usage:

- every credential of that organization must become `enabled: false`
- a message must be dispatched so a **broker** (the proxy fleet) can be notified — model this as you see fit (`Messenger` is already in the project)
- credentials must not stay enabled while the organization is over quota

Put this in the domain / application layer, not in the HTTP controller.

### 3. Top-up

`POST /api/organizations/{id}/topups` already increases the organization **quota** (not consumption). Auth and `bytes > 0` are in place.

Complete the behaviour:

- if the organization is no longer over quota, re-enable its credentials and notify the broker
- if it is *still* over quota after the top-up, credentials stay disabled
- keep this in the domain / application layer (the controller should stay thin)

A top-up that lands while usage is still being applied must not leave credentials enabled over quota.

### 4. Concurrency invariant

Two usage events applied at the same time must not leave the system in a state where **any credential is enabled and `consumption >= quota`**.

Document the strategy in a short `NOTES.md`. A correct invariant with an honest limitation is better than a silent race.

If it helps you prove the invariant, you may add your own load harness (parallel workers, not `curl` against the single-threaded `php -S` server). That is optional.

## Out of scope

- Real payment provider, invoices, cron auto-top-up
- MongoDB, API Platform
- Rewriting the app, adding a frontend, 100% coverage
- Changing PHP version unless you have a documented reason

## Bonus (optional, only if time remains)

- Notify when consumption crosses 80% of quota (once per threshold, not on every event)
- Batch ingest: many events in one go (same invariants)
- Reject usage ingestion for an already disabled org in a way that is still idempotent

## Deliverable

- A git history or a patch we can run with `make test` (and `make install` if you changed Compose)
- `NOTES.md` at the repo root: what you made production-ready, trade-offs, what you would do with more time (20–40 lines)

We will walk through the code together (~45 minutes). Be ready to explain the invariant, not only the happy path.
