# Queues y Workers

SmartMailerRouter usa Symfony Messenger para mover lógica pesada fuera del camino síncrono de envío.

## Colas

- `smart_mailer.routing`: selección de ruta y envío inicial.
- `smart_mailer.warmup`: tareas de warmup (provider/domain/ip pool).
- `smart_mailer.logs`: ingestión y agregación de eventos de deliverability.
- `smart_mailer.dead_letter`: mensajes agotados tras retries.
- `smart_mailer.retry`: cola interna persistida para reintentos programados.

## Mensajes

- `SmartMailerRouterBundle\Infrastructure\Messenger\Message\RouteEmailCommand`
- `SmartMailerRouterBundle\Infrastructure\Messenger\Message\IngestDeliveryEventCommand`
- `SmartMailerRouterBundle\Infrastructure\Messenger\Message\RecomputeHealthCommand`
- `SmartMailerRouterBundle\Infrastructure\Messenger\Message\ApplyAutoActionsCommand`
- `SmartMailerRouterBundle\Infrastructure\Messenger\Message\ProcessRetryQueueCommand`

## Ejemplo (Redis transport)

```yaml
framework:
  messenger:
    transports:
      smr_routing: '%env(MESSENGER_TRANSPORT_DSN)%'
      smr_warmup: '%env(MESSENGER_TRANSPORT_DSN)%'
      smr_logs: '%env(MESSENGER_TRANSPORT_DSN)%'
      smr_dead_letter: '%env(MESSENGER_TRANSPORT_DSN)%'
    routing:
      'SmartMailerRouterBundle\Infrastructure\Messenger\Message\RouteEmailCommand': smr_routing
      'SmartMailerRouterBundle\Infrastructure\Messenger\Message\IngestDeliveryEventCommand': smr_logs
      'SmartMailerRouterBundle\Infrastructure\Messenger\Message\RecomputeHealthCommand': smr_logs
      'SmartMailerRouterBundle\Infrastructure\Messenger\Message\ApplyAutoActionsCommand': smr_logs
      'SmartMailerRouterBundle\Infrastructure\Messenger\Message\ProcessRetryQueueCommand': smr_logs
```

## CLI helpers

- `smart-mailer:maintenance:sweep` purga logs y normaliza colas operativas.
- `smart-mailer:retry:process` procesa retries vencidos y re-dispatcha el comando guardado.
- `smart-mailer:maintenance:daily` ejecuta `sweep` y luego procesa retries vencidos en un solo paso.

### Cron recomendado

```cron
0 2 * * * /usr/bin/php /var/www/mautic/bin/console smart-mailer:maintenance:daily --env=prod --no-interaction
```

Opciones útiles:

- `--retry-limit=250`
- `--skip-sweep`
- `--skip-retry`

## Admin operativo

- `Settings` expone la retención configurable del router:
  - `delivery_log_retention_days`
  - `delivery_archive_retention_days`
  - `health_history_retention_days`
  - `retry_retention_days`
  - `retry.max_retries`
  - `retry.base_delay_ms`
  - `retry.multiplier`
  - `retry.max_delay_ms`
- `Logs` muestra filtros por `provider`, `status`, `recipient`, `domain`, `profile` y `external_message_id`.
- `Logs` permite seleccionar filas de `delivery logs` y ejecutar:
  - `Archive selected`
  - `Purge selected`
- `Logs` permite seleccionar filas de `retry queue` y ejecutar:
  - `Retry selected now`
  - `Retry selected +15m`
  - `Discard selected`
- `Archive selected` mueve filas a `smr_delivery_log_archive` y las elimina de `smr_delivery_log`.
- `Purge selected` elimina filas de `smr_delivery_log` sin archivarlas.
- `Archive selected` también limpia `smr_delivery_log_archive` mediante `maintenance:sweep` según la retención configurada.
