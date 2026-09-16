<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin;

use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\ProviderType;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Config\JsonExampleRegistry;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\ProviderConfig\ProviderConfigSchemaRegistry;
use Throwable;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProvidersAdminController extends AbstractAdminController
{
    private const CREATE_TOKEN_ID = 'smart_mailer_provider_create';

    #[Route(path: '/admin/smart-mailer/providers', name: 'smart_mailer_admin_providers', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $connection = $this->db();
        $notice = $this->resolveNotice((string) $request->query->get('status', ''));

        try {
            /** @var list<array<string, mixed>> $providers */
            $providers = $connection->fetchAllAssociative(
                'SELECT id, name, code, provider_type, enabled, weight, priority, throughput_limit, cost_per_email, reputation, health_score, tags, notes, config
                 FROM smr_provider
                 ORDER BY enabled DESC, priority DESC, name ASC'
            );

            $editId = trim((string) $request->query->get('edit', ''));
            $editProvider = $editId !== ''
                ? $connection->fetchAssociative(
                    'SELECT id, name, code, provider_type, enabled, weight, priority, throughput_limit, cost_per_email, reputation, health_score, tags, notes, config
                     FROM smr_provider WHERE id = :id',
                    ['id' => $editId]
                ) ?: null
                : null;
        } catch (Throwable $exception) {
            $providers = [];
            $editProvider = null;
            $notice = sprintf('No se pudieron cargar los providers. %s', $this->escape($exception->getMessage()));
        }

        $content = $this->renderProvidersContent($providers, $editProvider);

        return $this->renderAdminPage($request, 'providers', 'Providers', $content, $notice);
    }

    #[Route(path: '/admin/smart-mailer/providers/create', name: 'smart_mailer_admin_provider_create', methods: ['POST'])]
    public function create(Request $request): RedirectResponse
    {
        $connection = $this->db();

        if (!$this->isCsrfTokenValid(self::CREATE_TOKEN_ID, (string) $request->request->get('_token', ''))) {
            return $this->redirectWithStatus('csrf_error');
        }

        $payload = $this->buildPayload($request);
        if ($payload === null) {
            return $this->redirectWithStatus('validation_error');
        }

        try {
            $duplicate = $connection->fetchOne(
                'SELECT COUNT(*) FROM smr_provider WHERE code = :code',
                ['code' => $payload['code']]
            );
            if ((int) $duplicate > 0) {
                return $this->redirectWithStatus('duplicate_code');
            }

            $now = gmdate('Y-m-d H:i:s');
            $insertedRows = $connection->insert('smr_provider', [
                'id' => $this->generateUuidV4(),
                'name' => $payload['name'],
                'code' => $payload['code'],
                'provider_type' => $payload['provider_type'],
                'enabled' => $payload['enabled'],
                'weight' => $payload['weight'],
                'priority' => $payload['priority'],
                'throughput_limit' => $payload['throughput_limit'],
                'cost_per_email' => $payload['cost_per_email'],
                'reputation' => $payload['reputation'],
                'health_score' => $payload['health_score'],
                'tags' => $payload['tags_json'],
                'notes' => $payload['notes'],
                'quarantined_until' => null,
                'config' => $payload['config_json'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if ($insertedRows !== 1) {
                return $this->redirectWithStatus('db_error');
            }
        } catch (Throwable) {
            return $this->redirectWithStatus('db_error');
        }

        return $this->redirectWithStatus('created');
    }

    #[Route(path: '/admin/smart-mailer/providers/{id}/update', name: 'smart_mailer_admin_provider_update', methods: ['POST'])]
    public function update(Request $request, string $id): RedirectResponse
    {
        $connection = $this->db();

        if (!$this->isCsrfTokenValid('smart_mailer_provider_update_'.$id, (string) $request->request->get('_token', ''))) {
            return $this->redirectWithStatus('csrf_error');
        }

        try {
            $existing = $connection->fetchAssociative('SELECT id, code, config FROM smr_provider WHERE id = :id', ['id' => $id]);
        } catch (Throwable) {
            return $this->redirectWithStatus('db_error');
        }
        if (!is_array($existing)) {
            return $this->redirectWithStatus('provider_not_found');
        }

        $payload = $this->buildPayload($request, (string) ($existing['config'] ?? '{}'));
        if ($payload === null) {
            return $this->redirectWithStatus('validation_error', $id);
        }

        try {
            if ($payload['code'] !== (string) $existing['code']) {
                $duplicate = $connection->fetchOne(
                    'SELECT COUNT(*) FROM smr_provider WHERE code = :code AND id <> :id',
                    ['code' => $payload['code'], 'id' => $id]
                );
                if ((int) $duplicate > 0) {
                    return $this->redirectWithStatus('duplicate_code', $id);
                }
            }

            $connection->update('smr_provider', [
                'name' => $payload['name'],
                'code' => $payload['code'],
                'provider_type' => $payload['provider_type'],
                'enabled' => $payload['enabled'],
                'weight' => $payload['weight'],
                'priority' => $payload['priority'],
                'throughput_limit' => $payload['throughput_limit'],
                'cost_per_email' => $payload['cost_per_email'],
                'reputation' => $payload['reputation'],
                'health_score' => $payload['health_score'],
                'tags' => $payload['tags_json'],
                'notes' => $payload['notes'],
                'config' => $payload['config_json'],
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ], ['id' => $id]);
        } catch (Throwable) {
            return $this->redirectWithStatus('db_error', $id);
        }

        return $this->redirectWithStatus('updated');
    }

    #[Route(path: '/admin/smart-mailer/providers/{id}/delete', name: 'smart_mailer_admin_provider_delete', methods: ['POST'])]
    public function delete(Request $request, string $id): RedirectResponse
    {
        $connection = $this->db();

        if (!$this->isCsrfTokenValid('smart_mailer_provider_delete_'.$id, (string) $request->request->get('_token', ''))) {
            return $this->redirectWithStatus('csrf_error');
        }

        try {
            $deletedRows = $connection->delete('smr_provider', ['id' => $id]);
        } catch (Throwable) {
            return $this->redirectWithStatus('db_error');
        }
        if ($deletedRows === 0) {
            return $this->redirectWithStatus('provider_not_found');
        }

        return $this->redirectWithStatus('deleted');
    }

    private function redirectWithStatus(string $status, ?string $editId = null): RedirectResponse
    {
        $params = ['status' => $status];
        if (is_string($editId) && $editId !== '') {
            $params['edit'] = $editId;
        }

        return $this->redirect($this->generateUrl('smart_mailer_admin_providers', $params));
    }

    private function isValidCode(string $code): bool
    {
        return preg_match('/^[a-z0-9_-]{2,64}$/', $code) === 1;
    }

    private function resolveNotice(string $status): ?string
    {
        return match ($status) {
            'created' => 'Proveedor creado correctamente.',
            'updated' => 'Proveedor actualizado correctamente.',
            'deleted' => 'Proveedor eliminado correctamente.',
            'duplicate_code' => 'El código ya existe. Usá un código único.',
            'validation_error' => 'Datos inválidos. Verificá nombre, código, tipo y JSON.',
            'provider_not_found' => 'No se encontró el proveedor solicitado.',
            'csrf_error' => 'Token de seguridad inválido. Recargá la página e intentá de nuevo.',
            'db_error' => 'Error de base de datos. Revisá migraciones/conexión y volvé a intentar.',
            default => null,
        };
    }

    /**
     * @param list<array<string, mixed>> $providers
     * @param array<string, mixed>|null $editProvider
     */
    private function renderProvidersContent(array $providers, ?array $editProvider): string
    {
        $registry = $this->jsonExampleRegistry();
        $fieldHelp = '<div class="alert alert-secondary mb-md"><strong>Configuración guiada.</strong> Elegí proveedor y transporte, completá los campos correspondientes y SmartMailer genera la configuración interna. Las claves existentes nunca se muestran: dejá una clave vacía para conservarla.</div>';
        $examplesHelp = sprintf(
            '<details class="mb-md"><summary><strong>JSON examples by provider (copy/paste)</strong></summary><div class="mt-sm"><p class="text-muted mb-sm">La config guardada acá se usa en el envío real cuando el adapter soporta ese provider. Cada provider puede funcionar con <code>transport: api</code> o <code>transport: smtp</code> según su esquema. Si falta una clave requerida, el alta queda rechazada.</p><p class="mb-xs"><strong>Brevo API</strong></p><pre style="white-space:pre-wrap;">%s</pre><p class="mb-xs"><strong>Brevo SMTP</strong></p><pre style="white-space:pre-wrap;">%s</pre><p class="mb-xs"><strong>SendGrid API</strong></p><pre style="white-space:pre-wrap;">%s</pre><p class="mb-xs"><strong>SendGrid SMTP</strong></p><pre style="white-space:pre-wrap;">%s</pre><p class="mb-xs"><strong>Amazon SES API</strong></p><pre style="white-space:pre-wrap;">%s</pre><p class="mb-xs"><strong>Amazon SES SMTP</strong></p><pre style="white-space:pre-wrap;">%s</pre><p class="mb-xs"><strong>SMTP only</strong></p><pre style="white-space:pre-wrap;">%s</pre></div></details>',
            $this->escape($this->prettyJsonFromRegistry($registry, ProviderType::BREVO->value)),
            $this->escape(json_encode([
                'transport' => 'smtp',
                'host' => 'smtp.brevo.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'SMTP_LOGIN',
                'password' => 'SMTP_PASSWORD',
                'sender_email' => 'no-reply@tu-dominio.com',
                'sender_name' => 'Tu Marca',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}'),
            $this->escape($this->prettyJsonFromRegistry($registry, ProviderType::SENDGRID->value)),
            $this->escape(json_encode([
                'transport' => 'smtp',
                'host' => 'smtp.sendgrid.net',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'apikey',
                'password' => 'SG.SMTP_PASSWORD',
                'sender_email' => 'no-reply@tu-dominio.com',
                'sender_name' => 'Tu Marca',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}'),
            $this->escape($this->prettyJsonFromRegistry($registry, ProviderType::AMAZON_SES->value)),
            $this->escape(json_encode([
                'transport' => 'smtp',
                'host' => 'email-smtp.us-east-1.amazonaws.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'SMTP_USERNAME',
                'password' => 'SMTP_PASSWORD',
                'sender_email' => 'no-reply@tu-dominio.com',
                'sender_name' => 'Tu Marca',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}'),
            $this->escape($this->prettyJsonFromRegistry($registry, ProviderType::SMTP_ONLY->value))
        );

        $createForm = $this->buildProviderForm(
            action: $this->generateUrl('smart_mailer_admin_provider_create'),
            tokenId: self::CREATE_TOKEN_ID,
            buttonLabel: 'Crear proveedor',
            provider: null
        );

        $tableRows = '';
        foreach ($providers as $provider) {
            $id = (string) ($provider['id'] ?? '');
            $editUrl = $this->generateUrl('smart_mailer_admin_providers', ['edit' => $id]);
            $deleteAction = $this->generateUrl('smart_mailer_admin_provider_delete', ['id' => $id]);
            $tableRows .= sprintf(
                '<tr><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%d</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%d</td><td style="padding:8px;border-top:1px solid #e5e7eb;"><a href="%s" data-toggle="ajax">Editar</a> <form method="post" action="%s" style="display:inline;" onsubmit="return confirm(\'Eliminar proveedor?\');"><input type="hidden" name="_token" value="%s"><button type="submit" style="margin-left:6px;border:0;background:#dc2626;color:#fff;padding:5px 8px;border-radius:6px;cursor:pointer;">Eliminar</button></form></td></tr>',
                $this->escape((string) ($provider['name'] ?? '')),
                $this->escape((string) ($provider['code'] ?? '')),
                $this->escape((string) ($provider['provider_type'] ?? '')),
                ((int) ($provider['enabled'] ?? 0)) === 1 ? 'Sí' : 'No',
                (int) ($provider['weight'] ?? 0),
                (int) ($provider['priority'] ?? 0),
                $this->escape($editUrl),
                $this->escape($deleteAction),
                $this->escape($this->csrfToken('smart_mailer_provider_delete_'.$id))
            );
        }

        if ($tableRows === '') {
            $tableRows = '<tr><td colspan="7" style="padding:10px;border-top:1px solid #e5e7eb;color:#6b7280;">No hay proveedores configurados.</td></tr>';
        }

        $editSection = '';
        if (is_array($editProvider)) {
            $editId = (string) ($editProvider['id'] ?? '');
            $editCode = (string) ($editProvider['code'] ?? '');
            $editSection = sprintf(
                '<div style="margin-top:18px;"><h3 style="margin:0 0 10px;">Editar proveedor: %s</h3>%s</div>',
                $this->escape($editCode),
                $this->buildProviderForm(
                    action: $this->generateUrl('smart_mailer_admin_provider_update', ['id' => $editId]),
                    tokenId: 'smart_mailer_provider_update_'.$editId,
                    buttonLabel: 'Guardar cambios',
                    provider: $editProvider
                )
            );
        }

        return sprintf(
            '<h2 style="margin:0 0 12px;">Providers</h2><p style="margin:0 0 16px;color:#4b5563;">Alta, edición y borrado de proveedores para ruteo (Brevo, Resend, SES, SendGrid, Mailgun, etc.).</p>%s%s<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">%s<div><h3 style="margin:0 0 8px;">Proveedores existentes</h3><div style="overflow:auto;"><table style="width:100%%;border-collapse:collapse;font-size:13px;"><thead><tr style="text-align:left;background:#f9fafb;"><th style="padding:8px;">Nombre</th><th style="padding:8px;">Código</th><th style="padding:8px;">Tipo</th><th style="padding:8px;">Activo</th><th style="padding:8px;">Weight</th><th style="padding:8px;">Priority</th><th style="padding:8px;">Acciones</th></tr></thead><tbody>%s</tbody></table></div></div></div>%s',
            $fieldHelp,
            $examplesHelp,
            $createForm,
            $tableRows,
            $editSection
        );
    }

    /**
     * @param array<string, mixed>|null $provider
     */
    private function buildProviderForm(string $action, string $tokenId, string $buttonLabel, ?array $provider): string
    {
        $name = (string) ($provider['name'] ?? '');
        $code = (string) ($provider['code'] ?? '');
        $selectedType = (string) ($provider['provider_type'] ?? ProviderType::BREVO->value);
        $enabledChecked = ((int) ($provider['enabled'] ?? 1)) === 1;
        $weight = (string) ((int) ($provider['weight'] ?? 100));
        $priority = (string) ((int) ($provider['priority'] ?? 100));
        $throughputLimit = (string) ((int) ($provider['throughput_limit'] ?? 1000));
        $cost = number_format((float) ($provider['cost_per_email'] ?? 0.0), 6, '.', '');
        $reputation = number_format((float) ($provider['reputation'] ?? 100.0), 2, '.', '');
        $healthScore = (string) ((int) ($provider['health_score'] ?? 100));
        $tags = $this->decodeTags((string) ($provider['tags'] ?? '[]'));
        $notes = (string) ($provider['notes'] ?? '');
        $registry = $this->jsonExampleRegistry();
        $configJson = $this->prettyJson((string) ($provider['config'] ?? '{}'));
        $currentConfig = json_decode((string) ($provider['config'] ?? '{}'), true);
        $currentTransport = is_array($currentConfig) && ($currentConfig['transport'] ?? '') === 'smtp' ? 'smtp' : 'api';
        if (!is_array($provider) || !array_key_exists('config', $provider) || trim((string) $provider['config']) === '' || trim((string) $provider['config']) === '{}') {
            $configJson = $registry !== null
                ? $this->prettyJsonFromRegistry($registry, $selectedType)
                : (json_encode($this->defaultConfigTemplate($selectedType), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
        }

        $typeOptions = '';
        foreach (ProviderType::cases() as $providerType) {
            $selected = $selectedType === $providerType->value ? ' selected' : '';
            $label = strtoupper(str_replace('_', ' ', $providerType->value));
            $defaultConfig = $registry !== null
                ? $this->prettyJsonFromRegistry($registry, $providerType->value)
                : (json_encode($this->defaultConfigTemplate($providerType->value), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
            $typeOptions .= sprintf(
                '<option value="%s"%s data-default-config="%s">%s</option>',
                $this->escape($providerType->value),
                $selected,
                $this->escape($defaultConfig),
                $this->escape($label)
            );
        }

        return sprintf(
            '<div><h3 style="margin:0 0 8px;">%s</h3><form method="post" action="%s" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;"><input type="hidden" name="_token" value="%s"><label style="display:flex;flex-direction:column;gap:4px;">Nombre<input name="name" value="%s" required style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;">Código<input name="code" value="%s" required pattern="[a-z0-9_-]{2,64}" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;">Tipo<select name="provider_type" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;">%s</select></label><label style="display:flex;align-items:center;gap:8px;margin-top:24px;"><input type="checkbox" name="enabled" value="1"%s> Habilitado</label><label style="display:flex;flex-direction:column;gap:4px;">Weight<input type="number" min="1" name="weight" value="%s" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;">Priority<input type="number" name="priority" value="%s" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;">Throughput limit<input type="number" min="1" name="throughput_limit" value="%s" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;">Cost per email<input type="number" min="0" step="0.000001" name="cost_per_email" value="%s" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;">Reputation<input type="number" min="0" max="100" step="0.01" name="reputation" value="%s" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;">Health score<input type="number" min="0" max="100" name="health_score" value="%s" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;grid-column:1 / -1;">Tags (coma separada)<input name="tags" value="%s" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;grid-column:1 / -1;">Notes<textarea name="notes" rows="3" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;">%s</textarea></label><fieldset style="grid-column:1 / -1;border:1px solid #d1d5db;border-radius:6px;padding:12px;"><legend style="font-size:14px;padding:0 5px;">Conexión de envío</legend><p style="margin:0 0 10px;color:#6b7280;font-size:12px;">Completá sólo API, SMTP o SES según el tipo elegido. Las claves vacías se conservan al editar.</p><div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;"><label>Transporte<select name="transport" class="form-control"><option value="api"%s>API</option><option value="smtp"%s>SMTP</option></select></label><label>Remitente<input name="sender_email" type="email" class="form-control" placeholder="ventas-dominio.com"></label><label>Nombre remitente<input name="sender_name" class="form-control" placeholder="Tu marca"></label><label>API key<input name="api_key" type="password" autocomplete="new-password" class="form-control" placeholder="Nueva clave; vacío conserva la actual"></label><label>URL API<input name="base_url" type="url" class="form-control" placeholder="https://api.proveedor.com"></label><label>Dominio Mailgun<input name="mailgun_domain" class="form-control" placeholder="mg.tu-dominio.com"></label><label>SMTP host<input name="smtp_host" class="form-control" placeholder="smtp.proveedor.com"></label><label>SMTP puerto<input name="smtp_port" type="number" min="1" max="65535" value="587" class="form-control"></label><label>SMTP cifrado<select name="smtp_encryption" class="form-control"><option value="tls">TLS</option><option value="ssl">SSL</option><option value="">Sin cifrado</option></select></label><label>SMTP usuario<input name="smtp_username" class="form-control"></label><label>SMTP clave<input name="smtp_password" type="password" autocomplete="new-password" class="form-control" placeholder="Nueva clave; vacío conserva la actual"></label><label>SES Access Key<input name="access_key_id" class="form-control"></label><label>SES Secret Key<input name="secret_access_key" type="password" autocomplete="new-password" class="form-control" placeholder="Nueva clave; vacío conserva la actual"></label><label>Región SES<input name="region" class="form-control" placeholder="us-east-1"></label></div></fieldset><div style="grid-column:1 / -1;"><button type="submit" style="border:0;background:#111827;color:#fff;padding:8px 12px;border-radius:6px;cursor:pointer;">%s</button></div></form></div>',
            is_array($provider) ? 'Editar proveedor' : 'Nuevo proveedor',
            $this->escape($action),
            $this->escape($this->csrfToken($tokenId)),
            $this->escape($name),
            $this->escape($code),
            $typeOptions,
            $enabledChecked ? ' checked' : '',
            $this->escape($weight),
            $this->escape($priority),
            $this->escape($throughputLimit),
            $this->escape($cost),
            $this->escape($reputation),
            $this->escape($healthScore),
            $this->escape($tags),
            $this->escape($notes),
            $currentTransport === 'api' ? ' selected' : '',
            $currentTransport === 'smtp' ? ' selected' : '',
            $this->escape($buttonLabel)
        );
    }

    /**
     * @return array<string, int|string>|null
     */
    private function buildPayload(Request $request, string $existingConfigJson = '{}'): ?array
    {
        $name = trim((string) $request->request->get('name', ''));
        $code = strtolower(trim((string) $request->request->get('code', '')));
        $providerType = ProviderType::tryFrom((string) $request->request->get('provider_type', ''));
        if ($name === '' || $providerType === null || !$this->isValidCode($code)) {
            return null;
        }

        $tagsRaw = (string) $request->request->get('tags', '');
        $tags = array_values(array_filter(array_map(
            static fn (string $tag): string => trim($tag),
            preg_split('/[,\n]+/', $tagsRaw) ?: []
        ), static fn (string $tag): bool => $tag !== ''));

        $notes = trim((string) $request->request->get('notes', ''));
        $decodedConfig = $this->providerConfigFromForm($request, $providerType->value, $existingConfigJson);
        $validatedConfig = $this->validateConfigForProviderType($providerType->value, $decodedConfig);
        if ($validatedConfig === null) {
            return null;
        }

        $tagsJson = json_encode($tags, JSON_UNESCAPED_SLASHES);
        $configJson = json_encode($validatedConfig, JSON_UNESCAPED_SLASHES);

        return [
            'name' => $name,
            'code' => $code,
            'provider_type' => $providerType->value,
            'enabled' => $request->request->getBoolean('enabled', false) ? 1 : 0,
            'weight' => $this->toInt($request, 'weight', 100, 1),
            'priority' => $this->toInt($request, 'priority', 100, 0),
            'throughput_limit' => $this->toInt($request, 'throughput_limit', 1000, 1),
            'cost_per_email' => number_format($this->toFloat($request, 'cost_per_email', 0.0, 0.0), 6, '.', ''),
            'reputation' => number_format($this->toFloat($request, 'reputation', 100.0, 0.0, 100.0), 2, '.', ''),
            'health_score' => $this->toInt($request, 'health_score', 100, 0, 100),
            'tags_json' => is_string($tagsJson) ? $tagsJson : '[]',
            'notes' => $notes === '' ? null : $notes,
            'config_json' => is_string($configJson) ? $configJson : '{}',
        ];
    }

    /**  array<string, mixed> */
    private function providerConfigFromForm(Request $request, string $providerType, string $existingConfigJson): array
    {
        try {
            $existing = json_decode($existingConfigJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $existing = [];
        }
        if (!is_array($existing)) {
            $existing = [];
        }
        $value = static fn (string $key): string => trim((string) $request->request->get($key, ''));
        $keepSecret = static function (string $key) use ($value, $existing): string {
            $submitted = $value($key);
            return $submitted !== '' ? $submitted : (string) ($existing[$key] ?? '');
        };
        $keepValue = $keepSecret;
        $config = ['transport' => $value('transport') === 'smtp' ? 'smtp' : 'api', 'sender_email' => $keepValue('sender_email'), 'sender_name' => $keepValue('sender_name')];
        if ($config['transport'] === 'smtp') {
            return $config + ['host' => $keepValue('smtp_host'), 'port' => (int) ($value('smtp_port') ?: 587), 'encryption' => $keepValue('smtp_encryption') ?: 'tls', 'username' => $keepValue('smtp_username'), 'password' => $keepSecret('smtp_password')];
        }
        if ($providerType === ProviderType::AMAZON_SES->value) {
            return $config + ['access_key_id' => $keepValue('access_key_id'), 'secret_access_key' => $keepSecret('secret_access_key'), 'region' => $keepValue('region')];
        }
        $config += ['api_key' => $keepSecret('api_key'), 'base_url' => $keepValue('base_url')];
        if ($providerType === ProviderType::MAILGUN->value) {
            $config['domain'] = $keepValue('mailgun_domain');
        }
        return $config;
    }

    private function toInt(Request $request, string $key, int $default, int $min, ?int $max = null): int
    {
        $raw = $request->request->get($key);
        $value = filter_var($raw, FILTER_VALIDATE_INT);
        if ($value === false) {
            $value = $default;
        }

        if ($value < $min) {
            $value = $min;
        }

        if ($max !== null && $value > $max) {
            $value = $max;
        }

        return $value;
    }

    private function toFloat(Request $request, string $key, float $default, float $min, ?float $max = null): float
    {
        $raw = $request->request->get($key);
        $value = filter_var($raw, FILTER_VALIDATE_FLOAT);
        if ($value === false) {
            $value = $default;
        }

        if ($value < $min) {
            $value = $min;
        }

        if ($max !== null && $value > $max) {
            $value = $max;
        }

        return $value;
    }

    private function decodeTags(string $json): string
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return '';
        }

        if (!is_array($decoded)) {
            return '';
        }

        $tags = array_values(array_filter(array_map('strval', $decoded), static fn (string $tag): bool => trim($tag) !== ''));

        return implode(', ', $tags);
    }

    private function prettyJson(string $json): string
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return '{}';
        }

        if (!is_array($decoded)) {
            return '{}';
        }

        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>|null
     */
    private function validateConfigForProviderType(string $providerType, array $config): ?array
    {
        $registry = $this->providerConfigSchemaRegistry();
        if ($registry === null) {
            return null;
        }

        $result = $registry->validate($providerType, $config);
        if (!isset($result['valid']) || $result['valid'] !== true) {
            return null;
        }

        return isset($result['normalized']) && is_array($result['normalized']) ? $result['normalized'] : $config;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultConfigTemplate(string $providerType): array
    {
        $registry = $this->jsonExampleRegistry();
        if ($registry !== null) {
            return $registry->providerConfig($providerType);
        }

        return match (strtolower(trim($providerType))) {
            ProviderType::SMTP_ONLY->value,
            ProviderType::POWERMTA->value,
            ProviderType::SMTP_GENERIC->value => [
                'transport' => 'smtp',
                'host' => 'smtp.tu-proveedor.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'usuario',
                'password' => 'clave',
                'sender_email' => 'no-reply@tu-dominio.com',
                'sender_name' => 'Tu Marca',
            ],
            ProviderType::AMAZON_SES->value => [
                'transport' => 'api',
                'access_key_id' => 'AKIA...',
                'secret_access_key' => 'REEMPLAZAR',
                'region' => 'us-east-1',
                'sender_email' => 'no-reply@tu-dominio.com',
                'sender_name' => 'Tu Marca',
            ],
            default => [
                'transport' => 'api',
                'api_key' => 'REEMPLAZAR',
                'base_url' => 'https://api.tu-proveedor.com',
                'sender_email' => 'no-reply@tu-dominio.com',
                'sender_name' => 'Tu Marca',
            ],
        };
    }

    private function prettyJsonFromRegistry(?JsonExampleRegistry $registry, string $providerType): string
    {
        if (!$registry instanceof JsonExampleRegistry) {
            return $this->prettyJson(json_encode($this->defaultConfigTemplate($providerType), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
        }

        return $registry->exampleJson($registry->providerConfig($providerType));
    }

    private function providerConfigSchemaRegistry(): ?ProviderConfigSchemaRegistry
    {
        if (!$this->container->has(ProviderConfigSchemaRegistry::class)) {
            return null;
        }

        $service = $this->container->get(ProviderConfigSchemaRegistry::class);

        return $service instanceof ProviderConfigSchemaRegistry ? $service : null;
    }

    private function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

}
