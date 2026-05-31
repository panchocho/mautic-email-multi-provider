# Settings

La sección `Settings` controla la retención y el comportamiento operativo del plugin.

## Valores

- `maintenance.delivery_log_retention_days`
- `maintenance.delivery_archive_retention_days`
- `maintenance.health_history_retention_days`
- `maintenance.retry_retention_days`
- `retry.max_retries`
- `retry.base_delay_ms`
- `retry.multiplier`
- `retry.max_delay_ms`

## Comportamiento

- Si no hay valores guardados, la UI carga defaults.
- El repositorio también expone defaults para instalaciones limpias.
- `SchemaInitializer` siembra settings por defecto en instalaciones nuevas.

## Cuándo tocar cada valor

- `delivery_log_retention_days`: cuántos días conservar el log operativo.
- `delivery_archive_retention_days`: cuánto tiempo conservar el histórico archivado.
- `health_history_retention_days`: retención del historial de health.
- `retry_retention_days`: cuánto tiempo mantener la cola de reintentos persistida.
- `retry.max_retries`: número máximo de reintentos por mensaje.
- `retry.base_delay_ms`: demora inicial entre reintentos.
- `retry.multiplier`: factor de backoff exponencial.
- `retry.max_delay_ms`: tope superior de espera entre reintentos.

## Defaults canónicos

```text
maintenance.delivery_log_retention_days = 90
maintenance.delivery_archive_retention_days = 180
maintenance.health_history_retention_days = 180
maintenance.retry_retention_days = 30
retry.max_retries = 8
retry.base_delay_ms = 2000
retry.multiplier = 2.0
retry.max_delay_ms = 300000
```

## Operación diaria

- Si querés limpiar estado, corré la tarea de maintenance diaria.
- Si cambiás retención, primero guardá en UI y luego revisá que la tabla `smr_setting` tenga filas.
- La UI y el repositorio usan defaults si la DB todavía no tiene valores guardados.
