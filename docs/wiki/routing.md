# Routing

El routing decide qué provider se usa para cada email.

## Prioridad de decisiones

1. Metadata del mensaje.
2. Rules activas del profile.
3. Bindings por dominio.
4. Strategy del profile.
5. Guard rails de health y quarantine.

## Orden práctico de evaluación

1. Identificar el profile activo.
2. Aplicar bindings por dominio si existen.
3. Evaluar rules activas del profile.
4. Calcular ranking por strategy.
5. Filtrar providers por salud, enabled y quarantine.
6. Intentar provider primario.
7. Aplicar fallback ranking si falla.

## Modos

- Round robin
- Weighted
- Failover
- Priority-based
- Percentage split
- Domain routing
- Multi-domain routing
- Geo routing
- Tag-based
- Engagement based
- Sticky campaign

## Configuración

- Los profiles se definen desde el admin.
- Las rules agregan constraints o pesos.
- Los bindings asignan dominios a providers.

## Qué mirar cuando el routing no coincide

- Si el profile tiene `enabled = 0`, no participa.
- Si un provider está `quarantined`, queda fuera del ranking.
- Si la regla exige un dominio o tag, el mensaje debe cumplirlo.
- Si el provider supera el `throughput_limit`, el guard lo puede descartar.
- Si hay varios providers válidos, la strategy define el orden final.

## Buenas prácticas

- Usar un profile por caso de negocio.
- Usar bindings para dominios sensibles o de alto volumen.
- Mantener providers de backup con prioridad menor pero activos.
- Revisar `health_score` y `reputation` periódicamente.
