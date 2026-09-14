<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin;

use Doctrine\DBAL\Connection;
use MauticPlugin\SmartMailerRouterBundle\Application\Maintenance\DeliveryLogBulkActionService;
use MauticPlugin\SmartMailerRouterBundle\Application\Retry\RetryQueueBulkActionService;
use MauticPlugin\SmartMailerRouterBundle\Application\Retry\RetryQueueService;
use MauticPlugin\SmartMailerRouterBundle\Application\Maintenance\QueueMaintenanceService;
use Throwable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

final class LogsAdminController extends AbstractAdminController
{
    private const RETRY_NOW_TOKEN_PREFIX = 'smart_mailer_retry_now_';
    private const RETRY_LATER_TOKEN_PREFIX = 'smart_mailer_retry_later_';
    private const RETRY_DISCARD_TOKEN_PREFIX = 'smart_mailer_retry_discard_';
    private const RETRY_BULK_TOKEN_ID = 'smart_mailer_retry_bulk';
    private const DELIVERY_BULK_TOKEN_ID = 'smart_mailer_delivery_bulk';
    private const MAINTENANCE_SWEEP_TOKEN_ID = 'smart_mailer_maintenance_sweep';
    private const PROCESS_RETRY_TOKEN_ID = 'smart_mailer_process_retry_queue';

    #[Route(path: '/admin/smart-mailer/logs', name: 'smart_mailer_admin_logs', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $connection = $this->db();
        $logsQueue = $this->resolveQueueName('logs', 'smart_mailer.logs');
        $retryQueue = $this->resolveQueueName('dead_letter', 'smart_mailer.dead_letter');
        $notice = $this->resolveNotice((string) $request->query->get('notice', ''));

        $providerFilter = trim((string) $request->query->get('provider', ''));
        $statusFilter = trim((string) $request->query->get('status', ''));
        $recipientFilter = trim((string) $request->query->get('recipient', ''));
        $domainFilter = trim((string) $request->query->get('domain', ''));
        $profileFilter = trim((string) $request->query->get('profile', ''));
        $externalMessageFilter = trim((string) $request->query->get('external_message_id', ''));
        $limit = max(10, min(500, (int) $request->query->get('limit', 100)));

        $conditions = [];
        $params = [];
        if ($providerFilter !== '') {
            $conditions[] = 'LOWER(p.code) = :provider_code';
            $params['provider_code'] = strtolower($providerFilter);
        }
        if ($statusFilter !== '') {
            $conditions[] = 'LOWER(d.status) = :status';
            $params['status'] = strtolower($statusFilter);
        }
        if ($recipientFilter !== '') {
            $conditions[] = 'd.recipient LIKE :recipient';
            $params['recipient'] = '%'.$recipientFilter.'%';
        }
        if ($domainFilter !== '') {
            $conditions[] = 'd.domain LIKE :domain';
            $params['domain'] = '%'.$domainFilter.'%';
        }
        if ($profileFilter !== '') {
            $conditions[] = 'LOWER(rp.name) LIKE :profile_name';
            $params['profile_name'] = '%'.strtolower($profileFilter).'%';
        }
        if ($externalMessageFilter !== '') {
            $conditions[] = 'd.external_message_id LIKE :external_message_id';
            $params['external_message_id'] = '%'.$externalMessageFilter.'%';
        }

        $where = $conditions !== [] ? 'WHERE '.implode(' AND ', $conditions) : '';

        try {
            /** @var list<array<string, mixed>> $logRows */
            $logRows = $connection->fetchAllAssociative(
                'SELECT d.id, d.recipient, d.sender, d.subject, d.domain, d.status, d.attempt_no, d.failover_count, d.selection_reason, d.http_status, d.latency_ms, d.failure_reason, d.attempted_at, p.name AS provider_name, p.code AS provider_code, rp.name AS profile_name
                 FROM smr_delivery_log d
                 LEFT JOIN smr_provider p ON p.id = d.provider_id
                 LEFT JOIN smr_routing_profile rp ON rp.id = d.routing_profile_id
                 '.$where.'
                 ORDER BY d.attempted_at DESC
                 LIMIT '.$limit,
                $params
            );

            /** @var list<array<string, mixed>> $retryRows */
            $retryRows = $connection->fetchAllAssociative(
                'SELECT id, message_id, attempt, next_attempt_at, status, last_error, created_at
                 FROM smr_retry_queue
                 ORDER BY next_attempt_at ASC
                 LIMIT 200'
            );

            /** @var list<array<string, mixed>> $statusSummary */
            $statusSummary = $connection->fetchAllAssociative(
                'SELECT status, COUNT(*) AS total
                 FROM smr_delivery_log
                 WHERE attempted_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)
                 GROUP BY status
                 ORDER BY total DESC'
            );
        } catch (Throwable $exception) {
            $content = sprintf(
                '<h2 style="margin:0 0 10px;">Logs</h2><div class="alert alert-danger">No se pudieron cargar logs. %s</div>',
                $this->escape($exception->getMessage())
            );

            return $this->renderAdminPage($request, 'logs', 'Logs', $content);
        }

        $summaryHtml = '';
        if ($statusSummary === []) {
            $summaryHtml = '<div style="padding:10px;border:1px solid #e5e7eb;border-radius:8px;background:#f9fafb;color:#6b7280;">Sin actividad de delivery en las últimas 24h.</div>';
        } else {
            $summaryHtml = '<div style="display:flex;flex-wrap:wrap;gap:8px;">';
            foreach ($statusSummary as $statusRow) {
                $summaryHtml .= sprintf(
                    '<div style="padding:8px 10px;border:1px solid #e5e7eb;border-radius:8px;background:#f9fafb;"><strong>%s</strong>: %d</div>',
                    $this->escape((string) ($statusRow['status'] ?? 'unknown')),
                    (int) ($statusRow['total'] ?? 0)
                );
            }
            $summaryHtml .= '</div>';
        }

        $maintenanceButton = sprintf(
            '<form method="post" action="%s" style="margin:0 0 8px;"><input type="hidden" name="_token" value="%s"><button type="submit" style="border:0;background:#111827;color:#fff;padding:8px 12px;border-radius:6px;cursor:pointer;">Run maintenance sweep</button></form>',
            $this->escape($this->generateUrl('smart_mailer_admin_logs_maintenance_sweep')),
            $this->escape($this->csrfToken(self::MAINTENANCE_SWEEP_TOKEN_ID))
        );
        $processRetryButton = sprintf(
            '<form method="post" action="%s" style="margin:0 0 16px;"><input type="hidden" name="_token" value="%s"><button type="submit" style="border:0;background:#2563eb;color:#fff;padding:8px 12px;border-radius:6px;cursor:pointer;">Process due retries</button></form>',
            $this->escape($this->generateUrl('smart_mailer_admin_logs_process_retries')),
            $this->escape($this->csrfToken(self::PROCESS_RETRY_TOKEN_ID))
        );
        $deliveryBulkToolbar = sprintf(
            '<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:0 0 10px;"><form id="smart-mailer-delivery-bulk-form" method="post" action="%s" style="margin:0;"><input type="hidden" name="_token" value="%s"><span style="color:#6b7280;font-size:13px;">Acciones masivas sobre delivery logs:</span></form><button type="submit" form="smart-mailer-delivery-bulk-form" formaction="%s" style="border:0;background:#0f766e;color:#fff;padding:8px 12px;border-radius:6px;cursor:pointer;">Archive selected</button><button type="submit" form="smart-mailer-delivery-bulk-form" formaction="%s" style="border:0;background:#dc2626;color:#fff;padding:8px 12px;border-radius:6px;cursor:pointer;">Purge selected</button></div>',
            $this->escape($this->generateUrl('smart_mailer_admin_delivery_bulk_action', ['action' => 'archive'])),
            $this->escape($this->csrfToken(self::DELIVERY_BULK_TOKEN_ID)),
            $this->escape($this->generateUrl('smart_mailer_admin_delivery_bulk_action', ['action' => 'archive'])),
            $this->escape($this->generateUrl('smart_mailer_admin_delivery_bulk_action', ['action' => 'purge']))
        );
        $retryBulkToolbar = sprintf(
            '<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:0 0 10px;"><form id="smart-mailer-retry-bulk-form" method="post" action="%s" style="margin:0;"><input type="hidden" name="_token" value="%s"><span style="color:#6b7280;font-size:13px;">Acciones masivas sobre retry queue:</span></form><button type="submit" form="smart-mailer-retry-bulk-form" formaction="%s" style="border:0;background:#2563eb;color:#fff;padding:8px 12px;border-radius:6px;cursor:pointer;">Retry selected now</button><button type="submit" form="smart-mailer-retry-bulk-form" formaction="%s" style="border:0;background:#b45309;color:#fff;padding:8px 12px;border-radius:6px;cursor:pointer;">Retry selected +15m</button><button type="submit" form="smart-mailer-retry-bulk-form" formaction="%s" style="border:0;background:#dc2626;color:#fff;padding:8px 12px;border-radius:6px;cursor:pointer;">Discard selected</button></div>',
            $this->escape($this->generateUrl('smart_mailer_admin_retry_bulk_action', ['action' => 'now'])),
            $this->escape($this->csrfToken(self::RETRY_BULK_TOKEN_ID)),
            $this->escape($this->generateUrl('smart_mailer_admin_retry_bulk_action', ['action' => 'now'])),
            $this->escape($this->generateUrl('smart_mailer_admin_retry_bulk_action', ['action' => 'later'])),
            $this->escape($this->generateUrl('smart_mailer_admin_retry_bulk_action', ['action' => 'discard']))
        );

        $logsTableRows = '';
        foreach ($logRows as $row) {
            $deliveryLogId = (int) ($row['id'] ?? 0);
            $logsTableRows .= sprintf(
                '<tr><td style="padding:8px;border-top:1px solid #e5e7eb;"><input type="checkbox" name="selected_log_ids[]" value="%d" form="smart-mailer-delivery-bulk-form"></td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td></tr>',
                $deliveryLogId,
                $this->escape((string) ($row['attempted_at'] ?? '')),
                $this->escape((string) ($row['provider_name'] ?? $row['provider_code'] ?? 'N/A')),
                $this->escape((string) ($row['profile_name'] ?? '')),
                $this->escape((string) ($row['recipient'] ?? '')),
                $this->escape((string) ($row['status'] ?? '')),
                $this->escape((string) ($row['attempt_no'] ?? '1')),
                $this->escape((string) ($row['failover_count'] ?? '0')),
                $this->escape((string) ($row['latency_ms'] ?? '-')),
                $this->escape((string) ($row['failure_reason'] ?? ''))
            );
        }
        if ($logsTableRows === '') {
            $logsTableRows = '<tr><td colspan="10" style="padding:10px;border-top:1px solid #e5e7eb;color:#6b7280;">No hay registros para los filtros aplicados.</td></tr>';
        }

        $retryTableRows = '';
        foreach ($retryRows as $retry) {
            $retryId = (int) ($retry['id'] ?? 0);
            $retryNowAction = $this->generateUrl('smart_mailer_admin_retry_now', ['id' => $retryId]);
            $retryLaterAction = $this->generateUrl('smart_mailer_admin_retry_later', ['id' => $retryId]);
            $discardAction = $this->generateUrl('smart_mailer_admin_retry_discard', ['id' => $retryId]);
            $retryTableRows .= sprintf(
                '<tr><td style="padding:8px;border-top:1px solid #e5e7eb;"><input type="checkbox" name="selected_ids[]" value="%d" form="smart-mailer-retry-bulk-form"></td><td style="padding:8px;border-top:1px solid #e5e7eb;">#%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%d</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;white-space:nowrap;"><form method="post" action="%s" style="display:inline;"><input type="hidden" name="_token" value="%s"><button type="submit" style="border:0;background:#2563eb;color:#fff;padding:5px 8px;border-radius:6px;cursor:pointer;">Retry now</button></form> <form method="post" action="%s" style="display:inline;"><input type="hidden" name="_token" value="%s"><button type="submit" style="margin-left:6px;border:0;background:#b45309;color:#fff;padding:5px 8px;border-radius:6px;cursor:pointer;">Retry +15m</button></form> <form method="post" action="%s" style="display:inline;" onsubmit="return confirm(\'Eliminar de la retry queue?\');"><input type="hidden" name="_token" value="%s"><button type="submit" style="margin-left:6px;border:0;background:#dc2626;color:#fff;padding:5px 8px;border-radius:6px;cursor:pointer;">Discard</button></form></td></tr>',
                $retryId,
                $this->escape((string) ($retry['id'] ?? '')),
                $this->escape((string) ($retry['message_id'] ?? '')),
                (int) ($retry['attempt'] ?? 0),
                $this->escape((string) ($retry['next_attempt_at'] ?? '')),
                $this->escape((string) ($retry['status'] ?? '')),
                $this->escape((string) ($retry['last_error'] ?? '')),
                $this->escape($retryNowAction),
                $this->escape($this->csrfToken(self::RETRY_NOW_TOKEN_PREFIX.$retryId)),
                $this->escape($retryLaterAction),
                $this->escape($this->csrfToken(self::RETRY_LATER_TOKEN_PREFIX.$retryId)),
                $this->escape($discardAction),
                $this->escape($this->csrfToken(self::RETRY_DISCARD_TOKEN_PREFIX.$retryId))
            );
        }
        if ($retryTableRows === '') {
            $retryTableRows = '<tr><td colspan="8" style="padding:10px;border-top:1px solid #e5e7eb;color:#6b7280;">No hay entradas en retry queue.</td></tr>';
        }

        $filterForm = sprintf(
            '<form method="get" action="%s" style="display:grid;grid-template-columns:repeat(3,minmax(140px,1fr));gap:10px;margin:12px 0 16px;"><label style="display:flex;flex-direction:column;gap:4px;">Provider code<input type="text" name="provider" value="%s" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;">Status<input type="text" name="status" value="%s" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;">Recipient contains<input type="text" name="recipient" value="%s" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;">Domain contains<input type="text" name="domain" value="%s" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;">Profile contains<input type="text" name="profile" value="%s" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;">External message id<input type="text" name="external_message_id" value="%s" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><label style="display:flex;flex-direction:column;gap:4px;">Limit<input type="number" min="10" max="500" name="limit" value="%d" style="padding:8px;border:1px solid #d1d5db;border-radius:6px;"></label><div style="grid-column:1/-1;"><button type="submit" style="border:0;background:#111827;color:#fff;padding:8px 12px;border-radius:6px;cursor:pointer;">Aplicar filtros</button> <a href="%s" data-toggle="ajax" style="margin-left:10px;">Limpiar</a></div></form>',
            $this->escape($this->generateUrl('smart_mailer_admin_logs')),
            $this->escape($providerFilter),
            $this->escape($statusFilter),
            $this->escape($recipientFilter),
            $this->escape($domainFilter),
            $this->escape($profileFilter),
            $this->escape($externalMessageFilter),
            $limit,
            $this->escape($this->generateUrl('smart_mailer_admin_logs'))
        );

        $content = sprintf(
            '<h2 style="margin:0 0 12px;">Logs</h2><p style="margin:0 0 8px;color:#4b5563;">Trazas de delivery y cola de retry. Queue logs/eventos: <strong>%s</strong>. Dead-letter/retry: <strong>%s</strong>.</p>%s%s%s%s%s<div><h3 style="margin:0 0 8px;">Delivery logs</h3>%s<div style="overflow:auto;"><table style="width:100%%;border-collapse:collapse;font-size:13px;"><thead><tr style="text-align:left;background:#f9fafb;"><th style="padding:8px;">Sel.</th><th style="padding:8px;">Attempted at</th><th style="padding:8px;">Provider</th><th style="padding:8px;">Profile</th><th style="padding:8px;">Recipient</th><th style="padding:8px;">Status</th><th style="padding:8px;">Attempt</th><th style="padding:8px;">Failover</th><th style="padding:8px;">Latency ms</th><th style="padding:8px;">Failure reason</th></tr></thead><tbody>%s</tbody></table></div></div><div style="margin-top:18px;"><h3 style="margin:0 0 8px;">Retry queue</h3>%s<div style="overflow:auto;"><table style="width:100%%;border-collapse:collapse;font-size:13px;"><thead><tr style="text-align:left;background:#f9fafb;"><th style="padding:8px;">Sel.</th><th style="padding:8px;">ID</th><th style="padding:8px;">Message ID</th><th style="padding:8px;">Attempt</th><th style="padding:8px;">Next attempt</th><th style="padding:8px;">Status</th><th style="padding:8px;">Last error</th><th style="padding:8px;">Actions</th></tr></thead><tbody>%s</tbody></table></div></div>',
            $this->escape($logsQueue),
            $this->escape($retryQueue),
            $summaryHtml,
            $filterForm,
            $maintenanceButton,
            $processRetryButton,
            $deliveryBulkToolbar,
            '',
            $logsTableRows,
            $retryBulkToolbar,
            $retryTableRows
        );

        return $this->renderAdminPage($request, 'logs', 'Logs', $content, $notice);
    }

    #[Route(path: '/admin/smart-mailer/logs/maintenance/sweep', name: 'smart_mailer_admin_logs_maintenance_sweep', methods: ['POST'])]
    public function maintenanceSweep(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(self::MAINTENANCE_SWEEP_TOKEN_ID, (string) $request->request->get('_token', ''))) {
            return $this->redirectWithNotice('csrf_error');
        }

        try {
            $this->db();
            $service = $this->container->get(QueueMaintenanceService::class);
            if (!$service instanceof QueueMaintenanceService) {
                return $this->redirectWithNotice('db_error');
            }

            $service->sweep();
        } catch (Throwable) {
            return $this->redirectWithNotice('db_error');
        }

        return $this->redirectWithNotice('maintenance_swept');
    }

    #[Route(path: '/admin/smart-mailer/logs/retry/process', name: 'smart_mailer_admin_logs_process_retries', methods: ['POST'])]
    public function processRetries(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(self::PROCESS_RETRY_TOKEN_ID, (string) $request->request->get('_token', ''))) {
            return $this->redirectWithNotice('csrf_error');
        }

        try {
            $this->db();
            $service = $this->container->get(RetryQueueService::class);
            if (!$service instanceof RetryQueueService) {
                return $this->redirectWithNotice('db_error');
            }

            $service->processDue(100);
        } catch (Throwable) {
            return $this->redirectWithNotice('db_error');
        }

        return $this->redirectWithNotice('retry_queue_processed');
    }

    #[Route(path: '/admin/smart-mailer/logs/retry/{id}/now', name: 'smart_mailer_admin_retry_now', methods: ['POST'])]
    public function retryNow(Request $request, int $id): RedirectResponse
    {
        return $this->handleRetryMutation(
            request: $request,
            id: $id,
            tokenPrefix: self::RETRY_NOW_TOKEN_PREFIX,
            updater: function (Connection $connection, int $retryId, array $existing): void {
                $attempt = (int) ($existing['attempt'] ?? 0);
                $connection->update('smr_retry_queue', [
                    'attempt' => $attempt + 1,
                    'status' => 'pending',
                    'next_attempt_at' => gmdate('Y-m-d H:i:s'),
                    'last_error' => null,
                ], ['id' => $retryId]);
            }
        );
    }

    #[Route(path: '/admin/smart-mailer/logs/retry/{id}/later', name: 'smart_mailer_admin_retry_later', methods: ['POST'])]
    public function retryLater(Request $request, int $id): RedirectResponse
    {
        return $this->handleRetryMutation(
            request: $request,
            id: $id,
            tokenPrefix: self::RETRY_LATER_TOKEN_PREFIX,
            updater: function (Connection $connection, int $retryId, array $existing): void {
                $connection->update('smr_retry_queue', [
                    'status' => 'pending',
                    'next_attempt_at' => gmdate('Y-m-d H:i:s', time() + 900),
                    'last_error' => null,
                ], ['id' => $retryId]);
            }
        );
    }

    #[Route(path: '/admin/smart-mailer/logs/retry/{id}/discard', name: 'smart_mailer_admin_retry_discard', methods: ['POST'])]
    public function discardRetry(Request $request, int $id): RedirectResponse
    {
        return $this->handleRetryMutation(
            request: $request,
            id: $id,
            tokenPrefix: self::RETRY_DISCARD_TOKEN_PREFIX,
            updater: function (Connection $connection, int $retryId, array $existing): void {
                $connection->delete('smr_retry_queue', ['id' => $retryId]);
            }
        );
    }

    #[Route(path: '/admin/smart-mailer/logs/retry/bulk/{action}', name: 'smart_mailer_admin_retry_bulk_action', methods: ['POST'])]
    public function bulkRetry(Request $request, string $action): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(self::RETRY_BULK_TOKEN_ID, (string) $request->request->get('_token', ''))) {
            return $this->redirectWithNotice('csrf_error');
        }

        if (!in_array($action, ['now', 'later', 'discard'], true)) {
            return $this->redirectWithNotice('db_error');
        }

        $selectedIds = $request->request->all('selected_ids');
        if (!is_array($selectedIds) || $selectedIds === []) {
            return $this->redirectWithNotice('retry_bulk_empty');
        }

        $selectedIds = array_values(array_unique(array_filter(array_map(static fn (mixed $value): int => max(0, (int) $value), $selectedIds))));
        if ($selectedIds === []) {
            return $this->redirectWithNotice('retry_bulk_empty');
        }

        try {
            $this->db();
            $service = $this->container->get(RetryQueueBulkActionService::class);
            if (!$service instanceof RetryQueueBulkActionService) {
                return $this->redirectWithNotice('db_error');
            }

            $service->apply($selectedIds, $action);
        } catch (Throwable) {
            return $this->redirectWithNotice('db_error');
        }

        return $this->redirectWithNotice(match ($action) {
            'discard' => 'retry_bulk_discarded',
            'later' => 'retry_bulk_later',
            default => 'retry_bulk_now',
        });
    }

    #[Route(path: '/admin/smart-mailer/logs/delivery/bulk/{action}', name: 'smart_mailer_admin_delivery_bulk_action', methods: ['POST'])]
    public function bulkDelivery(Request $request, string $action): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(self::DELIVERY_BULK_TOKEN_ID, (string) $request->request->get('_token', ''))) {
            return $this->redirectWithNotice('csrf_error');
        }

        if (!in_array($action, ['archive', 'purge'], true)) {
            return $this->redirectWithNotice('delivery_bulk_invalid');
        }

        $selectedIds = $request->request->all('selected_log_ids');
        if (!is_array($selectedIds) || $selectedIds === []) {
            return $this->redirectWithNotice('delivery_bulk_empty');
        }

        $selectedIds = array_values(array_unique(array_filter(array_map(static fn (mixed $value): int => max(0, (int) $value), $selectedIds))));
        if ($selectedIds === []) {
            return $this->redirectWithNotice('delivery_bulk_empty');
        }

        try {
            $this->db();
            $service = $this->container->get(DeliveryLogBulkActionService::class);
            if (!$service instanceof DeliveryLogBulkActionService) {
                return $this->redirectWithNotice('db_error');
            }

            if ($action === 'archive') {
                $service->archive($selectedIds);
            } else {
                $service->purge($selectedIds);
            }
        } catch (Throwable) {
            return $this->redirectWithNotice('db_error');
        }

        return $this->redirectWithNotice($action === 'archive' ? 'delivery_bulk_archived' : 'delivery_bulk_purged');
    }

    /**
     * @param callable(Connection, int, array<string, mixed>): void $updater
     */
    private function handleRetryMutation(Request $request, int $id, string $tokenPrefix, callable $updater): RedirectResponse
    {
        if (!$this->isCsrfTokenValid($tokenPrefix.$id, (string) $request->request->get('_token', ''))) {
            return $this->redirectWithNotice('csrf_error');
        }

        $connection = $this->db();
        try {
            $existing = $connection->fetchAssociative(
                'SELECT id FROM smr_retry_queue WHERE id = :id',
                ['id' => $id]
            );
            if (!is_array($existing)) {
                return $this->redirectWithNotice('retry_not_found');
            }

            $updater($connection, $id, $existing);
        } catch (Throwable) {
            return $this->redirectWithNotice('db_error');
        }

        return $this->redirectWithNotice('retry_updated');
    }

    private function resolveNotice(string $notice): ?string
    {
        return match ($notice) {
            'retry_updated' => 'Retry queue actualizada correctamente.',
            'retry_not_found' => 'No se encontró la entrada de retry solicitada.',
            'retry_bulk_empty' => 'Seleccioná al menos una fila para aplicar una acción masiva.',
            'retry_bulk_now' => 'Acción masiva aplicada: retries reprogramados.',
            'retry_bulk_later' => 'Acción masiva aplicada: retries pospuestos 15 minutos.',
            'retry_bulk_discarded' => 'Acción masiva aplicada: retries descartados.',
            'delivery_bulk_empty' => 'Seleccioná al menos una fila de delivery logs para aplicar una acción masiva.',
            'delivery_bulk_invalid' => 'Acción masiva de delivery logs inválida.',
            'delivery_bulk_archived' => 'Delivery logs archivados correctamente.',
            'delivery_bulk_purged' => 'Delivery logs eliminados correctamente.',
            'maintenance_swept' => 'Sweep de mantenimiento ejecutado correctamente.',
            'retry_queue_processed' => 'Retry queue procesada correctamente.',
            'csrf_error' => 'Token de seguridad inválido. Recargá la página e intentá de nuevo.',
            'db_error' => 'Error de base de datos. Revisá migraciones/conexión y volvé a intentar.',
            default => null,
        };
    }

    private function redirectWithNotice(string $notice): RedirectResponse
    {
        return $this->redirect($this->generateUrl('smart_mailer_admin_logs', ['notice' => $notice]));
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
}
