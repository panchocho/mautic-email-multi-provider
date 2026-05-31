<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin;

use JsonException;
use Throwable;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RulesAdminController extends AbstractAdminController
{
    private const CREATE_TOKEN_ID = 'smart_mailer_rule_create';

    private ?string $lastValidationError = null;

    #[Route(path: '/admin/smart-mailer/rules', name: 'smart_mailer_admin_rules', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $connection = $this->db();

        try {
            /** @var list<array<string, mixed>> $rules */
            $rules = $connection->fetchAllAssociative(
                'SELECT r.id, r.profile_id, r.provider_id, r.domain_pattern, r.priority, r.weight, r.enabled, r.constraints,
                        p.name AS profile_name, pr.name AS provider_name, pr.code AS provider_code
                 FROM smr_routing_rule r
                 INNER JOIN smr_routing_profile p ON p.id = r.profile_id
                 LEFT JOIN smr_provider pr ON pr.id = r.provider_id
                 ORDER BY r.priority DESC, r.id DESC
                 LIMIT 200'
            );

            /** @var list<array<string, mixed>> $profiles */
            $profiles = $connection->fetchAllAssociative(
                'SELECT id, name, mode, enabled FROM smr_routing_profile ORDER BY enabled DESC, name ASC'
            );

            /** @var list<array<string, mixed>> $providers */
            $providers = $connection->fetchAllAssociative(
                'SELECT id, name, code, enabled FROM smr_provider ORDER BY enabled DESC, name ASC'
            );

            $editId = trim((string) $request->query->get('edit', ''));
            $editRule = $editId !== ''
                ? $connection->fetchAssociative(
                    'SELECT id, profile_id, provider_id, domain_pattern, priority, weight, enabled, constraints
                     FROM smr_routing_rule WHERE id = :id',
                    ['id' => $editId]
                ) ?: null
                : null;
        } catch (Throwable) {
            $rules = [];
            $profiles = [];
            $providers = [];
            $editRule = null;
        }

        $content = $this->renderRulesContent($rules, $profiles, $providers, $editRule);
        $notice = $this->resolveNotice((string) $request->query->get('status', ''));

        return $this->renderAdminPage($request, 'rules', 'Rules', $content, $notice);
    }

    #[Route(path: '/admin/smart-mailer/rules/create', name: 'smart_mailer_admin_rule_create', methods: ['POST'])]
    public function create(Request $request): RedirectResponse
    {
        $connection = $this->db();

        if (!$this->isCsrfTokenValid(self::CREATE_TOKEN_ID, (string) $request->request->get('_token', ''))) {
            return $this->redirectWithStatus('csrf_error');
        }

        $payload = $this->buildPayload($request);
        if ($payload === null) {
            return $this->redirectWithStatus($this->lastValidationError ?? 'validation_error');
        }

        try {
            $profileCount = (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM smr_routing_profile WHERE id = :id',
                ['id' => $payload['profile_id']]
            );
            if ($profileCount === 0) {
                return $this->redirectWithStatus('profile_not_found');
            }

            $profileMode = (string) $connection->fetchOne(
                'SELECT mode FROM smr_routing_profile WHERE id = :id',
                ['id' => $payload['profile_id']]
            );
            if ($profileMode === '' || !$this->isValidRuleForProfileMode($profileMode, $payload['domain_pattern'])) {
                return $this->redirectWithStatus('profile_mode_requires_domain_pattern');
            }

            if (is_string($payload['provider_id']) && $payload['provider_id'] !== '') {
                $providerCount = (int) $connection->fetchOne(
                    'SELECT COUNT(*) FROM smr_provider WHERE id = :id',
                    ['id' => $payload['provider_id']]
                );
                if ($providerCount === 0) {
                    return $this->redirectWithStatus('provider_not_found');
                }
            }

            $now = gmdate('Y-m-d H:i:s');
            $connection->insert('smr_routing_rule', [
                'profile_id' => $payload['profile_id'],
                'provider_id' => $payload['provider_id'] !== '' ? $payload['provider_id'] : null,
                'domain_pattern' => $payload['domain_pattern'],
                'priority' => $payload['priority'],
                'weight' => $payload['weight'],
                'enabled' => $payload['enabled'],
                'constraints' => $payload['constraints_json'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (Throwable) {
            return $this->redirectWithStatus('db_error');
        }

        return $this->redirectWithStatus('created');
    }

    #[Route(path: '/admin/smart-mailer/rules/{id}/update', name: 'smart_mailer_admin_rule_update', methods: ['POST'])]
    public function update(Request $request, string $id): RedirectResponse
    {
        $connection = $this->db();

        if (!$this->isCsrfTokenValid('smart_mailer_rule_update_'.$id, (string) $request->request->get('_token', ''))) {
            return $this->redirectWithStatus('csrf_error');
        }

        try {
            $existing = $connection->fetchAssociative('SELECT id FROM smr_routing_rule WHERE id = :id', ['id' => $id]);
        } catch (Throwable) {
            return $this->redirectWithStatus('db_error');
        }

        if (!is_array($existing)) {
            return $this->redirectWithStatus('rule_not_found');
        }

        $payload = $this->buildPayload($request);
        if ($payload === null) {
            return $this->redirectWithStatus($this->lastValidationError ?? 'validation_error', $id);
        }

        try {
            $profileCount = (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM smr_routing_profile WHERE id = :id',
                ['id' => $payload['profile_id']]
            );
            if ($profileCount === 0) {
                return $this->redirectWithStatus('profile_not_found', $id);
            }

            $profileMode = (string) $connection->fetchOne(
                'SELECT mode FROM smr_routing_profile WHERE id = :id',
                ['id' => $payload['profile_id']]
            );
            if ($profileMode === '' || !$this->isValidRuleForProfileMode($profileMode, $payload['domain_pattern'])) {
                return $this->redirectWithStatus('profile_mode_requires_domain_pattern', $id);
            }

            if (is_string($payload['provider_id']) && $payload['provider_id'] !== '') {
                $providerCount = (int) $connection->fetchOne(
                    'SELECT COUNT(*) FROM smr_provider WHERE id = :id',
                    ['id' => $payload['provider_id']]
                );
                if ($providerCount === 0) {
                    return $this->redirectWithStatus('provider_not_found', $id);
                }
            }

            $connection->update('smr_routing_rule', [
                'profile_id' => $payload['profile_id'],
                'provider_id' => $payload['provider_id'] !== '' ? $payload['provider_id'] : null,
                'domain_pattern' => $payload['domain_pattern'],
                'priority' => $payload['priority'],
                'weight' => $payload['weight'],
                'enabled' => $payload['enabled'],
                'constraints' => $payload['constraints_json'],
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ], ['id' => $id]);
        } catch (Throwable) {
            return $this->redirectWithStatus('db_error', $id);
        }

        return $this->redirectWithStatus('updated');
    }

    #[Route(path: '/admin/smart-mailer/rules/{id}/delete', name: 'smart_mailer_admin_rule_delete', methods: ['POST'])]
    public function delete(Request $request, string $id): RedirectResponse
    {
        $connection = $this->db();

        if (!$this->isCsrfTokenValid('smart_mailer_rule_delete_'.$id, (string) $request->request->get('_token', ''))) {
            return $this->redirectWithStatus('csrf_error');
        }

        try {
            $deletedRows = $connection->delete('smr_routing_rule', ['id' => $id]);
        } catch (Throwable) {
            return $this->redirectWithStatus('db_error');
        }

        if ($deletedRows === 0) {
            return $this->redirectWithStatus('rule_not_found');
        }

        return $this->redirectWithStatus('deleted');
    }

    private function redirectWithStatus(string $status, ?string $editId = null): RedirectResponse
    {
        $params = ['status' => $status];
        if (is_string($editId) && $editId !== '') {
            $params['edit'] = $editId;
        }

        return $this->redirect($this->generateUrl('smart_mailer_admin_rules', $params));
    }

    private function resolveNotice(string $status): ?string
    {
        return match ($status) {
            'created' => 'Rule creada correctamente.',
            'updated' => 'Rule actualizada correctamente.',
            'deleted' => 'Rule eliminada correctamente.',
            'validation_error' => 'Datos inválidos. Verificá profile, valores numéricos y JSON.',
            'profile_mode_requires_domain_pattern' => 'Este profile requiere domain_pattern para reglas de dominio.',
            'profile_not_found' => 'No se encontró el profile seleccionado.',
            'provider_not_found' => 'No se encontró el proveedor seleccionado.',
            'rule_not_found' => 'No se encontró la rule solicitada.',
            'csrf_error' => 'Token de seguridad inválido. Recargá la página e intentá de nuevo.',
            'db_error' => 'Error de base de datos. Revisá migraciones/conexión y volvé a intentar.',
            default => null,
        };
    }

    /**
     * @param list<array<string, mixed>> $rules
     * @param list<array<string, mixed>> $profiles
     * @param list<array<string, mixed>> $providers
     * @param array<string, mixed>|null  $editRule
     */
    private function renderRulesContent(array $rules, array $profiles, array $providers, ?array $editRule): string
    {
        $fieldHelp = '<div class="alert alert-secondary mb-md"><strong>Field guide</strong><br><strong>Profile:</strong> profile al que pertenece la regla. <strong>Provider:</strong> proveedor específico al que apunta la regla. <strong>Domain pattern:</strong> coincidencia simple por dominio (ej: `gmail.com` o `*.yahoo.com`). <strong>Priority:</strong> prioridad de evaluación (mayor primero). <strong>Weight:</strong> distribución relativa dentro del mismo nivel. <strong>Enabled:</strong> activa o pausa la regla. <strong>constraints_json:</strong> condiciones avanzadas (tenant, metadata, tipos de campaña, etc.).</div>';

        $profilesMissing = $profiles === [];
        $profilesWarning = $profilesMissing
            ? '<div class="alert alert-warning">Necesitás crear al menos un <strong>profile</strong> antes de cargar rules.</div>'
            : '';

        $createForm = $this->buildRuleForm(
            action: $this->generateUrl('smart_mailer_admin_rule_create'),
            tokenId: self::CREATE_TOKEN_ID,
            buttonLabel: 'Crear rule',
            rule: null,
            profiles: $profiles,
            providers: $providers
        );

        $tableRows = '';
        foreach ($rules as $rule) {
            $id = (string) ($rule['id'] ?? '');
            $editUrl = $this->generateUrl('smart_mailer_admin_rules', ['edit' => $id]);
            $deleteAction = $this->generateUrl('smart_mailer_admin_rule_delete', ['id' => $id]);
            $tableRows .= sprintf(
                '<tr><td style="padding:8px;border-top:1px solid #e5e7eb;">#%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%d</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%d</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;"><a href="%s" data-toggle="ajax">Editar</a> <form method="post" action="%s" style="display:inline;" onsubmit="return confirm(\'Eliminar rule?\');"><input type="hidden" name="_token" value="%s"><button type="submit" style="margin-left:6px;border:0;background:#dc2626;color:#fff;padding:5px 8px;border-radius:6px;cursor:pointer;">Eliminar</button></form></td></tr>',
                $this->escape($id),
                $this->escape((string) ($rule['profile_name'] ?? '')),
                $this->escape((string) (($rule['provider_name'] ?? '') !== '' ? ($rule['provider_name'].' ('.($rule['provider_code'] ?? '').')') : 'N/A')),
                $this->escape((string) ($rule['domain_pattern'] ?? '')),
                (int) ($rule['priority'] ?? 0),
                (int) ($rule['weight'] ?? 0),
                ((int) ($rule['enabled'] ?? 0)) === 1 ? 'Sí' : 'No',
                $this->escape($editUrl),
                $this->escape($deleteAction),
                $this->escape($this->csrfToken('smart_mailer_rule_delete_'.$id))
            );
        }

        if ($tableRows === '') {
            $tableRows = '<tr><td colspan="8" style="padding:10px;border-top:1px solid #e5e7eb;color:#6b7280;">No hay rules configuradas.</td></tr>';
        }

        $editSection = '';
        if (is_array($editRule)) {
            $editId = (string) ($editRule['id'] ?? '');
            $editSection = sprintf(
                '<div style="margin-top:18px;"><h3 style="margin:0 0 10px;">Editar rule #%s</h3>%s</div>',
                $this->escape($editId),
                $this->buildRuleForm(
                    action: $this->generateUrl('smart_mailer_admin_rule_update', ['id' => $editId]),
                    tokenId: 'smart_mailer_rule_update_'.$editId,
                    buttonLabel: 'Guardar cambios',
                    rule: $editRule,
                    profiles: $profiles,
                    providers: $providers
                )
            );
        }

        return sprintf(
            '<h2 style="margin:0 0 12px;">Routing Rules</h2><p style="margin:0 0 16px;color:#4b5563;">Reglas de selección por profile/proveedor con prioridad y constraints JSON.</p><div class="alert alert-info mb-md">Mostrando hasta 200 rules recientes para evitar tiempos de carga altos.</div>%s%s<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">%s<div><h3 style="margin:0 0 8px;">Rules existentes</h3><div style="overflow:auto;"><table style="width:100%%;border-collapse:collapse;font-size:13px;"><thead><tr style="text-align:left;background:#f9fafb;"><th style="padding:8px;">ID</th><th style="padding:8px;">Profile</th><th style="padding:8px;">Provider</th><th style="padding:8px;">Domain pattern</th><th style="padding:8px;">Priority</th><th style="padding:8px;">Weight</th><th style="padding:8px;">Activa</th><th style="padding:8px;">Acciones</th></tr></thead><tbody>%s</tbody></table></div></div></div>%s',
            $fieldHelp,
            $profilesWarning,
            $createForm,
            $tableRows,
            $editSection
        );
    }

    /**
     * @param array<string, mixed>|null $rule
     * @param list<array<string, mixed>> $profiles
     * @param list<array<string, mixed>> $providers
     */
    private function buildRuleForm(string $action, string $tokenId, string $buttonLabel, ?array $rule, array $profiles, array $providers): string
    {
        if ($profiles === []) {
            return '<div class="alert alert-warning">No hay profiles disponibles para crear rules.</div>';
        }

        $profileId = (string) ($rule['profile_id'] ?? ($profiles[0]['id'] ?? ''));
        $providerId = (string) ($rule['provider_id'] ?? '');
        $domainPattern = (string) ($rule['domain_pattern'] ?? '');
        $priority = (string) ((int) ($rule['priority'] ?? 100));
        $weight = (string) ((int) ($rule['weight'] ?? 100));
        $enabledChecked = ((int) ($rule['enabled'] ?? 1)) === 1;
        $constraintsJson = $this->prettyJson((string) ($rule['constraints'] ?? '{}'));

        $profileOptions = '';
        foreach ($profiles as $profile) {
            $id = (string) ($profile['id'] ?? '');
            $selected = $id === $profileId ? ' selected' : '';
            $label = (string) ($profile['name'] ?? '');
            $profileOptions .= sprintf(
                '<option value="%s"%s>%s</option>',
                $this->escape($id),
                $selected,
                $this->escape($label)
            );
        }

        $providerOptions = '<option value="">Ninguno</option>';
        foreach ($providers as $provider) {
            $id = (string) ($provider['id'] ?? '');
            $selected = $id === $providerId ? ' selected' : '';
            $label = sprintf('%s (%s)', (string) ($provider['name'] ?? ''), (string) ($provider['code'] ?? ''));
            $providerOptions .= sprintf(
                '<option value="%s"%s>%s</option>',
                $this->escape($id),
                $selected,
                $this->escape($label)
            );
        }

        return sprintf(
            '<div><h3 style="margin:0 0 8px;">%s</h3><form method="post" action="%s" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;"><input type="hidden" name="_token" value="%s"><label style="display:flex;flex-direction:column;gap:4px;">Profile<select name="profile_id" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;">%s</select></label><label style="display:flex;flex-direction:column;gap:4px;">Provider<select name="provider_id" required style="padding:8px;border:1px solid #d1d5db;border-radius:6px;">%s</select></label><label style="display:flex;flex-direction:column;gap:4px;">Domain pattern<input name="domain_pattern" value="%s" maxlength="255" placeholder="ej: gmail.com o *.yahoo.com" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;">Priority<input type="number" name="priority" value="%s" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;">Weight<input type="number" min="1" max="32767" name="weight" value="%s" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;align-items:center;gap:8px;margin-top:24px;"><input type="checkbox" name="enabled" value="1"%s> Habilitada</label><label style="display:flex;flex-direction:column;gap:4px;grid-column:1 / -1;">constraints_json (objeto JSON)<textarea name="constraints_json" rows="8" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;font-family:monospace;">%s</textarea></label><div style="grid-column:1 / -1;"><button type="submit" style="border:0;background:#111827;color:#fff;padding:8px 12px;border-radius:6px;cursor:pointer;">%s</button></div></form></div>',
            is_array($rule) ? 'Editar rule' : 'Nueva rule',
            $this->escape($action),
            $this->escape($this->csrfToken($tokenId)),
            $profileOptions,
            $providerOptions,
            $this->escape($domainPattern),
            $this->escape($priority),
            $this->escape($weight),
            $enabledChecked ? ' checked' : '',
            $this->escape($constraintsJson),
            $this->escape($buttonLabel)
        );
    }

    /**
     * @return array{profile_id: int, provider_id: string, domain_pattern: ?string, priority: int, weight: int, enabled: int, constraints_json: string}|null
     */
    private function buildPayload(Request $request): ?array
    {
        $profileRaw = $request->request->get('profile_id');
        $profileId = filter_var($profileRaw, FILTER_VALIDATE_INT);
        if ($profileId === false || $profileId < 1) {
            return null;
        }

        $providerId = trim((string) $request->request->get('provider_id', ''));
        if ($providerId === '' || strlen($providerId) > 36 || preg_match('/^[a-zA-Z0-9-]+$/', $providerId) !== 1) {
            $this->lastValidationError = 'validation_error';

            return null;
        }

        $domainPattern = trim((string) $request->request->get('domain_pattern', ''));
        if (mb_strlen($domainPattern) > 255) {
            $this->lastValidationError = 'validation_error';

            return null;
        }
        if ($domainPattern === '') {
            $domainPattern = null;
        }

        $constraintsRaw = trim((string) $request->request->get('constraints_json', '{}'));
        try {
            $decodedConstraints = $constraintsRaw === '' ? [] : json_decode($constraintsRaw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->lastValidationError = 'validation_error';

            return null;
        }

        if (!is_array($decodedConstraints)) {
            $this->lastValidationError = 'validation_error';

            return null;
        }

        $constraintsJson = json_encode($decodedConstraints, JSON_UNESCAPED_SLASHES);
        $this->lastValidationError = null;

        return [
            'profile_id' => $profileId,
            'provider_id' => $providerId,
            'domain_pattern' => $domainPattern,
            'priority' => $this->toInt($request, 'priority', 100, 0),
            'weight' => $this->toInt($request, 'weight', 100, 1, 32767),
            'enabled' => $request->request->getBoolean('enabled', false) ? 1 : 0,
            'constraints_json' => is_string($constraintsJson) ? $constraintsJson : '{}',
        ];
    }

    private function isValidRuleForProfileMode(string $profileMode, ?string $domainPattern): bool
    {
        if (in_array($profileMode, ['domain_routing', 'multi_domain_routing'], true)) {
            return is_string($domainPattern) && $domainPattern !== '';
        }

        return true;
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
}
