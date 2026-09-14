# Overview

SmartMailerRouter agrega ruteo inteligente de correo sobre Mautic con soporte para múltiples providers, fallback, health scoring, warmup y observabilidad.

## Qué resuelve

- Selección dinámica de provider por profile, rules y bindings.
- Prioridad, peso, health y quarantine.
- Soporte para providers API y SMTP.
- Defaults por tipo de provider en `config_json` y autocompletado de ejemplos cuando el JSON está vacío.
- Registro de envíos, retries y eventos de deliverability.
- Persistencia de settings operativos con defaults para instalaciones limpias.

## Flujo general

1. Entra un `RouteEmailCommand`.
2. El router evalúa profile, rules, bindings y metadata.
3. Se elige un provider elegible.
4. El adapter realiza el envío por API o SMTP.
5. Se registran delivery logs, métricas y health signals.
6. Los eventos alimentan retry, warmup y auto-actions.

## Puntos de control

- `Providers`: credenciales, límites, salud y configuración del transport.
- `Profiles`: estrategia de routing y reglas de selección.
- `Bindings`: match directo por dominio.
- `Rules`: constraints y prioridades.
- `Health`: quarantine y auto-actions.
- `Warmup`: ramp-up de volumen por provider/scope.
- `Logs`: auditoría operativa y cola de retries.

## Cuándo usar esta wiki

- Si estás instalando el plugin por primera vez.
- Si necesitás configurar providers SMTP-only o API-backed.
- Si querés entender por qué un provider fue seleccionado o descartado.
- Si querés operar retención, retries y mantenimiento diario.
