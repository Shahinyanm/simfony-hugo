# Relay

Starter for a senior PHP / Symfony take-home. Read **[BRIEF.md](BRIEF.md)** first — that is the assignment.

This app is already bootstrapped. You are not evaluated on Docker, Flex, or `symfony new`.

## Requirements

- Docker with Compose
- Make

## Commands

```bash
make install      # start stack, composer, migrate, seed demo org
make test         # PHPUnit
make concurrency  # parallel workers, asserts the quota invariant holds
make worker-logs  # follow the usage and outbox workers
make seed         # reset-friendly demo organization
make down         # stop containers
```

App: [http://localhost:8080](http://localhost:8080)

Demo API key (after seed): `demo-acme-key`

```bash
curl -s -H 'X-Api-Key: demo-acme-key' \
  http://localhost:8080/api/organizations/<organization-id>
```

HTTP snippets: `http/demo.http`

## Stack

- PHP 8.4 / Symfony 7.4
- Doctrine ORM + PostgreSQL 16
- Messenger over RabbitMQ 4: `usage` for ingestion, `proxy_fleet` for the broker
- PHPUnit 13 + DAMA Doctrine test bundle

Usage is applied by `worker-usage`; `worker-outbox` relays domain events to the fleet. Both start
with `make install`. RabbitMQ management: [http://localhost:15672](http://localhost:15672) (app/app).

Design decisions and trade-offs: **[NOTES.md](NOTES.md)**.
