# Providers

Cada provider define credenciales, límites operativos y configuración específica en `config_json`.

## Tipos soportados

- `brevo`
- `resend`
- `amazon_ses`
- `sendgrid`
- `mailgun`
- `postal`
- `powermta`
- `smtp_generic`
- `smtp_only`
- `sparkpost`

## Qué guarda la entidad

- `name`: nombre visible en la UI.
- `code`: identificador interno único.
- `provider_type`: motor de envío.
- `enabled`: habilitación administrativa.
- `weight`: participación en modos ponderados.
- `priority`: preferencia en failover o priority routing.
- `throughput_limit`: techo operativo.
- `cost_per_email`: costo estimado para routing por costo.
- `reputation` y `health_score`: señales de elegibilidad.
- `tags`: etiquetas para matching.
- `notes`: documentación interna.
- `config_json`: credenciales y opciones del transport.

## Configuración

La UI carga defaults según el `provider_type`. Si `config_json` está vacío, se inserta un ejemplo por defecto para ese tipo antes de guardar.

- Providers API: `transport: api`, `api_key`, `base_url`, `sender_email`, `sender_name`.
- Providers SMTP: `transport: smtp`, `host`, `port`, `encryption`, `username`, `password`, `sender_email`, `sender_name`.
- `smtp_only` no usa API key.
- Providers híbridos aceptan `transport: api` o `transport: smtp` según su schema.

## Reglas de validación

- `sender_email` es obligatorio y debe ser válido.
- Para providers SMTP, `host`, `port`, `username` y `password` son obligatorios.
- Para providers API, `api_key` o credenciales API equivalentes son obligatorias.
- Si el schema admite `transport: smtp`, el adapter enviará por SMTP.
- Si el schema admite `transport: api`, el adapter enviará por API.

## Ejemplos rápidos

### SendGrid API

```json
{
  "transport": "api",
  "api_key": "SG.REEMPLAZAR",
  "base_url": "https://api.sendgrid.com",
  "sender_email": "no-reply@example.com",
  "sender_name": "My Brand"
}
```

### SendGrid SMTP

```json
{
  "transport": "smtp",
  "host": "smtp.sendgrid.net",
  "port": 587,
  "encryption": "tls",
  "username": "apikey",
  "password": "SG.SMTP_PASSWORD",
  "sender_email": "no-reply@example.com",
  "sender_name": "My Brand"
}
```

### SMTP-only

```json
{
  "transport": "smtp",
  "host": "smtp.provider.com",
  "port": 587,
  "encryption": "tls",
  "username": "user",
  "password": "secret",
  "sender_email": "no-reply@example.com",
  "sender_name": "My Brand"
}
```
