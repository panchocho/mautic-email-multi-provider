<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin;

use Throwable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardAdminController extends AbstractAdminController
{
    #[Route(path: '/admin/smart-mailer', name: 'smart_mailer_admin_dashboard', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $connection = $this->db();

        try {
            $totalProviders = (int) $connection->fetchOne('SELECT COUNT(*) FROM smr_provider');
            $enabledProviders = (int) $connection->fetchOne('SELECT COUNT(*) FROM smr_provider WHERE enabled = 1');
            $totalBindings = (int) $connection->fetchOne('SELECT COUNT(*) FROM smr_provider_domain_binding');
            $activeBindings = (int) $connection->fetchOne('SELECT COUNT(*) FROM smr_provider_domain_binding WHERE active = 1');
            $totalProfiles = (int) $connection->fetchOne('SELECT COUNT(*) FROM smr_routing_profile');
            $enabledProfiles = (int) $connection->fetchOne('SELECT COUNT(*) FROM smr_routing_profile WHERE enabled = 1');
            $totalRules = (int) $connection->fetchOne('SELECT COUNT(*) FROM smr_routing_rule');
            $enabledRules = (int) $connection->fetchOne('SELECT COUNT(*) FROM smr_routing_rule WHERE enabled = 1');
            $delivery24h = (int) $connection->fetchOne('SELECT COUNT(*) FROM smr_delivery_log WHERE attempted_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)');
            $retryPending = (int) $connection->fetchOne('SELECT COUNT(*) FROM smr_retry_queue WHERE status IN (\'pending\', \'retrying\', \'processing\')');
            $warmupSchedules = (int) $connection->fetchOne('SELECT COUNT(*) FROM smr_warmup_schedule WHERE active = 1');
            $warmupStates = (int) $connection->fetchOne('SELECT COUNT(*) FROM smr_warmup_state');
        } catch (Throwable) {
            $totalProviders = 0;
            $enabledProviders = 0;
            $totalBindings = 0;
            $activeBindings = 0;
            $totalProfiles = 0;
            $enabledProfiles = 0;
            $totalRules = 0;
            $enabledRules = 0;
            $delivery24h = 0;
            $retryPending = 0;
            $warmupSchedules = 0;
            $warmupStates = 0;
        }

        $content = sprintf(
            '<h2 style="margin:0 0 10px;">Panel principal</h2><p style="margin:0 0 18px;color:#4b5563;">Resumen operativo del router multi-provider.</p><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:18px;"><div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:12px;"><div style="font-size:13px;color:#6b7280;">Proveedores</div><div style="font-size:26px;font-weight:700;">%d</div><div style="font-size:12px;color:#6b7280;">Habilitados: %d</div></div><div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:12px;"><div style="font-size:13px;color:#6b7280;">Domain bindings</div><div style="font-size:26px;font-weight:700;">%d</div><div style="font-size:12px;color:#6b7280;">Activos: %d</div></div><div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:12px;"><div style="font-size:13px;color:#6b7280;">Profiles</div><div style="font-size:26px;font-weight:700;">%d</div><div style="font-size:12px;color:#6b7280;">Habilitados: %d</div></div><div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:12px;"><div style="font-size:13px;color:#6b7280;">Rules</div><div style="font-size:26px;font-weight:700;">%d</div><div style="font-size:12px;color:#6b7280;">Activas: %d</div></div><div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:12px;"><div style="font-size:13px;color:#6b7280;">Delivery (24h)</div><div style="font-size:26px;font-weight:700;">%d</div><div style="font-size:12px;color:#6b7280;">Retry pendientes: %d</div></div><div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:12px;"><div style="font-size:13px;color:#6b7280;">Warmup</div><div style="font-size:26px;font-weight:700;">%d</div><div style="font-size:12px;color:#6b7280;">Estados: %d</div></div></div><p style="margin:0;">Accesos rápidos: <a href="%s">Proveedores</a>, <a href="%s">Bindings</a>, <a href="%s">Profiles</a>, <a href="%s">Rules</a>, <a href="%s">Settings</a>, <a href="%s">Health</a>, <a href="%s">Warmup</a>, <a href="%s">Logs</a>.</p>',
            $totalProviders,
            $enabledProviders,
            $totalBindings,
            $activeBindings,
            $totalProfiles,
            $enabledProfiles,
            $totalRules,
            $enabledRules,
            $delivery24h,
            $retryPending,
            $warmupSchedules,
            $warmupStates,
            $this->escape($this->generateUrl('smart_mailer_admin_providers')),
            $this->escape($this->generateUrl('smart_mailer_admin_bindings')),
            $this->escape($this->generateUrl('smart_mailer_admin_profiles')),
            $this->escape($this->generateUrl('smart_mailer_admin_rules')),
            $this->escape($this->generateUrl('smart_mailer_admin_settings')),
            $this->escape($this->generateUrl('smart_mailer_admin_health')),
            $this->escape($this->generateUrl('smart_mailer_admin_warmup')),
            $this->escape($this->generateUrl('smart_mailer_admin_logs'))
        );

        return $this->renderAdminPage($request, 'dashboard', 'Dashboard', $content);
    }
}
