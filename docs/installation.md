# Instalación

## Requisitos

- Mautic 7.x
- PHP 8.3+
- Symfony 7 components
- MariaDB/MySQL

## Pasos

1. Copiar el bundle en `plugins/SmartMailerRouterBundle`.
2. Instalar dependencias:

```bash
cd plugins/SmartMailerRouterBundle
composer install
```

3. Registrar el bundle (si tu instalación no lo autodetecta):

```php
<?php
return [
    SmartMailerRouterBundle\SmartMailerRouterBundle::class => ['all' => true],
];
```

4. Ejecutar migraciones del plugin.
5. Configurar Messenger (Redis o Doctrine transport).
6. Configurar providers/routing profiles en panel admin.
7. En `config_json`, elegir `transport: api` o `transport: smtp` según el provider. Si el provider soporta ambos modos, el schema acepta la configuración equivalente y la UI muestra ejemplos listos para copiar.
