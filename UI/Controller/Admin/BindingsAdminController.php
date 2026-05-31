<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin;

use Throwable;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BindingsAdminController extends AbstractAdminController
{
    private const CREATE_TOKEN_ID = 'smart_mailer_binding_create';

    #[Route(path: '/admin/smart-mailer/bindings', name: 'smart_mailer_admin_bindings', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $connection = $this->db();
        $notice = $this->resolveNotice((string) $request->query->get('status', ''));

        try {
            /** @var list<array<string, mixed>> $providers */
            $providers = $connection->fetchAllAssociative(
                'SELECT id, name, code, enabled
                 FROM smr_provider
                 ORDER BY enabled DESC, name ASC'
            );

            /** @var list<array<string, mixed>> $bindings */
            $bindings = $connection->fetchAllAssociative(
                'SELECT b.id, b.provider_id, b.domain, b.priority, b.active, b.created_at, p.name AS provider_name, p.code AS provider_code
                 FROM smr_provider_domain_binding b
                 LEFT JOIN smr_provider p ON p.id = b.provider_id
                 ORDER BY b.priority ASC, b.domain ASC'
            );

            $editIdRaw = $request->query->get('edit');
            $editId = filter_var($editIdRaw, FILTER_VALIDATE_INT);
            $editBinding = ($editId !== false)
                ? $connection->fetchAssociative(
                    'SELECT id, provider_id, domain, priority, active
                     FROM smr_provider_domain_binding
                     WHERE id = :id',
                    ['id' => $editId]
                ) ?: null
                : null;
        } catch (Throwable $exception) {
            $providers = [];
            $bindings = [];
            $editBinding = null;
            $notice = sprintf(
                'No se pudieron cargar los domain bindings. %s',
                $this->escape($exception->getMessage())
            );
        }

        $content = $this->renderContent($providers, $bindings, $editBinding);

        return $this->renderAdminPage($request, 'bindings', 'Bindings', $content, $notice);
    }

    #[Route(path: '/admin/smart-mailer/bindings/create', name: 'smart_mailer_admin_binding_create', methods: ['POST'])]
    public function create(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(self::CREATE_TOKEN_ID, (string) $request->request->get('_token', ''))) {
            return $this->redirectWithStatus('csrf_error');
        }

        $payload = $this->buildPayload($request);
        if ($payload === null) {
            return $this->redirectWithStatus('validation_error');
        }

        $connection = $this->db();
        try {
            $providerExists = (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM smr_provider WHERE id = :id',
                ['id' => $payload['provider_id']]
            );
            if ($providerExists === 0) {
                return $this->redirectWithStatus('provider_not_found');
            }

            $duplicate = (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM smr_provider_domain_binding WHERE provider_id = :provider_id AND domain = :domain',
                ['provider_id' => $payload['provider_id'], 'domain' => $payload['domain']]
            );
            if ($duplicate > 0) {
                return $this->redirectWithStatus('duplicate_binding');
            }

            $insertedRows = $connection->insert('smr_provider_domain_binding', [
                'provider_id' => $payload['provider_id'],
                'domain' => $payload['domain'],
                'priority' => $payload['priority'],
                'active' => $payload['active'],
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
            if ($insertedRows !== 1) {
                return $this->redirectWithStatus('db_error');
            }
        } catch (Throwable) {
            return $this->redirectWithStatus('db_error');
        }

        return $this->redirectWithStatus('created');
    }

    #[Route(path: '/admin/smart-mailer/bindings/{id}/update', name: 'smart_mailer_admin_binding_update', methods: ['POST'])]
    public function update(Request $request, string $id): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('smart_mailer_binding_update_'.$id, (string) $request->request->get('_token', ''))) {
            return $this->redirectWithStatus('csrf_error');
        }

        $bindingId = filter_var($id, FILTER_VALIDATE_INT);
        if ($bindingId === false) {
            return $this->redirectWithStatus('binding_not_found');
        }

        $payload = $this->buildPayload($request);
        if ($payload === null) {
            return $this->redirectWithStatus('validation_error', $bindingId);
        }

        $connection = $this->db();
        try {
            $existing = $connection->fetchAssociative(
                'SELECT id FROM smr_provider_domain_binding WHERE id = :id',
                ['id' => $bindingId]
            );
            if (!is_array($existing)) {
                return $this->redirectWithStatus('binding_not_found');
            }

            $providerExists = (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM smr_provider WHERE id = :id',
                ['id' => $payload['provider_id']]
            );
            if ($providerExists === 0) {
                return $this->redirectWithStatus('provider_not_found', $bindingId);
            }

            $duplicate = (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM smr_provider_domain_binding
                 WHERE provider_id = :provider_id
                   AND domain = :domain
                   AND id <> :id',
                ['provider_id' => $payload['provider_id'], 'domain' => $payload['domain'], 'id' => $bindingId]
            );
            if ($duplicate > 0) {
                return $this->redirectWithStatus('duplicate_binding', $bindingId);
            }

            $connection->update('smr_provider_domain_binding', [
                'provider_id' => $payload['provider_id'],
                'domain' => $payload['domain'],
                'priority' => $payload['priority'],
                'active' => $payload['active'],
            ], ['id' => $bindingId]);
        } catch (Throwable) {
            return $this->redirectWithStatus('db_error', $bindingId);
        }

        return $this->redirectWithStatus('updated');
    }

    #[Route(path: '/admin/smart-mailer/bindings/{id}/delete', name: 'smart_mailer_admin_binding_delete', methods: ['POST'])]
    public function delete(Request $request, string $id): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('smart_mailer_binding_delete_'.$id, (string) $request->request->get('_token', ''))) {
            return $this->redirectWithStatus('csrf_error');
        }

        $bindingId = filter_var($id, FILTER_VALIDATE_INT);
        if ($bindingId === false) {
            return $this->redirectWithStatus('binding_not_found');
        }

        $connection = $this->db();
        try {
            $deletedRows = $connection->delete('smr_provider_domain_binding', ['id' => $bindingId]);
        } catch (Throwable) {
            return $this->redirectWithStatus('db_error');
        }
        if ($deletedRows === 0) {
            return $this->redirectWithStatus('binding_not_found');
        }

        return $this->redirectWithStatus('deleted');
    }

    private function redirectWithStatus(string $status, ?int $editId = null): RedirectResponse
    {
        $params = ['status' => $status];
        if ($editId !== null) {
            $params['edit'] = (string) $editId;
        }

        return $this->redirect($this->generateUrl('smart_mailer_admin_bindings', $params));
    }

    private function resolveNotice(string $status): ?string
    {
        return match ($status) {
            'created' => 'Domain binding creado correctamente.',
            'updated' => 'Domain binding actualizado correctamente.',
            'deleted' => 'Domain binding eliminado correctamente.',
            'duplicate_binding' => 'Ya existe un binding para ese provider y dominio.',
            'provider_not_found' => 'No se encontró el provider seleccionado.',
            'binding_not_found' => 'No se encontró el domain binding solicitado.',
            'validation_error' => 'Datos inválidos. Verificá provider, dominio y prioridad.',
            'csrf_error' => 'Token de seguridad inválido. Recargá la página e intentá de nuevo.',
            'db_error' => 'Error de base de datos. Revisá migraciones/conexión y volvé a intentar.',
            default => null,
        };
    }

    /**
     * @param list<array<string, mixed>> $providers
     * @param list<array<string, mixed>> $bindings
     * @param array<string, mixed>|null  $editBinding
     */
    private function renderContent(array $providers, array $bindings, ?array $editBinding): string
    {
        $fieldHelp = '<div class="alert alert-secondary mb-md"><strong>Field guide</strong><br><strong>Provider:</strong> proveedor al que se asigna el dominio. <strong>Domain:</strong> dominio exacto o wildcard (`*.example.com`). <strong>Priority:</strong> menor valor tiene preferencia cuando hay múltiples matches. <strong>Active:</strong> habilita/deshabilita el binding sin borrarlo.</div>';

        $createForm = $this->buildBindingForm(
            action: $this->generateUrl('smart_mailer_admin_binding_create'),
            tokenId: self::CREATE_TOKEN_ID,
            buttonLabel: 'Crear domain binding',
            providers: $providers,
            binding: null
        );

        $tableRows = '';
        foreach ($bindings as $binding) {
            $bindingId = (int) ($binding['id'] ?? 0);
            $editUrl = $this->generateUrl('smart_mailer_admin_bindings', ['edit' => (string) $bindingId]);
            $deleteAction = $this->generateUrl('smart_mailer_admin_binding_delete', ['id' => (string) $bindingId]);
            $tableRows .= sprintf(
                '<tr><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%d</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;"><a href="%s" data-toggle="ajax">Editar</a> <form method="post" action="%s" style="display:inline;" onsubmit="return confirm(\'Eliminar domain binding?\');"><input type="hidden" name="_token" value="%s"><button type="submit" style="margin-left:6px;border:0;background:#dc2626;color:#fff;padding:5px 8px;border-radius:6px;cursor:pointer;">Eliminar</button></form></td></tr>',
                $this->escape((string) ($binding['provider_name'] ?? $binding['provider_code'] ?? '')),
                $this->escape((string) ($binding['domain'] ?? '')),
                (int) ($binding['priority'] ?? 0),
                ((int) ($binding['active'] ?? 0)) === 1 ? 'Sí' : 'No',
                $this->escape((string) ($binding['created_at'] ?? '')),
                $this->escape($editUrl),
                $this->escape($deleteAction),
                $this->escape($this->csrfToken('smart_mailer_binding_delete_'.$bindingId))
            );
        }
        if ($tableRows === '') {
            $tableRows = '<tr><td colspan="6" style="padding:10px;border-top:1px solid #e5e7eb;color:#6b7280;">No hay domain bindings configurados.</td></tr>';
        }

        $editSection = '';
        if (is_array($editBinding)) {
            $editId = (int) ($editBinding['id'] ?? 0);
            $editDomain = (string) ($editBinding['domain'] ?? '');
            $editSection = sprintf(
                '<div style="margin-top:18px;"><h3 style="margin:0 0 10px;">Editar domain binding: %s</h3>%s</div>',
                $this->escape($editDomain),
                $this->buildBindingForm(
                    action: $this->generateUrl('smart_mailer_admin_binding_update', ['id' => (string) $editId]),
                    tokenId: 'smart_mailer_binding_update_'.$editId,
                    buttonLabel: 'Guardar cambios',
                    providers: $providers,
                    binding: $editBinding
                )
            );
        }

        return sprintf(
            '<h2 style="margin:0 0 12px;">Domain Bindings</h2><p style="margin:0 0 16px;color:#4b5563;">Mapeo provider-dominio para escenarios de `domain_routing` y `multi_domain_routing`.</p>%s<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">%s<div><h3 style="margin:0 0 8px;">Bindings existentes</h3><div style="overflow:auto;"><table style="width:100%%;border-collapse:collapse;font-size:13px;"><thead><tr style="text-align:left;background:#f9fafb;"><th style="padding:8px;">Provider</th><th style="padding:8px;">Domain</th><th style="padding:8px;">Priority</th><th style="padding:8px;">Activo</th><th style="padding:8px;">Created</th><th style="padding:8px;">Acciones</th></tr></thead><tbody>%s</tbody></table></div></div></div>%s',
            $fieldHelp,
            $createForm,
            $tableRows,
            $editSection
        );
    }

    /**
     * @param list<array<string, mixed>> $providers
     * @param array<string, mixed>|null  $binding
     */
    private function buildBindingForm(string $action, string $tokenId, string $buttonLabel, array $providers, ?array $binding): string
    {
        $selectedProviderId = (string) ($binding['provider_id'] ?? '');
        $domain = (string) ($binding['domain'] ?? '');
        $priority = (string) ((int) ($binding['priority'] ?? 100));
        $active = ((int) ($binding['active'] ?? 1)) === 1;

        $providerOptions = '';
        foreach ($providers as $provider) {
            $providerId = (string) ($provider['id'] ?? '');
            if ($providerId === '') {
                continue;
            }

            $providerName = (string) ($provider['name'] ?? '');
            $providerCode = (string) ($provider['code'] ?? '');
            $isEnabled = ((int) ($provider['enabled'] ?? 0)) === 1;
            $selected = $providerId === $selectedProviderId ? ' selected' : '';
            $disabledLabel = $isEnabled ? '' : ' (disabled)';
            $providerOptions .= sprintf(
                '<option value="%s"%s>%s</option>',
                $this->escape($providerId),
                $selected,
                $this->escape(trim($providerName.' ['.$providerCode.']'.$disabledLabel))
            );
        }
        if ($providerOptions === '') {
            $providerOptions = '<option value="">Sin providers disponibles</option>';
        }

        return sprintf(
            '<div><h3 style="margin:0 0 8px;">%s</h3><form method="post" action="%s" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;"><input type="hidden" name="_token" value="%s"><label style="display:flex;flex-direction:column;gap:4px;">Provider<select name="provider_id" required style="padding:8px;border:1px solid #d1d5db;border-radius:6px;">%s</select></label><label style="display:flex;flex-direction:column;gap:4px;">Domain<input name="domain" value="%s" required maxlength="255" placeholder="ej: gmail.com o *.gmail.com" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;">Priority<input type="number" name="priority" value="%s" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;align-items:center;gap:8px;margin-top:24px;"><input type="checkbox" name="active" value="1"%s> Activo</label><div style="grid-column:1 / -1;"><button type="submit" style="border:0;background:#111827;color:#fff;padding:8px 12px;border-radius:6px;cursor:pointer;">%s</button></div></form></div>',
            is_array($binding) ? 'Editar domain binding' : 'Nuevo domain binding',
            $this->escape($action),
            $this->escape($this->csrfToken($tokenId)),
            $providerOptions,
            $this->escape($domain),
            $this->escape($priority),
            $active ? ' checked' : '',
            $this->escape($buttonLabel)
        );
    }

    /**
     * @return array<string, int|string>|null
     */
    private function buildPayload(Request $request): ?array
    {
        $providerId = trim((string) $request->request->get('provider_id', ''));
        if ($providerId === '') {
            return null;
        }

        $domain = $this->normalizeDomain((string) $request->request->get('domain', ''));
        if ($domain === null) {
            return null;
        }

        $priority = $this->toInt($request, 'priority', 100, -32768, 32767);

        return [
            'provider_id' => $providerId,
            'domain' => $domain,
            'priority' => $priority,
            'active' => $request->request->getBoolean('active', false) ? 1 : 0,
        ];
    }

    private function normalizeDomain(string $domain): ?string
    {
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            return null;
        }

        $prefix = '';
        if (str_starts_with($domain, '*.')) {
            $prefix = '*.';
            $domain = substr($domain, 2);
        }

        $domain = ltrim($domain, '.');
        if ($domain === '' || strlen($domain) > 255) {
            return null;
        }

        $validated = filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
        if (!is_string($validated) || $validated === '') {
            return null;
        }

        return $prefix.$domain;
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
}
