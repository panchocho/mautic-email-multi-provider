<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin;

use Throwable;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WarmupAdminController extends AbstractAdminController
{
    private const CREATE_TOKEN_ID = 'smart_mailer_warmup_schedule_create';

    #[Route(path: '/admin/smart-mailer/warmup', name: 'smart_mailer_admin_warmup', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $connection = $this->db();
        $queueName = $this->resolveQueueName('warmup', 'smart_mailer.warmup');

        try {
            /** @var list<array<string, mixed>> $providers */
            $providers = $connection->fetchAllAssociative(
                'SELECT id, name, code, enabled FROM smr_provider ORDER BY enabled DESC, name ASC'
            );

            /** @var list<array<string, mixed>> $schedules */
            $schedules = $connection->fetchAllAssociative(
                'SELECT s.id, s.provider_id, s.scope_type, s.scope_key, s.day_offset, s.day_of_week, s.hour_utc, s.target_volume, s.increment_step, s.active, s.created_at, s.updated_at, p.name AS provider_name, p.code AS provider_code
                 FROM smr_warmup_schedule s
                 LEFT JOIN smr_provider p ON p.id = s.provider_id
                 ORDER BY s.active DESC, s.scope_type ASC, s.scope_key ASC, s.day_offset ASC'
            );

            /** @var list<array<string, mixed>> $states */
            $states = $connection->fetchAllAssociative(
                'SELECT st.id, st.provider_id, st.scope_type, st.scope_key, st.current_day, st.sent_today, st.current_daily_limit, st.current_hourly_limit, st.consecutive_healthy_days, st.consecutive_unhealthy_days, st.last_advanced_at, st.next_planned_at, st.updated_at, p.name AS provider_name, p.code AS provider_code
                 FROM smr_warmup_state st
                 LEFT JOIN smr_provider p ON p.id = st.provider_id
                 ORDER BY st.updated_at DESC
                 LIMIT 200'
            );
        } catch (Throwable $exception) {
            $content = sprintf(
                '<h2 style="margin:0 0 10px;">Warmup</h2><div class="alert alert-danger">No se pudieron cargar datos de warmup. %s</div>',
                $this->escape($exception->getMessage())
            );

            return $this->renderAdminPage($request, 'warmup', 'Warmup', $content);
        }

        $notice = $this->resolveNotice((string) $request->query->get('status', ''));
        $content = $this->renderWarmupContent($queueName, $providers, $schedules, $states);

        return $this->renderAdminPage($request, 'warmup', 'Warmup', $content, $notice);
    }

    #[Route(path: '/admin/smart-mailer/warmup/schedules/create', name: 'smart_mailer_admin_warmup_schedule_create', methods: ['POST'])]
    public function createSchedule(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(self::CREATE_TOKEN_ID, (string) $request->request->get('_token', ''))) {
            return $this->redirectWithStatus('csrf_error');
        }

        $payload = $this->buildSchedulePayload($request);
        if ($payload === null) {
            return $this->redirectWithStatus('validation_error');
        }

        $connection = $this->db();
        try {
            $providerId = $payload['provider_id'] !== '' ? $payload['provider_id'] : null;
            if ($providerId !== null) {
                $exists = (int) $connection->fetchOne('SELECT COUNT(*) FROM smr_provider WHERE id = :id', ['id' => $providerId]);
                if ($exists === 0) {
                    return $this->redirectWithStatus('provider_not_found');
                }
            }

            if ($payload['scope_type'] === 'provider' && $providerId === null) {
                return $this->redirectWithStatus('validation_error');
            }

            if ($payload['scope_type'] === 'domain' && !$this->looksLikeDomain($payload['scope_key'])) {
                return $this->redirectWithStatus('validation_error');
            }

            if ($payload['scope_type'] === 'tenant' && !$this->looksLikeTenantKey($payload['scope_key'])) {
                return $this->redirectWithStatus('validation_error');
            }

            $now = gmdate('Y-m-d H:i:s');
            $connection->insert('smr_warmup_schedule', [
                'provider_id' => $providerId,
                'scope_type' => $payload['scope_type'],
                'scope_key' => $payload['scope_key'],
                'day_offset' => $payload['day_offset'],
                'day_of_week' => $payload['day_of_week'],
                'hour_utc' => $payload['hour_utc'],
                'target_volume' => $payload['target_volume'],
                'increment_step' => $payload['increment_step'],
                'active' => $payload['active'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->syncWarmupState($connection, $providerId, $payload, $now);
        } catch (Throwable) {
            return $this->redirectWithStatus('db_error');
        }

        return $this->redirectWithStatus('created');
    }

    #[Route(path: '/admin/smart-mailer/warmup/schedules/{id}/toggle', name: 'smart_mailer_admin_warmup_schedule_toggle', methods: ['POST'])]
    public function toggleSchedule(Request $request, int $id): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('smart_mailer_warmup_schedule_toggle_'.$id, (string) $request->request->get('_token', ''))) {
            return $this->redirectWithStatus('csrf_error');
        }

        $connection = $this->db();
        try {
            $schedule = $connection->fetchAssociative('SELECT id, active FROM smr_warmup_schedule WHERE id = :id', ['id' => $id]);
            if (!is_array($schedule)) {
                return $this->redirectWithStatus('schedule_not_found');
            }

            $active = ((int) ($schedule['active'] ?? 0)) === 1 ? 0 : 1;
            $connection->update('smr_warmup_schedule', [
                'active' => $active,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ], ['id' => $id]);

            $this->syncWarmupStateForScheduleId($connection, $id, $active === 1);
        } catch (Throwable) {
            return $this->redirectWithStatus('db_error');
        }

        return $this->redirectWithStatus('toggled');
    }

    #[Route(path: '/admin/smart-mailer/warmup/schedules/{id}/delete', name: 'smart_mailer_admin_warmup_schedule_delete', methods: ['POST'])]
    public function deleteSchedule(Request $request, int $id): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('smart_mailer_warmup_schedule_delete_'.$id, (string) $request->request->get('_token', ''))) {
            return $this->redirectWithStatus('csrf_error');
        }

        $connection = $this->db();
        try {
            $deleted = $connection->delete('smr_warmup_schedule', ['id' => $id]);
            if ($deleted === 0) {
                return $this->redirectWithStatus('schedule_not_found');
            }
        } catch (Throwable) {
            return $this->redirectWithStatus('db_error');
        }

        return $this->redirectWithStatus('deleted');
    }

    private function redirectWithStatus(string $status): RedirectResponse
    {
        return $this->redirect($this->generateUrl('smart_mailer_admin_warmup', ['status' => $status]));
    }

    private function resolveNotice(string $status): ?string
    {
        return match ($status) {
            'created' => 'Warmup schedule creado correctamente.',
            'toggled' => 'Warmup schedule actualizado correctamente.',
            'deleted' => 'Warmup schedule eliminado correctamente.',
            'validation_error' => 'Datos inválidos. Revisá scope/day/limits.',
            'provider_not_found' => 'Provider no encontrado.',
            'schedule_not_found' => 'Warmup schedule no encontrado.',
            'csrf_error' => 'Token de seguridad inválido. Recargá la página e intentá de nuevo.',
            'db_error' => 'Error de base de datos. Revisá migraciones/conexión y volvé a intentar.',
            default => null,
        };
    }

    private function resolveQueueName(string $queueKey, string $default): string
    {
        $parameter = sprintf('smart_mailer_router.queues.%s', $queueKey);
        $value = $this->coreParametersHelper->get($parameter);
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return $default;
    }

    /**
     * @param list<array<string, mixed>> $providers
     * @param list<array<string, mixed>> $schedules
     * @param list<array<string, mixed>> $states
     */
    private function renderWarmupContent(string $queueName, array $providers, array $schedules, array $states): string
    {
        $providerOptions = '<option value="">Ninguno</option>';
        foreach ($providers as $provider) {
            $providerOptions .= sprintf(
                '<option value="%s">%s (%s)</option>',
                $this->escape((string) ($provider['id'] ?? '')),
                $this->escape((string) ($provider['name'] ?? '')),
                $this->escape((string) ($provider['code'] ?? ''))
            );
        }

        $createForm = sprintf(
            '<div><h3 style="margin:0 0 8px;">Nuevo warmup schedule</h3><form method="post" action="%s" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;"><input type="hidden" name="_token" value="%s"><label style="display:flex;flex-direction:column;gap:4px;">Provider (opcional)<select name="provider_id" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;">%s</select></label><label style="display:flex;flex-direction:column;gap:4px;">Scope type<select name="scope_type" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"><option value="provider">provider</option><option value="domain">domain</option><option value="tenant">tenant</option><option value="global">global</option></select></label><label style="display:flex;flex-direction:column;gap:4px;">Scope key<input name="scope_key" required maxlength="255" placeholder="ej: gmail.com / tenant-a / default" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;">Day offset<input type="number" min="0" name="day_offset" value="1" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;">Day of week (0-6 opcional)<input type="number" min="0" max="6" name="day_of_week" value="" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;">Hour UTC (0-23 opcional)<input type="number" min="0" max="23" name="hour_utc" value="" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;">Target volume<input type="number" min="1" name="target_volume" value="100" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;">Increment step<input type="number" min="1" name="increment_step" value="50" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;align-items:center;gap:8px;grid-column:1/-1;"><input type="checkbox" name="active" value="1" checked> Activo</label><div style="grid-column:1/-1;"><button type="submit" style="border:0;background:#111827;color:#fff;padding:8px 12px;border-radius:6px;cursor:pointer;">Crear warmup schedule</button></div></form></div>',
            $this->escape($this->generateUrl('smart_mailer_admin_warmup_schedule_create')),
            $this->escape($this->csrfToken(self::CREATE_TOKEN_ID)),
            $providerOptions
        );

        $scheduleRows = '';
        foreach ($schedules as $schedule) {
            $id = (int) ($schedule['id'] ?? 0);
            $toggleAction = $this->generateUrl('smart_mailer_admin_warmup_schedule_toggle', ['id' => $id]);
            $deleteAction = $this->generateUrl('smart_mailer_admin_warmup_schedule_delete', ['id' => $id]);

            $scheduleRows .= sprintf(
                '<tr><td style="padding:8px;border-top:1px solid #e5e7eb;">#%d</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%d</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%d</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;"><form method="post" action="%s" style="display:inline;"><input type="hidden" name="_token" value="%s"><button type="submit" style="border:0;background:#2563eb;color:#fff;padding:5px 8px;border-radius:6px;cursor:pointer;">%s</button></form> <form method="post" action="%s" style="display:inline;" onsubmit="return confirm(\'Eliminar warmup schedule?\');"><input type="hidden" name="_token" value="%s"><button type="submit" style="margin-left:6px;border:0;background:#dc2626;color:#fff;padding:5px 8px;border-radius:6px;cursor:pointer;">Eliminar</button></form></td></tr>',
                $id,
                $this->escape((string) ($schedule['provider_name'] ?? $schedule['provider_code'] ?? 'N/A')),
                $this->escape((string) ($schedule['scope_type'] ?? '')),
                $this->escape((string) ($schedule['scope_key'] ?? '')),
                (int) ($schedule['target_volume'] ?? 0),
                (int) ($schedule['increment_step'] ?? 0),
                (int) ($schedule['active'] ?? 0) === 1 ? 'Sí' : 'No',
                $this->escape($toggleAction),
                $this->escape($this->csrfToken('smart_mailer_warmup_schedule_toggle_'.$id)),
                (int) ($schedule['active'] ?? 0) === 1 ? 'Desactivar' : 'Activar',
                $this->escape($deleteAction),
                $this->escape($this->csrfToken('smart_mailer_warmup_schedule_delete_'.$id))
            );
        }
        if ($scheduleRows === '') {
            $scheduleRows = '<tr><td colspan="8" style="padding:10px;border-top:1px solid #e5e7eb;color:#6b7280;">No hay warmup schedules configurados.</td></tr>';
        }

        $stateRows = '';
        foreach ($states as $state) {
            $stateRows .= sprintf(
                '<tr><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%d</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%d</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%d / %d</td><td style="padding:8px;border-top:1px solid #e5e7eb;">+%d / -%d</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td></tr>',
                $this->escape((string) ($state['provider_name'] ?? $state['provider_code'] ?? 'N/A')),
                $this->escape((string) (($state['scope_type'] ?? '').':'.($state['scope_key'] ?? ''))),
                (int) ($state['current_day'] ?? 0),
                (int) ($state['sent_today'] ?? 0),
                (int) ($state['current_daily_limit'] ?? 0),
                (int) ($state['current_hourly_limit'] ?? 0),
                (int) ($state['consecutive_healthy_days'] ?? 0),
                (int) ($state['consecutive_unhealthy_days'] ?? 0),
                $this->escape((string) ($state['updated_at'] ?? ''))
            );
        }
        if ($stateRows === '') {
            $stateRows = '<tr><td colspan="7" style="padding:10px;border-top:1px solid #e5e7eb;color:#6b7280;">No hay warmup states todavía.</td></tr>';
        }

        return sprintf(
            '<h2 style="margin:0 0 12px;">Warmup</h2><p style="margin:0 0 8px;color:#4b5563;">Ramp-up y límites progresivos por provider/scope. Cola configurada: <strong>%s</strong>.</p><div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:12px;">%s<div><h3 style="margin:0 0 8px;">Warmup schedules</h3><div style="overflow:auto;"><table style="width:100%%;border-collapse:collapse;font-size:13px;"><thead><tr style="text-align:left;background:#f9fafb;"><th style="padding:8px;">ID</th><th style="padding:8px;">Provider</th><th style="padding:8px;">Scope type</th><th style="padding:8px;">Scope key</th><th style="padding:8px;">Target</th><th style="padding:8px;">Increment</th><th style="padding:8px;">Activo</th><th style="padding:8px;">Acciones</th></tr></thead><tbody>%s</tbody></table></div></div></div><div style="margin-top:18px;"><h3 style="margin:0 0 8px;">Warmup states</h3><div style="overflow:auto;"><table style="width:100%%;border-collapse:collapse;font-size:13px;"><thead><tr style="text-align:left;background:#f9fafb;"><th style="padding:8px;">Provider</th><th style="padding:8px;">Scope</th><th style="padding:8px;">Day</th><th style="padding:8px;">Sent today</th><th style="padding:8px;">Limits (D/H)</th><th style="padding:8px;">Streak</th><th style="padding:8px;">Updated</th></tr></thead><tbody>%s</tbody></table></div></div>',
            $this->escape($queueName),
            $createForm,
            $scheduleRows,
            $stateRows
        );
    }

    /**
     * @return array{
     *     provider_id: string,
     *     scope_type: string,
     *     scope_key: string,
     *     day_offset: int,
     *     day_of_week: ?int,
     *     hour_utc: ?int,
     *     target_volume: int,
     *     increment_step: int,
     *     active: int
     * }|null
     */
    private function buildSchedulePayload(Request $request): ?array
    {
        $scopeType = strtolower(trim((string) $request->request->get('scope_type', 'provider')));
        if (!in_array($scopeType, ['provider', 'domain', 'tenant', 'global'], true)) {
            return null;
        }

        $scopeKey = trim((string) $request->request->get('scope_key', ''));
        if ($scopeKey === '' || mb_strlen($scopeKey) > 255) {
            return null;
        }

        $providerId = trim((string) $request->request->get('provider_id', ''));
        if ($providerId !== '' && (strlen($providerId) > 36 || preg_match('/^[a-zA-Z0-9-]+$/', $providerId) !== 1)) {
            return null;
        }

        return [
            'provider_id' => $providerId,
            'scope_type' => $scopeType,
            'scope_key' => $scopeKey,
            'day_offset' => $this->toInt($request, 'day_offset', 1, 0, 3650),
            'day_of_week' => $this->toNullableInt($request, 'day_of_week', 0, 6),
            'hour_utc' => $this->toNullableInt($request, 'hour_utc', 0, 23),
            'target_volume' => $this->toInt($request, 'target_volume', 100, 1),
            'increment_step' => $this->toInt($request, 'increment_step', 50, 1),
            'active' => $request->request->getBoolean('active', true) ? 1 : 0,
        ];
    }

    private function looksLikeDomain(string $value): bool
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

    private function looksLikeTenantKey(string $value): bool
    {
        return preg_match('/^[a-zA-Z0-9._-]{2,255}$/', trim($value)) === 1;
    }

    /**
     * @param array{provider_id: string, scope_type: string, scope_key: string, day_offset: int, day_of_week: ?int, hour_utc: ?int, target_volume: int, increment_step: int, active: int} $payload
     */
    private function syncWarmupState(\Doctrine\DBAL\Connection $connection, ?string $providerId, array $payload, string $now): void
    {
        $dailyLimit = max(1, (int) $payload['target_volume']);
        $hourlyLimit = max(1, (int) ceil($dailyLimit / 24));
        $notes = [
            'schedule_scope' => $payload['scope_type'].':'.$payload['scope_key'],
            'day_offset' => $payload['day_offset'],
            'day_of_week' => $payload['day_of_week'],
            'hour_utc' => $payload['hour_utc'],
            'target_volume' => $payload['target_volume'],
            'increment_step' => $payload['increment_step'],
            'active' => $payload['active'],
        ];

        $stateData = [
            'provider_id' => $providerId,
            'scope_type' => $payload['scope_type'],
            'scope_key' => $payload['scope_key'],
            'current_day' => 1,
            'sent_today' => 0,
            'current_daily_limit' => $dailyLimit,
            'current_hourly_limit' => $hourlyLimit,
            'consecutive_healthy_days' => 0,
            'consecutive_unhealthy_days' => 0,
            'last_advanced_at' => null,
            'next_planned_at' => null,
            'notes' => json_encode($notes, JSON_UNESCAPED_SLASHES) ?: '{}',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $existing = $connection->fetchOne(
            'SELECT id FROM smr_warmup_state WHERE scope_type = :scope_type AND scope_key = :scope_key',
            [
                'scope_type' => $payload['scope_type'],
                'scope_key' => $payload['scope_key'],
            ]
        );

        if ($existing !== false) {
            $connection->update('smr_warmup_state', $stateData, ['scope_type' => $payload['scope_type'], 'scope_key' => $payload['scope_key']]);

            return;
        }

        $connection->insert('smr_warmup_state', $stateData);
    }

    private function syncWarmupStateForScheduleId(\Doctrine\DBAL\Connection $connection, int $scheduleId, bool $active): void
    {
        $schedule = $connection->fetchAssociative(
            'SELECT provider_id, scope_type, scope_key, day_offset, day_of_week, hour_utc, target_volume, increment_step
             FROM smr_warmup_schedule WHERE id = :id',
            ['id' => $scheduleId]
        );

        if (!is_array($schedule)) {
            return;
        }

        $providerId = isset($schedule['provider_id']) && is_string($schedule['provider_id']) && $schedule['provider_id'] !== ''
            ? (string) $schedule['provider_id']
            : null;
        $now = gmdate('Y-m-d H:i:s');

        $payload = [
            'provider_id' => $providerId ?? '',
            'scope_type' => (string) ($schedule['scope_type'] ?? 'provider'),
            'scope_key' => (string) ($schedule['scope_key'] ?? ''),
            'day_offset' => (int) ($schedule['day_offset'] ?? 1),
            'day_of_week' => isset($schedule['day_of_week']) ? (int) $schedule['day_of_week'] : null,
            'hour_utc' => isset($schedule['hour_utc']) ? (int) $schedule['hour_utc'] : null,
            'target_volume' => (int) ($schedule['target_volume'] ?? 1),
            'increment_step' => (int) ($schedule['increment_step'] ?? 1),
            'active' => $active ? 1 : 0,
        ];

        $this->syncWarmupState($connection, $providerId, $payload, $now);
    }

    private function toInt(Request $request, string $key, int $default, int $min, ?int $max = null): int
    {
        $value = filter_var($request->request->get($key), FILTER_VALIDATE_INT);
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

    private function toNullableInt(Request $request, string $key, int $min, int $max): ?int
    {
        $raw = trim((string) $request->request->get($key, ''));
        if ($raw === '') {
            return null;
        }

        $value = filter_var($raw, FILTER_VALIDATE_INT);
        if ($value === false || $value < $min || $value > $max) {
            return null;
        }

        return $value;
    }
}
