# Architecture

## Capas

- `Domain`: entidades, value objects y contratos.
- `Application`: routing, health, warmup, retry y delivery orchestration.
- `Infrastructure`: adapters, persistence, Messenger handlers y schema bootstrap.
- `UI`: pantallas administrativas.

## Componentes clave

- `ProviderAdapterFactory`
- `ModeRoutingEngine`
- `ProfileRulesRoutingResolver`
- `ProviderGuard`
- `HealthScoreService`
- `AutoActionsService`
- `QueueMaintenanceService`
- `SmartMailerSettingsRepository`

## Design goals

- Mantener el camino de envío lo más liviano posible.
- Externalizar cálculos pesados a Messenger.
- Permitir providers API y SMTP con el mismo contrato de entrega.

