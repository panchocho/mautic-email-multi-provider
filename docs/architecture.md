# Arquitectura SmartMailerRouter

## Componentes

- `Domain/Entity`: modelo Doctrine para providers, reglas, perfiles, métricas, salud, warmup, logs y retry queue.
- `Domain/Routing/Strategy/Mode`: 14 estrategias de routing desacopladas por modo.
- `Application/Routing/ModeRoutingEngine`: orquesta selección de provider por modo + guardas.
- `Application/Provider/ProviderAdapterFactory`: resuelve adapter concreto por provider.
- `Infrastructure/ProviderAdapter`: adapters para SES, SendGrid, Mailgun, Postal, PowerMTA, SMTP genérico, SMTP-only, Brevo, SparkPost, Resend.
- `Infrastructure/Messenger/Handler`: pipeline asíncrono de routing + ingest + health + auto-actions.

## Flujo de envío

1. `RouteEmailCommand` llega al bus de Messenger.
2. `RouteEmailHandler` construye contexto y ejecuta `ModeRoutingEngine`.
3. Se selecciona provider primario y fallback ranking.
4. El adapter del provider envía (API o SMTP según `config_json` y el provider).
5. Se despacha `IngestDeliveryEventCommand`.
6. Pipeline de salud: `RecomputeHealthCommand` + `ApplyAutoActionsCommand`.

## Principios de diseño

- Core síncrono liviano; trabajo intensivo en workers.
- Abstracción por contratos (`ModeRoutingStrategyInterface`, `ProviderAdapterInterface`).
- Extensibilidad por DI tags (`smart_mailer_router.mode_strategy`, `smart_mailer_router.provider_adapter`).
- Cacheable routing decisions y capacidad de escalar horizontalmente.
