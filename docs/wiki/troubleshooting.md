# Troubleshooting

## Providers no envían

- Verificá `provider_type`.
- Verificá `transport` en `config_json`.
- Verificá sender identity.
- Verificá que el provider esté activo y sin quarantine.
- Verificá si el schema requiere API key o SMTP credentials.
- Revisá si el provider quedó fuera por `health_score` bajo.

## La pantalla de settings parece vacía

- En instalaciones limpias ahora se muestran defaults.
- Si hay problemas de DB, revisá que la tabla `smr_setting` exista.
- Si la UI no refresca, verificá que el guardado haya creado filas en `smr_setting`.

## Routing no elige el provider esperado

- Revisá profile activo.
- Revisá rules/bindings.
- Revisá health score y quarantine.
- Revisá metadata del mensaje.
- Confirmá que el `transport` del provider sea compatible con el schema.

## Reintentos no avanzan

- Verificá `retry.max_retries`.
- Verificá `retry.base_delay_ms` y `retry.multiplier`.
- Revisá la cola `smart_mailer.dead_letter`.
- Revisá si el mensaje terminó como error terminal y no debe reintentarse.

## Warmup no progresa

- Revisá que el schedule esté `active`.
- Revisá `current_day` y `sent_today`.
- Revisá si la salud del provider bloqueó el avance.

## Síntomas de configuración incorrecta

- `config_json` vacío en un provider nuevo: usar defaults del tipo de provider.
- `config_json` vacío en la UI: ahora la pantalla precarga un ejemplo por defecto según el tipo de provider.
- `api_key` presente en `smtp_only`: eso es inválido y debe corregirse.
- `host` o `username` faltantes: faltan credenciales SMTP.
- `sender_email` inválido: el alta debe ser rechazada.
