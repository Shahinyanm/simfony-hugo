# Relay

Starter for a senior PHP / Symfony take-home. Read **[BRIEF.md](BRIEF.md)** first — that is the assignment.

This app is already bootstrapped. You are not evaluated on Docker, Flex, or `symfony new`.

## Requirements

- Docker with Compose
- Make

## Commands

```bash
make install   # start stack, composer, migrate, seed demo org
make test      # PHPUnit (keep green, or update tests to match your design)
make seed      # reset-friendly demo organization
make down      # stop containers
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
- Messenger (`MESSENGER_TRANSPORT_DSN=sync://` in this starter)
- PHPUnit 13 + DAMA Doctrine test bundle
