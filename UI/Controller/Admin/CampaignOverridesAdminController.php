<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin;

use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\RoutingMode;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CampaignOverridesAdminController extends AbstractAdminController
{
    #[Route(path: '/admin/smart-mailer/campaign-overrides', name: 'smart_mailer_admin_campaign_overrides', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        try {
            $campaigns = $this->db()->fetchAllAssociative(
                'SELECT c.id, c.name, r.tracking_domain, r.routing_profile, r.routing_mode
                 FROM campaigns c LEFT JOIN smr_campaign_routing r ON r.campaign_id = c.id
                 ORDER BY c.id DESC LIMIT 200'
            );
            $profiles = $this->db()->fetchFirstColumn(
                'SELECT name FROM smr_routing_profile WHERE enabled = 1 ORDER BY name ASC'
            );
        } catch (\Throwable) {
            $campaigns = [];
            $profiles = [];
        }

        return $this->renderAdminPage(
            $request,
            'campaign-overrides',
            'Campaign delivery overrides',
            $this->content($campaigns, $profiles),
            $this->notice((string) $request->query->get('status', ''))
        );
    }

    #[Route(path: '/admin/smart-mailer/campaign-overrides/{campaignId}', name: 'smart_mailer_admin_campaign_overrides_save', methods: ['POST'])]
    public function save(Request $request, string $campaignId): RedirectResponse
    {
        $id = filter_var($campaignId, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0 || !$this->isCsrfTokenValid('smart_mailer_campaign_override_'.$campaignId, (string) $request->request->get('_token', ''))) {
            return $this->redirectWithStatus('validation_error');
        }

        $domain = $this->normalizeDomain((string) $request->request->get('tracking_domain', ''));
        if (trim((string) $request->request->get('tracking_domain', '')) !== '' && ($domain === null || !$this->resolvesDns($domain))) {
            return $this->redirectWithStatus('dns_error');
        }

        $profile = trim((string) $request->request->get('routing_profile', ''));
        $mode = trim((string) $request->request->get('routing_mode', ''));
        if ($mode !== '' && RoutingMode::tryFrom($mode) === null) {
            return $this->redirectWithStatus('validation_error');
        }

        try {
            if ((int) $this->db()->fetchOne('SELECT COUNT(*) FROM campaigns WHERE id = :id', ['id' => $id]) === 0) {
                return $this->redirectWithStatus('not_found');
            }
            if ($profile !== '' && (int) $this->db()->fetchOne('SELECT COUNT(*) FROM smr_routing_profile WHERE name = :name AND enabled = 1', ['name' => $profile]) === 0) {
                return $this->redirectWithStatus('validation_error');
            }

            $this->db()->executeStatement(
                'INSERT INTO smr_campaign_routing (campaign_id, tracking_domain, routing_profile, routing_mode, updated_at)
                 VALUES (:campaign_id, :tracking_domain, :routing_profile, :routing_mode, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE tracking_domain = VALUES(tracking_domain), routing_profile = VALUES(routing_profile),
                 routing_mode = VALUES(routing_mode), updated_at = VALUES(updated_at)',
                [
                    'campaign_id' => $id,
                    'tracking_domain' => $domain,
                    'routing_profile' => $profile === '' ? null : $profile,
                    'routing_mode' => $mode === '' ? null : $mode,
                ]
            );
        } catch (\Throwable) {
            return $this->redirectWithStatus('db_error');
        }

        return $this->redirectWithStatus('saved');
    }

    private function redirectWithStatus(string $status): RedirectResponse
    {
        return $this->redirect($this->generateUrl('smart_mailer_admin_campaign_overrides', ['status' => $status]));
    }

    private function notice(string $status): ?string
    {
        return match ($status) {
            'saved' => 'Campaign override saved.',
            'dns_error' => 'Tracking domain has no resolvable A, AAAA, or CNAME record.',
            'not_found' => 'Campaign not found.',
            'validation_error' => 'Check the tracking domain, profile, and routing mode.',
            'db_error' => 'Could not save the campaign override.',
            default => null,
        };
    }

    /**
     * @param list<array<string,mixed>> $campaigns
     * @param list<string> $profiles
     */
    private function content(array $campaigns, array $profiles): string
    {
        $rows = '';
        foreach ($campaigns as $campaign) {
            $id = (int) ($campaign['id'] ?? 0);
            $profileOptions = '<option value="">Inherit from email/default</option>';
            foreach ($profiles as $profile) {
                $selected = $profile === ($campaign['routing_profile'] ?? null) ? ' selected' : '';
                $profileOptions .= sprintf('<option value="%s"%s>%s</option>', $this->escape($profile), $selected, $this->escape($profile));
            }

            $rows .= sprintf(
                '<tr><td style="padding:12px;border-top:1px solid #e5e7eb;"><strong>%s</strong><br><small>Campaign #%d</small></td><td style="padding:12px;border-top:1px solid #e5e7eb;"><form method="post" action="%s" style="display:grid;grid-template-columns:1.2fr 1fr 1fr auto;gap:8px;min-width:650px"><input type="hidden" name="_token" value="%s"><input class="form-control" name="tracking_domain" value="%s" placeholder="trk.client.com"><select class="form-control" name="routing_profile">%s</select><select class="form-control" name="routing_mode">%s</select><button class="btn btn-primary" type="submit">Save</button></form></td></tr>',
                $this->escape((string) ($campaign['name'] ?? ('Campaign #'.$id))),
                $id,
                $this->escape($this->generateUrl('smart_mailer_admin_campaign_overrides_save', ['campaignId' => $id])),
                $this->escape($this->csrfToken('smart_mailer_campaign_override_'.$id)),
                $this->escape((string) ($campaign['tracking_domain'] ?? '')),
                $profileOptions,
                $this->modeOptions((string) ($campaign['routing_mode'] ?? ''))
            );
        }

        if ($rows === '') {
            $rows = '<tr><td style="padding:12px">No campaigns found.</td></tr>';
        }

        return sprintf(
            '<h2>Campaign delivery overrides</h2><div class="alert alert-info">These values override the email configuration only for sends triggered by this campaign. Leave a field empty to inherit. Tracking domains are checked through DNS before saving and must also be allowed by Multidomain.</div><div style="overflow:auto"><table style="width:100%%;border-collapse:collapse"><thead><tr style="text-align:left;background:#f9fafb"><th style="padding:12px">Campaign</th><th style="padding:12px">Override</th></tr></thead><tbody>%s</tbody></table></div>',
            $rows
        );
    }

    private function modeOptions(string $selected): string
    {
        $options = '<option value="">Inherit from profile/default</option>';
        foreach (RoutingMode::cases() as $mode) {
            $options .= sprintf('<option value="%s"%s>%s</option>', $this->escape($mode->value), $mode->value === $selected ? ' selected' : '', $this->escape(str_replace('_', ' ', $mode->value)));
        }

        return $options;
    }

    private function normalizeDomain(string $value): ?string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('#^https?://#', '', $value) ?? '';
        $value = preg_replace('#/.*$#', '', $value) ?? '';
        $value = trim($value, '.');

        return $value === '' ? null : $value;
    }

    private function resolvesDns(string $domain): bool
    {
        try {
            $records = dns_get_record($domain, DNS_A | DNS_AAAA | DNS_CNAME);
        } catch (\Throwable) {
            return false;
        }

        return is_array($records) && $records !== [];
    }
}
