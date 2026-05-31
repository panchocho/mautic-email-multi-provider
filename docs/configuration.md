# Configuración

Root key: `smart_mailer_router`

## Opciones clave

- `default_profile`
- `default_strategy` (`round_robin`, `weighted_round_robin`, `failover`, etc.)
- `providers.<code>.enabled`
- `providers.<code>.type`
- `providers.<code>.weight`
- `providers.<code>.priority`
- `providers.<code>.throughput_limit`
- `providers.<code>.domains`
- `providers.<code>.cost_per_email`
- `providers.<code>.reputation`
- `providers.<code>.config.transport` (`api` o `smtp`)
- `providers.<code>.config.api_key` / `access_key_id` / `secret_access_key`
- `providers.<code>.config.host` / `port` / `username` / `password`
- `health.min_score`
- `health.bounce_threshold`
- `health.complaint_threshold`
- `health.defer_threshold`
- `warmup.enabled`
- `warmup.default_ramp`
- `retry.max_retries`
- `retry.base_delay_ms`
- `retry.multiplier`
- `retry.max_delay_ms`
- `queues.routing|warmup|logs|dead_letter`

## Ejemplo

```yaml
smart_mailer_router:
  default_profile: marketing
  default_strategy: weighted_round_robin
  providers:
    ses:
      enabled: true
      type: amazon_ses
      weight: 100
      priority: 120
      throughput_limit: 5000
      domains: ['gmail.com', 'googlemail.com']
      cost_per_email: 0.00010
      reputation: 92.5
      tags: ['premium', 'transactional']
      config:
        transport: api
        access_key_id: 'AKIA...'
        secret_access_key: 'REEMPLAZAR'
        region: 'us-east-1'
    mailgun:
      enabled: true
      type: mailgun
      weight: 85
      priority: 100
      throughput_limit: 4000
      domains: ['yahoo.com']
      cost_per_email: 0.00012
      reputation: 90
      tags: ['bulk']
      config:
        transport: smtp
        host: 'smtp.mailgun.org'
        port: 587
        username: 'postmaster@mg.example.com'
        password: 'REEMPLAZAR'
  health:
    min_score: 60
    bounce_threshold: 0.03
    complaint_threshold: 0.001
    defer_threshold: 0.06
  warmup:
    enabled: true
    default_ramp: [100, 500, 1000, 2500, 5000]
  retry:
    max_retries: 8
    base_delay_ms: 2000
    multiplier: 2.0
    max_delay_ms: 300000
```

## Selección de profile y rules en runtime

El handler de routing resuelve profile/rules con este orden:

1. `metadata.routing_profile_id`
2. `metadata.routing_profile` (también acepta `profile` y `profile_name`)
3. `smart_mailer_router.default_profile`

Si hay rules activas para el profile, se filtran providers usando:

- `domain_pattern` (ej: `gmail.com`, `*.yahoo.com`)
- `constraints_json` con campos opcionales:
  - `tenant_id`
  - `campaign_type`
  - `region`
  - `message_type`
  - `priority_min` / `priority_max`
  - `recipient_domain`
  - `metadata` (objeto clave/valor para match exacto)
