<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin;

use JsonException;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\SmartMailerSettingsRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SettingsAdminController extends AbstractAdminController
{
    private const SETTINGS_TOKEN_ID = 'smart_mailer_settings';

    #[Route(path: '/admin/smart-mailer/settings', name: 'smart_mailer_admin_settings', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $this->db();
        $repository = $this->settingsRepository();
        if (!$repository instanceof SmartMailerSettingsRepository) {
            return $this->renderAdminPage($request, 'settings', 'Settings', '<div class="alert alert-danger">No se pudo cargar la configuración.</div>');
        }
        $jsonExampleRegistry = $this->jsonExampleRegistry();
        $defaults = SmartMailerSettingsRepository::defaultValues();
        $jsonExamples = $jsonExampleRegistry !== null
            ? $jsonExampleRegistry->exampleJson($jsonExampleRegistry->examples())
            : ($repository->get('ui.json_examples', '{}') ?? '{}');

        $values = [
            'router_active' => $repository->isActive(),
            'delivery_log_retention_days' => $repository->getInt('maintenance.delivery_log_retention_days', (int) ($defaults['maintenance.delivery_log_retention_days'] ?? 90)),
            'delivery_archive_retention_days' => $repository->getInt('maintenance.delivery_archive_retention_days', (int) ($defaults['maintenance.delivery_archive_retention_days'] ?? 180)),
            'health_history_retention_days' => $repository->getInt('maintenance.health_history_retention_days', (int) ($defaults['maintenance.health_history_retention_days'] ?? 180)),
            'retry_retention_days' => $repository->getInt('maintenance.retry_retention_days', (int) ($defaults['maintenance.retry_retention_days'] ?? 30)),
            'retry_max_retries' => $repository->getInt('retry.max_retries', (int) ($defaults['retry.max_retries'] ?? 8)),
            'retry_base_delay_ms' => $repository->getInt('retry.base_delay_ms', (int) ($defaults['retry.base_delay_ms'] ?? 2000)),
            'retry_multiplier' => $repository->getFloat('retry.multiplier', (float) ($defaults['retry.multiplier'] ?? 2.0)),
            'retry_max_delay_ms' => $repository->getInt('retry.max_delay_ms', (int) ($defaults['retry.max_delay_ms'] ?? 300000)),
        ];

        $content = sprintf(
            '<h2 style="margin:0 0 12px;">Settings</h2><p style="margin:0 0 8px;color:#4b5563;">Retención, operación y ejemplos JSON del router.</p><form method="post" action="%s" style="display:grid;grid-template-columns:repeat(2,minmax(220px,1fr));gap:12px;align-items:start;max-width:900px;"><input type="hidden" name="_token" value="%s"><label style="display:flex;flex-direction:column;gap:4px;">Delivery log retention days<input type="number" min="1" name="delivery_log_retention_days" value="%s" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;">Delivery archive retention days<input type="number" min="1" name="delivery_archive_retention_days" value="%s" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;">Health history retention days<input type="number" min="1" name="health_history_retention_days" value="%s" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;">Retry retention days<input type="number" min="1" name="retry_retention_days" value="%s" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;">Retry max retries<input type="number" min="1" name="retry_max_retries" value="%s" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;">Retry base delay (ms)<input type="number" min="0" name="retry_base_delay_ms" value="%s" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;">Retry multiplier<input type="number" min="1" step="0.1" name="retry_multiplier" value="%s" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;">Retry max delay (ms)<input type="number" min="0" name="retry_max_delay_ms" value="%s" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;grid-column:1/-1;">JSON examples overrides<textarea name="json_examples_json" rows="14" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;font-family:monospace;">%s</textarea><small style="color:#6b7280;">Opcional. Estructura esperada: providers / profiles / rules. Si lo dejás vacío, se usan los ejemplos por defecto.</small></label><div style="grid-column:1/-1;"><button type="submit" style="border:0;background:#111827;color:#fff;padding:8px 12px;border-radius:6px;cursor:pointer;">Save settings</button></div></form>',
            $this->escape($this->generateUrl('smart_mailer_admin_settings_save')),
            $this->escape($this->csrfToken(self::SETTINGS_TOKEN_ID)),
            $this->escape((string) $values['delivery_log_retention_days']),
            $this->escape((string) $values['delivery_archive_retention_days']),
            $this->escape((string) $values['health_history_retention_days']),
            $this->escape((string) $values['retry_retention_days']),
            $this->escape((string) $values['retry_max_retries']),
            $this->escape((string) $values['retry_base_delay_ms']),
            $this->escape((string) $values['retry_multiplier']),
            $this->escape((string) $values['retry_max_delay_ms']),
            $this->escape($jsonExamples),
        );

        $activeCheckbox = sprintf(
            '<label style="display:flex;align-items:center;gap:8px;grid-column:1/-1;"><input type="checkbox" name="router_active" value="1"%s> Router activo</label>',
            $values['router_active'] ? ' checked' : '',
        );
        $content = preg_replace(
            '/(<input type="hidden" name="_token" value="[^"]+">)/',
            '$1'.$activeCheckbox,
            $content,
            1
        ) ?: $content;

        return $this->renderAdminPage($request, 'settings', 'Settings', $content, $this->resolveNotice((string) $request->query->get('notice', '')));
    }

    #[Route(path: '/admin/smart-mailer/settings', name: 'smart_mailer_admin_settings_save', methods: ['POST'])]
    public function save(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(self::SETTINGS_TOKEN_ID, (string) $request->request->get('_token', ''))) {
            return $this->redirectWithNotice('csrf_error');
        }

        $this->db();
        $repository = $this->settingsRepository();
        if (!$repository instanceof SmartMailerSettingsRepository) {
            return $this->redirectWithNotice('db_error');
        }

        $repository->setMany([
            'router.active' => $request->request->getBoolean('router_active', false) ? '1' : '0',
            'maintenance.delivery_log_retention_days' => $this->normalizePositiveInt($request->request->get('delivery_log_retention_days'), 90),
            'maintenance.delivery_archive_retention_days' => $this->normalizePositiveInt($request->request->get('delivery_archive_retention_days'), 180),
            'maintenance.health_history_retention_days' => $this->normalizePositiveInt($request->request->get('health_history_retention_days'), 180),
            'maintenance.retry_retention_days' => $this->normalizePositiveInt($request->request->get('retry_retention_days'), 30),
            'retry.max_retries' => $this->normalizePositiveInt($request->request->get('retry_max_retries'), 8),
            'retry.base_delay_ms' => $this->normalizePositiveInt($request->request->get('retry_base_delay_ms'), 2000),
            'retry.multiplier' => $this->normalizePositiveFloat($request->request->get('retry_multiplier'), 2.0),
            'retry.max_delay_ms' => $this->normalizePositiveInt($request->request->get('retry_max_delay_ms'), 300000),
        ]);

        $jsonExamplesRaw = trim((string) $request->request->get('json_examples_json', '{}'));
        if ($jsonExamplesRaw !== '' && $jsonExamplesRaw !== '{}') {
            try {
                $decoded = json_decode($jsonExamplesRaw, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return $this->redirectWithNotice('json_examples_error');
            }

            if (!is_array($decoded)) {
                return $this->redirectWithNotice('json_examples_error');
            }

            $repository->setJson('ui.json_examples', $decoded);
        } else {
            $repository->setJson('ui.json_examples', []);
        }

        return $this->redirectWithNotice('settings_saved');
    }

    private function normalizePositiveInt(mixed $value, int $default): int
    {
        if (is_numeric($value)) {
            return max(1, (int) $value);
        }

        return $default;
    }

    private function normalizePositiveFloat(mixed $value, float $default): float
    {
        if (is_numeric($value)) {
            return max(0.1, (float) $value);
        }

        return $default;
    }

    private function redirectWithNotice(string $notice): RedirectResponse
    {
        return $this->redirect($this->generateUrl('smart_mailer_admin_settings', ['notice' => $notice]));
    }

    private function resolveNotice(string $notice): ?string
    {
        return match ($notice) {
            'settings_saved' => 'Configuración guardada correctamente.',
            'json_examples_error' => 'Los ejemplos JSON no son válidos. Revisá el formato.',
            'csrf_error' => 'Token de seguridad inválido. Recargá la página e intentá de nuevo.',
            'db_error' => 'Error de base de datos. Revisá migraciones/conexión y volvé a intentar.',
            default => null,
        };
    }
}
