# Installation

## Instalación limpia

1. Copiar `plugins/SmartMailerRouterBundle` a la instalación de Mautic.
2. Ejecutar el proceso de instalación o actualización de Mautic.
3. Verificar que `smr_*` se creen en base de datos.
4. Abrir el panel del plugin en el admin.

## Validación inicial

- Crear un provider SMTP o API.
- Crear un profile.
- Crear bindings o rules.
- Enviar un email de prueba.

## Checklist de producción

- `APP_ENV=prod`
- Messenger workers corriendo
- Transporte de queue configurado
- `smr_setting` con defaults persistidos
- Retención revisada
- Logs y dead-letter monitoreados

## Primer arranque recomendado

1. Crear al menos un provider de producción.
2. Crear un provider de fallback SMTP.
3. Definir un profile `default`.
4. Configurar bindings por dominio si aplica.
5. Probar un envío real.
