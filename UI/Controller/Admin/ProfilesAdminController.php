<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin;

use JsonException;
use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\RoutingMode;
use Throwable;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProfilesAdminController extends AbstractAdminController
{
    private const CREATE_TOKEN_ID = 'smart_mailer_profile_create';

    private ?string $lastValidationError = null;

    #[Route(path: '/admin/smart-mailer/profiles', name: 'smart_mailer_admin_profiles', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $connection = $this->db();

        try {
            /** @var list<array<string, mixed>> $profiles */
            $profiles = $connection->fetchAllAssociative(
                'SELECT p.id, p.name, p.mode, p.enabled, p.config, p.created_at, p.updated_at,
                        (SELECT COUNT(*) FROM smr_routing_rule r WHERE r.profile_id = p.id) AS rules_count
                 FROM smr_routing_profile p
                 ORDER BY p.enabled DESC, p.name ASC'
            );

            $editId = trim((string) $request->query->get('edit', ''));
            $editProfile = $editId !== ''
                ? $connection->fetchAssociative(
                    'SELECT id, name, mode, enabled, config FROM smr_routing_profile WHERE id = :id',
                    ['id' => $editId]
                ) ?: null
                : null;
        } catch (Throwable) {
            $profiles = [];
            $editProfile = null;
        }

        $content = $this->renderProfilesContent($profiles, $editProfile);
        $notice = $this->resolveNotice((string) $request->query->get('status', ''));

        return $this->renderAdminPage($request, 'profiles', 'Profiles', $content, $notice);
    }

    #[Route(path: '/admin/smart-mailer/profiles/create', name: 'smart_mailer_admin_profile_create', methods: ['POST'])]
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
            $duplicate = $connection->fetchOne(
                'SELECT COUNT(*) FROM smr_routing_profile WHERE name = :name',
                ['name' => $payload['name']]
            );
            if ((int) $duplicate > 0) {
                return $this->redirectWithStatus('duplicate_name');
            }

            $now = gmdate('Y-m-d H:i:s');
            $connection->insert('smr_routing_profile', [
                'name' => $payload['name'],
                'mode' => $payload['mode'],
                'enabled' => $payload['enabled'],
                'config' => $payload['config_json'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (Throwable) {
            return $this->redirectWithStatus('db_error');
        }

        return $this->redirectWithStatus('created');
    }

    #[Route(path: '/admin/smart-mailer/profiles/{id}/update', name: 'smart_mailer_admin_profile_update', methods: ['POST'])]
    public function update(Request $request, string $id): RedirectResponse
    {
        $connection = $this->db();

        if (!$this->isCsrfTokenValid('smart_mailer_profile_update_'.$id, (string) $request->request->get('_token', ''))) {
            return $this->redirectWithStatus('csrf_error');
        }

        try {
            $existing = $connection->fetchAssociative('SELECT id, name FROM smr_routing_profile WHERE id = :id', ['id' => $id]);
        } catch (Throwable) {
            return $this->redirectWithStatus('db_error');
        }

        if (!is_array($existing)) {
            return $this->redirectWithStatus('profile_not_found');
        }

        $payload = $this->buildPayload($request);
        if ($payload === null) {
            return $this->redirectWithStatus($this->lastValidationError ?? 'validation_error', $id);
        }

        try {
            if ($payload['name'] !== (string) $existing['name']) {
                $duplicate = $connection->fetchOne(
                    'SELECT COUNT(*) FROM smr_routing_profile WHERE name = :name AND id <> :id',
                    ['name' => $payload['name'], 'id' => $id]
                );
                if ((int) $duplicate > 0) {
                    return $this->redirectWithStatus('duplicate_name', $id);
                }
            }

            $connection->update('smr_routing_profile', [
                'name' => $payload['name'],
                'mode' => $payload['mode'],
                'enabled' => $payload['enabled'],
                'config' => $payload['config_json'],
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ], ['id' => $id]);
        } catch (Throwable) {
            return $this->redirectWithStatus('db_error', $id);
        }

        return $this->redirectWithStatus('updated');
    }

    #[Route(path: '/admin/smart-mailer/profiles/{id}/delete', name: 'smart_mailer_admin_profile_delete', methods: ['POST'])]
    public function delete(Request $request, string $id): RedirectResponse
    {
        $connection = $this->db();

        if (!$this->isCsrfTokenValid('smart_mailer_profile_delete_'.$id, (string) $request->request->get('_token', ''))) {
            return $this->redirectWithStatus('csrf_error');
        }

        try {
            $deletedRows = $connection->delete('smr_routing_profile', ['id' => $id]);
        } catch (Throwable) {
            return $this->redirectWithStatus('db_error');
        }

        if ($deletedRows === 0) {
            return $this->redirectWithStatus('profile_not_found');
        }

        return $this->redirectWithStatus('deleted');
    }

    private function redirectWithStatus(string $status, ?string $editId = null): RedirectResponse
    {
        $params = ['status' => $status];
        if (is_string($editId) && $editId !== '') {
            $params['edit'] = $editId;
        }

        return $this->redirect($this->generateUrl('smart_mailer_admin_profiles', $params));
    }

    private function resolveNotice(string $status): ?string
    {
        return match ($status) {
            'created' => 'Profile creado correctamente.',
            'updated' => 'Profile actualizado correctamente.',
            'deleted' => 'Profile eliminado correctamente.',
            'duplicate_name' => 'El nombre del profile ya existe. Usá un nombre único.',
            'validation_error' => 'Datos inválidos. Verificá nombre, modo y JSON.',
            'mode_validation_error' => 'La configuración JSON no coincide con el modo seleccionado.',
            'config_missing_required_keys' => 'La configuración JSON no tiene las claves requeridas para este modo.',
            'profile_not_found' => 'No se encontró el profile solicitado.',
            'csrf_error' => 'Token de seguridad inválido. Recargá la página e intentá de nuevo.',
            'db_error' => 'Error de base de datos. Revisá migraciones/conexión y volvé a intentar.',
            default => null,
        };
    }

    /**
     * @param list<array<string, mixed>> $profiles
     * @param array<string, mixed>|null $editProfile
     */
    private function renderProfilesContent(array $profiles, ?array $editProfile): string
    {
        $fieldHelp = '<div class="alert alert-secondary mb-md"><strong>Field guide</strong><br><strong>Name:</strong> nombre único del profile de ruteo. <strong>Mode:</strong> modo primario (round robin, failover, etc.). <strong>Enabled:</strong> habilita o deshabilita el profile. <strong>config_json:</strong> parámetros específicos del modo (por ejemplo split percentages o reglas de tags).</div>';
        $modeHelp = '<div class="alert alert-info mb-md"><strong>Tips por modo</strong><br><strong>domain_routing / multi_domain_routing:</strong> agregá <code>{"allowed_domains":["gmail.com","yahoo.com"]}</code>.<br><strong>percentage_split:</strong> usá <code>{"percentage_split":{"brevo":60,"resend":40}}</code>.<br><strong>geo_routing:</strong> podés documentar regiones en <code>config_json</code>, aunque el ranking usa el perfil del provider.<br>Si el modo no necesita parámetros, dejá <code>{}</code>.</div>';
        $createForm = $this->buildProfileForm(
            action: $this->generateUrl('smart_mailer_admin_profile_create'),
            tokenId: self::CREATE_TOKEN_ID,
            buttonLabel: 'Crear profile',
            profile: null
        );

        $tableRows = '';
        foreach ($profiles as $profile) {
            $id = (string) ($profile['id'] ?? '');
            $editUrl = $this->generateUrl('smart_mailer_admin_profiles', ['edit' => $id]);
            $deleteAction = $this->generateUrl('smart_mailer_admin_profile_delete', ['id' => $id]);
            $tableRows .= sprintf(
                '<tr><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%d</td><td style="padding:8px;border-top:1px solid #e5e7eb;"><a href="%s" data-toggle="ajax">Editar</a> <form method="post" action="%s" style="display:inline;" onsubmit="return confirm(\'Eliminar profile?\');"><input type="hidden" name="_token" value="%s"><button type="submit" style="margin-left:6px;border:0;background:#dc2626;color:#fff;padding:5px 8px;border-radius:6px;cursor:pointer;">Eliminar</button></form></td></tr>',
                $this->escape((string) ($profile['name'] ?? '')),
                $this->escape((string) ($profile['mode'] ?? '')),
                ((int) ($profile['enabled'] ?? 0)) === 1 ? 'Sí' : 'No',
                (int) ($profile['rules_count'] ?? 0),
                $this->escape($editUrl),
                $this->escape($deleteAction),
                $this->escape($this->csrfToken('smart_mailer_profile_delete_'.$id))
            );
        }

        if ($tableRows === '') {
            $tableRows = '<tr><td colspan="5" style="padding:10px;border-top:1px solid #e5e7eb;color:#6b7280;">No hay profiles configurados.</td></tr>';
        }

        $editSection = '';
        if (is_array($editProfile)) {
            $editId = (string) ($editProfile['id'] ?? '');
            $editName = (string) ($editProfile['name'] ?? '');
            $editSection = sprintf(
                '<div style="margin-top:18px;"><h3 style="margin:0 0 10px;">Editar profile: %s</h3>%s</div>',
                $this->escape($editName),
                $this->buildProfileForm(
                    action: $this->generateUrl('smart_mailer_admin_profile_update', ['id' => $editId]),
                    tokenId: 'smart_mailer_profile_update_'.$editId,
                    buttonLabel: 'Guardar cambios',
                    profile: $editProfile
                )
            );
        }

        return sprintf(
            '<h2 style="margin:0 0 12px;">Routing Profiles</h2><p style="margin:0 0 16px;color:#4b5563;">Define perfiles de ruteo y su modo principal para campañas y tráfico transaccional.</p>%s%s<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">%s<div><h3 style="margin:0 0 8px;">Profiles existentes</h3><div style="overflow:auto;"><table style="width:100%%;border-collapse:collapse;font-size:13px;"><thead><tr style="text-align:left;background:#f9fafb;"><th style="padding:8px;">Nombre</th><th style="padding:8px;">Modo</th><th style="padding:8px;">Activo</th><th style="padding:8px;">Rules</th><th style="padding:8px;">Acciones</th></tr></thead><tbody>%s</tbody></table></div></div></div>%s',
            $fieldHelp,
            $modeHelp,
            $createForm,
            $tableRows,
            $editSection
        );
    }

    /**
     * @param array<string, mixed>|null $profile
     */
    private function buildProfileForm(string $action, string $tokenId, string $buttonLabel, ?array $profile): string
    {
        $name = trim((string) ($profile['name'] ?? ''));
        $selectedMode = (string) ($profile['mode'] ?? RoutingMode::ROUND_ROBIN->value);
        $enabledChecked = ((int) ($profile['enabled'] ?? 1)) === 1;
        $configJson = $this->prettyJson((string) ($profile['config'] ?? '{}'));

        $modeOptions = '';
        foreach (RoutingMode::cases() as $mode) {
            $selected = $selectedMode === $mode->value ? ' selected' : '';
            $label = strtoupper(str_replace('_', ' ', $mode->value));
            $modeOptions .= sprintf(
                '<option value="%s"%s>%s</option>',
                $this->escape($mode->value),
                $selected,
                $this->escape($label)
            );
        }

        return sprintf(
            '<div><h3 style="margin:0 0 8px;">%s</h3><form method="post" action="%s" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;"><input type="hidden" name="_token" value="%s"><label style="display:flex;flex-direction:column;gap:4px;">Nombre<input name="name" value="%s" required maxlength="120" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;">Mode<select name="mode" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;">%s</select></label><label style="display:flex;align-items:center;gap:8px;margin-top:24px;grid-column:1 / -1;"><input type="checkbox" name="enabled" value="1"%s> Habilitado</label><label style="display:flex;flex-direction:column;gap:4px;grid-column:1 / -1;">config_json (objeto JSON)<textarea name="config_json" rows="8" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;font-family:monospace;">%s</textarea></label><div style="grid-column:1 / -1;"><button type="submit" style="border:0;background:#111827;color:#fff;padding:8px 12px;border-radius:6px;cursor:pointer;">%s</button></div></form></div>',
            is_array($profile) ? 'Editar profile' : 'Nuevo profile',
            $this->escape($action),
            $this->escape($this->csrfToken($tokenId)),
            $this->escape($name),
            $modeOptions,
            $enabledChecked ? ' checked' : '',
            $this->escape($configJson),
            $this->escape($buttonLabel)
        );
    }

    /**
     * @return array{name: string, mode: string, enabled: int, config_json: string}|null
     */
    private function buildPayload(Request $request): ?array
    {
        $name = trim((string) $request->request->get('name', ''));
        $mode = RoutingMode::tryFrom((string) $request->request->get('mode', ''));
        if ($name === '' || mb_strlen($name) > 120 || $mode === null) {
            return null;
        }

        $configRaw = trim((string) $request->request->get('config_json', '{}'));
        try {
            $decodedConfig = $configRaw === '' ? [] : json_decode($configRaw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->lastValidationError = 'validation_error';

            return null;
        }

        if (!is_array($decodedConfig)) {
            $this->lastValidationError = 'validation_error';

            return null;
        }

        $modeValidation = $this->validateModeConfig($mode->value, $decodedConfig);
        if ($modeValidation !== null) {
            $this->lastValidationError = $modeValidation;

            return null;
        }

        $configJson = json_encode($decodedConfig, JSON_UNESCAPED_SLASHES);
        $this->lastValidationError = null;

        return [
            'name' => $name,
            'mode' => $mode->value,
            'enabled' => $request->request->getBoolean('enabled', false) ? 1 : 0,
            'config_json' => is_string($configJson) ? $configJson : '{}',
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function validateModeConfig(string $mode, array $config): ?string
    {
        return match ($mode) {
            RoutingMode::DOMAIN_ROUTING->value, RoutingMode::MULTI_DOMAIN_ROUTING->value => $this->validateDomainRoutingConfig($config),
            RoutingMode::PERCENTAGE_SPLIT->value => $this->validatePercentageSplitConfig($config),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    private function validateDomainRoutingConfig(array $config): ?string
    {
        $domains = $config['allowed_domains'] ?? null;
        if (!is_array($domains) || $domains === []) {
            return 'config_missing_required_keys';
        }

        foreach ($domains as $domain) {
            if (!is_string($domain) || !$this->isValidDomainPattern($domain)) {
                return 'mode_validation_error';
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function validatePercentageSplitConfig(array $config): ?string
    {
        $split = $config['percentage_split'] ?? null;
        if (!is_array($split) || $split === []) {
            return 'config_missing_required_keys';
        }

        $total = 0.0;
        foreach ($split as $provider => $value) {
            if (!is_string($provider) || $provider === '' || !is_numeric($value)) {
                return 'mode_validation_error';
            }

            $total += (float) $value;
        }

        return $total > 0.0 ? null : 'mode_validation_error';
    }

    private function isValidDomainPattern(string $value): bool
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return false;
        }

        if (str_starts_with($value, '*.')) {
            $value = substr($value, 2);
        }

        return preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z]{2,63}$/', $value) === 1;
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
