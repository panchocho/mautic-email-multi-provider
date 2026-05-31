# Queues

## Colas operativas

- `smart_mailer.routing`
- `smart_mailer.warmup`
- `smart_mailer.logs`
- `smart_mailer.dead_letter`

## Mensajes principales

- `RouteEmailCommand`
- `IngestDeliveryEventCommand`
- `RecomputeHealthCommand`
- `ApplyAutoActionsCommand`
- `ProcessRetryQueueCommand`

## Operación

- `maintenance:daily` limpia y procesa la cola persistida.
- `retry:process` reintenta mensajes vencidos.
- Los logs alimentan health y métricas de deliverability.

