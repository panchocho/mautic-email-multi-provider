# Configuration

## Root key

`smart_mailer_router`

## Qué se configura

- `default_profile`
- `default_strategy`
- `providers`
- `health`
- `warmup`
- `retry`
- `maintenance`
- `queues`

## Providers

Cada provider puede declarar:

- `enabled`
- `type`
- `weight`
- `priority`
- `throughput_limit`
- `cost_per_email`
- `reputation`
- `tags`
- `notes`
- `config.transport`
- credenciales API o SMTP según el provider

## Reglas prácticas

- API-backed providers pueden usar `transport: api` o `transport: smtp` si el schema lo permite.
- `smtp_only` acepta únicamente credenciales SMTP.
- El UI valida el JSON antes de persistirlo.

