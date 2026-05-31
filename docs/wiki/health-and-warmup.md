# Health and Warmup

## Health

Health score y reputation determinan elegibilidad y acciones automáticas.

- Quarantine automática cuando el score cae por debajo del umbral.
- Rehabilitación cuando se recupera la salud.
- Historial persistido para auditoría.
- El health pipeline impacta directamente el routing.
- Un provider unhealthy puede ser excluido antes del ranking final.

## Señales que alimentan health

- Bounces.
- Complaints.
- Deferrals.
- Delivery failures.
- Latencia.
- Engagement cuando está disponible.

## Warmup

Warmup incrementa volumen de envío por provider/scope.

- Schedule por provider o ámbito.
- Límite diario y horario.
- Pausa o degradación si la salud cae.
- El warmup no es solo UI: define límites reales de volumen.
- Cada scope puede tener su propio estado persistido.
- Si la salud cae por debajo del mínimo, el ritmo de warmup se reduce o se pausa.
- Si la salud se recupera, el schedule puede avanzar al próximo escalón.

## Qué revisar

- `smr_warmup_schedule`
- `smr_warmup_state`
- `smr_provider_health_history`
- `smr_provider_metric_bucket`
