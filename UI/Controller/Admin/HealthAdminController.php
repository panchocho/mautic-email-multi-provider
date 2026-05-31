<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin;

use MauticPlugin\SmartMailerRouterBundle\Config\Health\HealthScoreCalculator;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Messenger\Message\RecomputeHealthCommand;
use Throwable;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class HealthAdminController extends AbstractAdminController
{
    private const RECOMPUTE_TOKEN_PREFIX = 'smart_mailer_health_recompute_';
    private const QUARANTINE_TOKEN_PREFIX = 'smart_mailer_health_quarantine_';
    private const UNQUARANTINE_TOKEN_PREFIX = 'smart_mailer_health_unquarantine_';

    #[Route(path: '/admin/smart-mailer/health', name: 'smart_mailer_admin_health', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $connection = $this->db();

        $healthCalculator = null;
        if ($this->container->has(HealthScoreCalculator::class)) {
            $candidate = $this->container->get(HealthScoreCalculator::class);
            if ($candidate instanceof HealthScoreCalculator) {
                $healthCalculator = $candidate;
            }
        }

        try {
            /** @var list<array<string, mixed>> $providers */
            $providers = $connection->fetchAllAssociative(
                'SELECT id, name, code, enabled, health_score, reputation, quarantined_until, updated_at
                 FROM smr_provider
                 ORDER BY enabled DESC, health_score DESC, reputation DESC, name ASC'
            );

            /** @var list<array<string, mixed>> $history */
            $history = $connection->fetchAllAssociative(
                'SELECT h.id, h.provider_id, h.checked_at, h.score, h.status, h.message, h.details, p.name AS provider_name, p.code AS provider_code
                 FROM smr_provider_health_history h
                 LEFT JOIN smr_provider p ON p.id = h.provider_id
                 ORDER BY h.checked_at DESC
                 LIMIT 200'
            );

            /** @var list<array<string, mixed>> $metrics24h */
            $metrics24h = $connection->fetchAllAssociative(
                'SELECT b.provider_id, p.name AS provider_name, p.code AS provider_code,
                        SUM(b.sent_count) AS sent_count,
                        SUM(b.delivered_count) AS delivered_count,
                        SUM(b.bounced_count) AS bounced_count,
                        SUM(b.complaint_count) AS complaint_count,
                        AVG(b.avg_latency_ms) AS avg_latency_ms
                 FROM smr_provider_metric_bucket b
                 LEFT JOIN smr_provider p ON p.id = b.provider_id
                 WHERE b.bucket_start >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)
                 GROUP BY b.provider_id, p.name, p.code
                 ORDER BY sent_count DESC'
            );
        } catch (Throwable $exception) {
            $content = sprintf(
                '<h2 style="margin:0 0 10px;">Health</h2><div class="alert alert-danger">No se pudieron cargar métricas de health. %s</div>',
                $this->escape($exception->getMessage())
            );

            return $this->renderAdminPage($request, 'health', 'Health', $content);
        }

        $notice = $this->resolveNotice((string) $request->query->get('status', ''));

        $totalProviders = count($providers);
        $enabledProviders = 0;
        $healthyProviders = 0;
        $sumHealthScore = 0.0;
        foreach ($providers as $providerRow) {
            $enabled = (int) ($providerRow['enabled'] ?? 0) === 1;
            $score = (int) ($providerRow['health_score'] ?? 0);
            $sumHealthScore += $score;
            if ($enabled) {
                ++$enabledProviders;
            }
            if ($score >= 80) {
                ++$healthyProviders;
            }
        }
        $avgHealth = $totalProviders > 0 ? round($sumHealthScore / $totalProviders, 2) : 0.0;

        $totalSent = 0;
        $totalDelivered = 0;
        $totalBounced = 0;
        $totalComplaints = 0;
        $sumLatency = 0.0;
        $latencyBuckets = 0;

        foreach ($metrics24h as $metricRow) {
            $totalSent += (int) ($metricRow['sent_count'] ?? 0);
            $totalDelivered += (int) ($metricRow['delivered_count'] ?? 0);
            $totalBounced += (int) ($metricRow['bounced_count'] ?? 0);
            $totalComplaints += (int) ($metricRow['complaint_count'] ?? 0);
            $avgLatencyRaw = $metricRow['avg_latency_ms'] ?? null;
            if ($avgLatencyRaw !== null && is_numeric($avgLatencyRaw)) {
                $sumLatency += (float) $avgLatencyRaw;
                ++$latencyBuckets;
            }
        }

        $successRate = $totalSent > 0 ? $totalDelivered / $totalSent : 0.0;
        $bounceRate = $totalSent > 0 ? $totalBounced / $totalSent : 0.0;
        $complaintRate = $totalSent > 0 ? $totalComplaints / $totalSent : 0.0;
        $avgLatency = $latencyBuckets > 0 ? round($sumLatency / $latencyBuckets, 2) : 0.0;
        $syntheticScore = $healthCalculator?->calculate($successRate, $avgLatency, $bounceRate) ?? 0;

        $providerRows = '';
        $nowTs = time();
        foreach ($providers as $provider) {
            $providerId = (string) ($provider['id'] ?? '');
            $providerCode = (string) ($provider['code'] ?? '');
            $quarantinedUntil = (string) ($provider['quarantined_until'] ?? '');
            $isQuarantined = $quarantinedUntil !== '' && strtotime($quarantinedUntil) !== false && strtotime($quarantinedUntil) > $nowTs;
            $recomputeAction = $this->generateUrl('smart_mailer_admin_health_recompute_provider', ['id' => $providerId]);
            $quarantineAction = $this->generateUrl('smart_mailer_admin_health_quarantine_provider', ['id' => $providerId]);
            $unquarantineAction = $this->generateUrl('smart_mailer_admin_health_unquarantine_provider', ['id' => $providerId]);

            $providerRows .= sprintf(
                '<tr><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%d</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%.2f</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;white-space:nowrap;"><form method="post" action="%s" style="display:inline;"><input type="hidden" name="_token" value="%s"><button type="submit" style="border:0;background:#2563eb;color:#fff;padding:5px 8px;border-radius:6px;cursor:pointer;">Recompute</button></form> <form method="post" action="%s" style="display:inline;"><input type="hidden" name="_token" value="%s"><input type="hidden" name="hours" value="24"><button type="submit" style="margin-left:6px;border:0;background:#b45309;color:#fff;padding:5px 8px;border-radius:6px;cursor:pointer;">Quarantine 24h</button></form> <form method="post" action="%s" style="display:inline;"><input type="hidden" name="_token" value="%s"><button type="submit" style="margin-left:6px;border:0;background:#16a34a;color:#fff;padding:5px 8px;border-radius:6px;cursor:pointer;">Unquarantine</button></form></td></tr>',
                $this->escape((string) ($provider['name'] ?? '')),
                $this->escape($providerCode),
                (int) ($provider['enabled'] ?? 0) === 1 ? 'Sí' : 'No',
                (int) ($provider['health_score'] ?? 0),
                (float) ($provider['reputation'] ?? 0.0),
                $this->escape((string) ($provider['updated_at'] ?? '')),
                $this->escape($isQuarantined ? ('Sí (hasta '.$quarantinedUntil.')') : 'No'),
                $this->escape($recomputeAction),
                $this->escape($this->csrfToken(self::RECOMPUTE_TOKEN_PREFIX.$providerId)),
                $this->escape($quarantineAction),
                $this->escape($this->csrfToken(self::QUARANTINE_TOKEN_PREFIX.$providerId)),
                $this->escape($unquarantineAction),
                $this->escape($this->csrfToken(self::UNQUARANTINE_TOKEN_PREFIX.$providerId))
            );
        }
        if ($providerRows === '') {
            $providerRows = '<tr><td colspan="8" style="padding:10px;border-top:1px solid #e5e7eb;color:#6b7280;">No hay providers para calcular health.</td></tr>';
        }

        $metricsRows = '';
        foreach ($metrics24h as $metric) {
            $sent = (int) ($metric['sent_count'] ?? 0);
            $delivered = (int) ($metric['delivered_count'] ?? 0);
            $bounced = (int) ($metric['bounced_count'] ?? 0);
            $complaints = (int) ($metric['complaint_count'] ?? 0);
            $successPct = $sent > 0 ? round(($delivered / $sent) * 100, 2) : 0.0;
            $bouncePct = $sent > 0 ? round(($bounced / $sent) * 100, 2) : 0.0;
            $complaintPct = $sent > 0 ? round(($complaints / $sent) * 100, 3) : 0.0;
            $metricsRows .= sprintf(
                '<tr><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%d</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%d (%.2f%%)</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%d (%.2f%%)</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%d (%.3f%%)</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td></tr>',
                $this->escape((string) ($metric['provider_name'] ?? $metric['provider_code'] ?? 'unknown')),
                $sent,
                $delivered,
                $successPct,
                $bounced,
                $bouncePct,
                $complaints,
                $complaintPct,
                $this->escape((string) ($metric['avg_latency_ms'] ?? '-'))
            );
        }
        if ($metricsRows === '') {
            $metricsRows = '<tr><td colspan="6" style="padding:10px;border-top:1px solid #e5e7eb;color:#6b7280;">Sin métricas en las últimas 24h.</td></tr>';
        }

        $historyRows = '';
        foreach (array_slice($history, 0, 50) as $item) {
            $historyRows .= sprintf(
                '<tr><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%d</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td></tr>',
                $this->escape((string) ($item['provider_name'] ?? $item['provider_code'] ?? 'unknown')),
                (int) ($item['score'] ?? 0),
                $this->escape((string) ($item['status'] ?? 'unknown')),
                $this->escape((string) ($item['message'] ?? '')),
                $this->escape((string) ($item['checked_at'] ?? ''))
            );
        }
        if ($historyRows === '') {
            $historyRows = '<tr><td colspan="5" style="padding:10px;border-top:1px solid #e5e7eb;color:#6b7280;">Sin historial de health.</td></tr>';
        }

        $content = sprintf(
            '<h2 style="margin:0 0 12px;">Health</h2><p style="margin:0 0 16px;color:#4b5563;">Estado operativo de deliverability por provider y tendencias de las últimas 24h.</p><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:16px;"><div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:12px;"><div style="font-size:13px;color:#6b7280;">Providers saludables</div><div style="font-size:26px;font-weight:700;">%d / %d</div><div style="font-size:12px;color:#6b7280;">Promedio score: %.2f</div></div><div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:12px;"><div style="font-size:13px;color:#6b7280;">Deliveries (24h)</div><div style="font-size:26px;font-weight:700;">%d</div><div style="font-size:12px;color:#6b7280;">Success rate: %.2f%%</div></div><div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:12px;"><div style="font-size:13px;color:#6b7280;">Bounce / Complaint</div><div style="font-size:26px;font-weight:700;">%.2f%% / %.3f%%</div><div style="font-size:12px;color:#6b7280;">Latencia media: %.2f ms</div></div><div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:12px;"><div style="font-size:13px;color:#6b7280;">Synthetic score</div><div style="font-size:26px;font-weight:700;">%d</div><div style="font-size:12px;color:#6b7280;">Calculator basado en 24h</div></div></div><div style="display:grid;grid-template-columns:1fr;gap:16px;"><div><h3 style="margin:0 0 8px;">Providers</h3><div style="overflow:auto;"><table style="width:100%%;border-collapse:collapse;font-size:13px;"><thead><tr style="text-align:left;background:#f9fafb;"><th style="padding:8px;">Name</th><th style="padding:8px;">Code</th><th style="padding:8px;">Activo</th><th style="padding:8px;">Health score</th><th style="padding:8px;">Reputation</th><th style="padding:8px;">Updated</th><th style="padding:8px;">Quarantine</th><th style="padding:8px;">Acciones</th></tr></thead><tbody>%s</tbody></table></div></div><div><h3 style="margin:0 0 8px;">Métricas 24h</h3><div style="overflow:auto;"><table style="width:100%%;border-collapse:collapse;font-size:13px;"><thead><tr style="text-align:left;background:#f9fafb;"><th style="padding:8px;">Provider</th><th style="padding:8px;">Sent</th><th style="padding:8px;">Delivered</th><th style="padding:8px;">Bounced</th><th style="padding:8px;">Complaints</th><th style="padding:8px;">Avg latency ms</th></tr></thead><tbody>%s</tbody></table></div></div><div><h3 style="margin:0 0 8px;">Historial reciente</h3><div style="overflow:auto;"><table style="width:100%%;border-collapse:collapse;font-size:13px;"><thead><tr style="text-align:left;background:#f9fafb;"><th style="padding:8px;">Provider</th><th style="padding:8px;">Score</th><th style="padding:8px;">Status</th><th style="padding:8px;">Message</th><th style="padding:8px;">Checked at</th></tr></thead><tbody>%s</tbody></table></div></div></div>',
            $healthyProviders,
            $enabledProviders,
            $avgHealth,
            $totalSent,
            round($successRate * 100, 2),
            round($bounceRate * 100, 2),
            round($complaintRate * 100, 3),
            $avgLatency,
            $syntheticScore,
            $providerRows,
            $metricsRows,
            $historyRows
        );

        return $this->renderAdminPage($request, 'health', 'Health', $content, $notice);
    }

    #[Route(path: '/admin/smart-mailer/health/providers/{id}/recompute', name: 'smart_mailer_admin_health_recompute_provider', methods: ['POST'])]
    public function recomputeProvider(Request $request, string $id): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(self::RECOMPUTE_TOKEN_PREFIX.$id, (string) $request->request->get('_token', ''))) {
            return $this->redirectWithStatus('csrf_error');
        }

        $connection = $this->db();
        try {
            $provider = $connection->fetchAssociative(
                'SELECT id, name, code, health_score FROM smr_provider WHERE id = :id',
                ['id' => $id]
            );
            if (!is_array($provider)) {
                return $this->redirectWithStatus('provider_not_found');
            }

            $stats = $connection->fetchAssociative(
                'SELECT
                    COALESCE(SUM(sent_count), 0) AS sent_count,
                    COALESCE(SUM(delivered_count), 0) AS delivered_count,
                    COALESCE(SUM(bounced_count), 0) AS bounced_count,
                    COALESCE(SUM(complaint_count), 0) AS complaint_count,
                    COALESCE(AVG(avg_latency_ms), 0) AS avg_latency_ms
                 FROM smr_provider_metric_bucket
                 WHERE provider_id = :provider_id
                   AND bucket_start >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)',
                ['provider_id' => $id]
            ) ?: [];

            $sent = (int) ($stats['sent_count'] ?? 0);
            if ($sent <= 0) {
                $this->insertHealthHistory(
                    providerId: $id,
                    score: (int) ($provider['health_score'] ?? 0),
                    status: 'no_data',
                    message: 'Manual recompute: no hay métricas en las últimas 24h.',
                    details: ['manual' => true, 'window' => '24h', 'sent_count' => 0]
                );

                return $this->redirectWithStatus('recomputed_no_data');
            }

            $delivered = (int) ($stats['delivered_count'] ?? 0);
            $bounced = (int) ($stats['bounced_count'] ?? 0);
            $complaints = (int) ($stats['complaint_count'] ?? 0);
            $latency = (float) ($stats['avg_latency_ms'] ?? 0.0);

            $successRate = $delivered / max(1, $sent);
            $bounceRate = $bounced / max(1, $sent);
            $complaintRate = $complaints / max(1, $sent);

            $calculator = $this->resolveHealthCalculator();
            $score = $calculator?->calculate($successRate, $latency, $bounceRate) ?? (int) ($provider['health_score'] ?? 0);
            $status = $this->classifyHealthStatus($score);

            $connection->update('smr_provider', [
                'health_score' => $score,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ], ['id' => $id]);

            $this->insertHealthHistory(
                providerId: $id,
                score: $score,
                status: $status,
                message: 'Manual recompute desde métricas 24h.',
                details: [
                    'manual' => true,
                    'window' => '24h',
                    'sent_count' => $sent,
                    'delivered_count' => $delivered,
                    'bounced_count' => $bounced,
                    'complaint_count' => $complaints,
                    'success_rate' => $successRate,
                    'bounce_rate' => $bounceRate,
                    'complaint_rate' => $complaintRate,
                    'avg_latency_ms' => $latency,
                ]
            );

            $this->dispatchRecompute((string) ($provider['code'] ?? ''));
        } catch (Throwable) {
            return $this->redirectWithStatus('db_error');
        }

        return $this->redirectWithStatus('recomputed');
    }

    #[Route(path: '/admin/smart-mailer/health/providers/{id}/quarantine', name: 'smart_mailer_admin_health_quarantine_provider', methods: ['POST'])]
    public function quarantineProvider(Request $request, string $id): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(self::QUARANTINE_TOKEN_PREFIX.$id, (string) $request->request->get('_token', ''))) {
            return $this->redirectWithStatus('csrf_error');
        }

        $hours = filter_var($request->request->get('hours', 24), FILTER_VALIDATE_INT);
        if ($hours === false) {
            $hours = 24;
        }
        $hours = max(1, min(720, $hours));

        $connection = $this->db();
        try {
            $provider = $connection->fetchAssociative(
                'SELECT id, health_score FROM smr_provider WHERE id = :id',
                ['id' => $id]
            );
            if (!is_array($provider)) {
                return $this->redirectWithStatus('provider_not_found');
            }

            $untilTs = time() + ($hours * 3600);
            $until = gmdate('Y-m-d H:i:s', $untilTs);
            $connection->update('smr_provider', [
                'quarantined_until' => $until,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ], ['id' => $id]);

            $this->insertHealthHistory(
                providerId: $id,
                score: (int) ($provider['health_score'] ?? 0),
                status: 'quarantined',
                message: sprintf('Provider en quarantine manual por %d horas.', $hours),
                details: ['manual' => true, 'quarantined_until' => $until, 'hours' => $hours]
            );
        } catch (Throwable) {
            return $this->redirectWithStatus('db_error');
        }

        return $this->redirectWithStatus('quarantined');
    }

    #[Route(path: '/admin/smart-mailer/health/providers/{id}/unquarantine', name: 'smart_mailer_admin_health_unquarantine_provider', methods: ['POST'])]
    public function unquarantineProvider(Request $request, string $id): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(self::UNQUARANTINE_TOKEN_PREFIX.$id, (string) $request->request->get('_token', ''))) {
            return $this->redirectWithStatus('csrf_error');
        }

        $connection = $this->db();
        try {
            $provider = $connection->fetchAssociative(
                'SELECT id, health_score FROM smr_provider WHERE id = :id',
                ['id' => $id]
            );
            if (!is_array($provider)) {
                return $this->redirectWithStatus('provider_not_found');
            }

            $connection->update('smr_provider', [
                'quarantined_until' => null,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ], ['id' => $id]);

            $this->insertHealthHistory(
                providerId: $id,
                score: (int) ($provider['health_score'] ?? 0),
                status: 'restored',
                message: 'Provider removido de quarantine manual.',
                details: ['manual' => true]
            );
        } catch (Throwable) {
            return $this->redirectWithStatus('db_error');
        }

        return $this->redirectWithStatus('unquarantined');
    }

    private function redirectWithStatus(string $status): RedirectResponse
    {
        return $this->redirect($this->generateUrl('smart_mailer_admin_health', ['status' => $status]));
    }

    private function resolveNotice(string $status): ?string
    {
        return match ($status) {
            'recomputed' => 'Health recomputado correctamente.',
            'recomputed_no_data' => 'No había métricas de 24h para recomputar; se registró evento en historial.',
            'quarantined' => 'Provider puesto en quarantine manual.',
            'unquarantined' => 'Provider removido de quarantine manual.',
            'provider_not_found' => 'Provider no encontrado.',
            'csrf_error' => 'Token de seguridad inválido. Recargá la página e intentá de nuevo.',
            'db_error' => 'Error de base de datos. Revisá migraciones/conexión y volvé a intentar.',
            default => null,
        };
    }

    private function classifyHealthStatus(int $score): string
    {
        if ($score >= 80) {
            return 'healthy';
        }

        if ($score >= 60) {
            return 'watch';
        }

        if ($score >= 40) {
            return 'degraded';
        }

        return 'critical';
    }

    private function resolveHealthCalculator(): ?HealthScoreCalculator
    {
        if (!$this->container->has(HealthScoreCalculator::class)) {
            return null;
        }

        $service = $this->container->get(HealthScoreCalculator::class);

        return $service instanceof HealthScoreCalculator ? $service : null;
    }

    /**
     * @param array<string, mixed> $details
     */
    private function insertHealthHistory(string $providerId, int $score, string $status, string $message, array $details = []): void
    {
        $connection = $this->db();
        $detailsJson = json_encode($details, JSON_UNESCAPED_SLASHES);

        $connection->insert('smr_provider_health_history', [
            'provider_id' => $providerId,
            'checked_at' => gmdate('Y-m-d H:i:s'),
            'score' => max(0, min(100, $score)),
            'status' => mb_substr($status, 0, 32),
            'message' => $message,
            'details' => is_string($detailsJson) ? $detailsJson : '{}',
        ]);
    }

    private function dispatchRecompute(string $providerCode): void
    {
        $providerCode = trim($providerCode);
        if ($providerCode === '' || !$this->container->has('messenger.default_bus')) {
            return;
        }

        $bus = $this->container->get('messenger.default_bus');
        if ($bus instanceof MessageBusInterface) {
            $bus->dispatch(new RecomputeHealthCommand($providerCode));
        }
    }
}
