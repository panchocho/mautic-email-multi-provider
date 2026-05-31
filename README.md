# SmartMailerRouterBundle (Mautic 7.x)

SmartMailerRouter es un plugin enterprise-grade para Mautic 7.x que enruta email entre múltiples providers SMTP/API con reglas inteligentes, failover, warmup y optimización de deliverability.

## Stack

- PHP 8.3+
- Symfony 7 components
- Doctrine ORM + Doctrine Migrations
- Symfony Mailer (sin SwiftMailer)
- Symfony Messenger (Redis/Doctrine transport)
- MariaDB/MySQL

## Providers soportados

- Amazon SES
- SendGrid
- Mailgun
- Postal
- PowerMTA
- SMTP genérico
- SMTP-only
- Brevo
- SparkPost
- Resend

Cada provider puede declararse con `transport: api` o `transport: smtp` según el esquema soportado. Los providers API-backed aceptan fallback SMTP cuando sus credenciales SMTP están presentes; los providers SMTP-only usan únicamente credenciales SMTP.

## Modos de routing implementados

1. `round_robin`
2. `weighted_round_robin`
3. `failover`
4. `sticky_campaign`
5. `domain_routing`
6. `mx_routing`
7. `randomized`
8. `percentage_split`
9. `tag_based`
10. `priority_based`
11. `cost_optimized`
12. `engagement_based`
13. `geo_routing`
14. `multi_domain_routing`

## Arquitectura (resumen)

- `Domain`: entidades Doctrine, value objects, contratos de estrategias/adapters.
- `Application`: motor de routing por modo, factory de estrategias, guardas de provider, health y auto-actions.
- `Infrastructure`: adapters de provider, handlers de Messenger, cache in-memory (pluggable a Redis), repositorios Doctrine.
- `UI`: controladores administrativos para providers/profiles/rules/health/warmup/logs.

## Mensajería

Mensajes principales:

- `RouteEmailCommand`
- `IngestDeliveryEventCommand`
- `RecomputeHealthCommand`
- `ApplyAutoActionsCommand`

La lógica de routing/deliverability pesada corre asíncronamente en workers.

Evento de entrada recomendado:

- `SmartMailerRouterBundle\Application\Event\EmailRoutingRequestedEvent` (capturado por `EmailRoutingSubscriber` y enviado a Messenger)

## Configuración base

```yaml
smart_mailer_router:
  default_profile: default
  default_strategy: round_robin
  queues:
    routing: smart_mailer.routing
    warmup: smart_mailer.warmup
    logs: smart_mailer.logs
    dead_letter: smart_mailer.dead_letter
  health:
    min_score: 60
    bounce_threshold: 0.03
    complaint_threshold: 0.001
    defer_threshold: 0.06
  warmup:
    enabled: true
    default_ramp: [100, 500, 1000, 2500, 5000]
```

## Instalación

1. Copiar `plugins/SmartMailerRouterBundle` en tu instancia Mautic.
2. Instalar dependencias del plugin.
3. Ejecutar migraciones.
4. Configurar transportes Messenger.

Detalles en `docs/installation.md`.

## Wiki operativa

La guía por secciones vive en `docs/wiki/`:

- `overview.md`
- `providers.md`
- `routing.md`
- `settings.md`
- `health-and-warmup.md`
- `retry-and-logs.md`
- `installation.md`
