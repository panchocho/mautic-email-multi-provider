# Retry and Logs

## Retry

- Los fallos temporales se reintentan.
- Los fallos terminales van a dead-letter.
- La cola conserva estado y próxima ejecución.
- El backoff es exponencial y configurable.
- Los reintentos tienen límite de cantidad y de tiempo.
- El estado se persiste para que el worker pueda retomarlo.

## Logs

- Delivery logs registran intentos y resultado final.
- Delivery archive guarda histórico operativo.
- Los eventos de deliverability alimentan métricas y health.

## Estados típicos

- `sent`
- `delivered`
- `bounced`
- `complaint`
- `deferred`
- `failed`
- `retrying`
- `dead_letter`

## Qué se registra

- `provider`
- `provider_type`
- `routing_profile`
- `recipient`
- `domain`
- `attempt_no`
- `latency_ms`
- `selection_reason`
- `http_status`
- `failure_reason`
- `metadata`

## Operación recomendada

- Revisar logs cuando un provider falla repetidamente.
- Archivar primero y purgar después.
- Usar la cola de retry para los fallos transitorios, no para errores de configuración.
