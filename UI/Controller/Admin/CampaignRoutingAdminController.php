<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin;

use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\RoutingMode;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CampaignRoutingAdminController extends AbstractAdminController
{
    #[Route(path: '/admin/smart-mailer/campaign-routing', name: 'smart_mailer_admin_campaign_routing', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        try {
            $emails = $this->db()->fetchAllAssociative(
                'SELECT e.id, e.name, e.subject, e.from_address, r.tracking_domain, r.routing_profile, r.routing_mode
                 FROM emails e
                 LEFT JOIN smr_email_routing r ON r.email_id = e.id
                 ORDER BY e.id DESC LIMIT 200'
            );
            $profiles = $this->db()->fetchAllAssociative(
                'SELECT name FROM smr_routing_profile WHERE enabled = 1 ORDER BY name ASC'
            );
        } catch (\Throwable) {
            $emails = [];
            $profiles = [];
        }

        return $this->renderAdminPage(
            $request,
            'campaign-routing',
            'Routing por email',
            $this->renderContent($emails, $profiles),
            $this->notice((string) $request->query->get('status', ''))
        );
    }

    #[Route(path: '/admin/smart-mailer/campaign-routing/{emailId}', name: 'smart_mailer_admin_campaign_routing_save', methods: ['POST'])]
    public function save(Request $request, string $emailId): RedirectResponse
    {
        $id = filter_var($emailId, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0 || !$this->isCsrfTokenValid('smart_mailer_email_routing_'.$emailId, (string) $request->request->get('_token', ''))) {
            return $this->redirectWithStatus('validation_error');
        }

        $trackingDomain = $this->domain((string) $request->request->get('tracking_domain', ''));
        $trackingDomainInput = trim((string) $request->request->get('tracking_domain', ''));
        if ($trackingDomainInput !== '' && ($trackingDomain === null || !$this->resolvesDns($trackingDomain))) {
            return $this->redirectWithStatus('dns_error');
        }

        $profile = trim((string) $request->request->get('routing_profile', ''));
        $mode = trim((string) $request->request->get('routing_mode', ''));

        if ($mode !== '' && RoutingMode::tryFrom($mode) === null) {
            return $this->redirectWithStatus('validation_error');
        }

        try {
            $exists = (int) $this->db()->fetchOne('SELECT COUNT(*) FROM emails WHERE id = :id', ['id' => $id]);
            if ($exists === 0) {
                return $this->redirectWithStatus('not_found');
            }

            if ($profile !== '') {
                $profileExists = (int) $this->db()->fetchOne(
                    'SELECT COUNT(*) FROM smr_routing_profile WHERE name = :name AND enabled = 1',
                    ['name' => $profile]
                );
                if ($profileExists === 0) {
                    return $this->redirectWithStatus('validation_error');
                }
            }

            $this->db()->executeStatement(
                'INSERT INTO smr_email_routing (email_id, tracking_domain, routing_profile, routing_mode, updated_at)
                 VALUES (:email_id, :tracking_domain, :routing_profile, :routing_mode, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE tracking_domain = VALUES(tracking_domain), routing_profile = VALUES(routing_profile),
                 routing_mode = VALUES(routing_mode), updated_at = VALUES(updated_at)',
                [
                    'email_id' => $id,
                    'tracking_domain' => $trackingDomain,
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
        return $this->redirect($this->generateUrl('smart_mailer_admin_campaign_routing', ['status' => $status]));
    }

    private function notice(string $status): ?string
    {
        return match ($status) {
            'saved' => 'Preferencias de envío guardadas.',
            'not_found' => 'No se encontró el email seleccionado.',
            'validation_error' => 'Revisá el perfil, el modo y el dominio de tracking.',
            'dns_error' => 'El dominio de tracking no tiene un registro DNS A, AAAA o CNAME resolvible. Crealo o espera la propagacion antes de guardarlo.',
            'db_error' => 'No se pudieron guardar las preferencias.',
            default => null,
        };
    }

    /**
     * @param list<array<string, mixed>> $emails
     * @param list<array<string, mixed>> $profiles
     */
    private function renderContent(array $emails, array $profiles): string
    {
        $profileOptions = '<option value="">Usar profile por defecto</option>';
        foreach ($profiles as $profile) {
            $name = (string) ($profile['name'] ?? '');
            $profileOptions .= sprintf('<option value="%s">%s</option>', $this->escape($name));
        }

        $rows = '';
        foreach ($emails as $email) {
            $id = (int) ($email['id'] ?? 0);
            $name = (string) ($email['name'] ?? '');
            $subject = (string) ($email['subject'] ?? '');
            $from = (string) ($email['from_address'] ?? '');
            $trackingDomain = (string) ($email['tracking_domain'] ?? '');
            $selectedProfile = (string) ($email['routing_profile'] ?? '');
            $selectedMode = (string) ($email['routing_mode'] ?? '');
            $options = str_replace(
                sprintf('value="%s">%s</option>', $this->escape($selectedProfile), $this->escape($selectedProfile)),
                sprintf('value="%s" selected>%s</option>', $this->escape($selectedProfile), $this->escape($selectedProfile)),
                $profileOptions
            );
            $rows .= sprintf(
                '<tr><td style="padding:12px;border-top:1px solid #e5e7eb;"><strong>%s</strong><br><small>%s</small></td><td style="padding:12px;border-top:1px solid #e5e7eb;">%s</td><td style="padding:12px;border-top:1px solid #e5e7eb;"><form method="post" action="%s" style="display:grid;grid-template-columns:1.2fr 1fr 1fr auto;gap:8px;min-width:650px;"><input type="hidden" name="_token" value="%s"><input class="form-control" name="tracking_domain" value="%s" placeholder="trk.cliente.com"><select class="form-control" name="routing_profile">%s</select><select class="form-control" name="routing_mode">%s</select><button class="btn btn-primary" type="submit">Guardar</button></form></td></tr>',
                $this->escape($name === '' ? ('Email #'.$id) : $name),
                $this->escape($subject),
                $this->escape($from === '' ? 'Usa remitente global' : $from),
                $this->escape($this->generateUrl('smart_mailer_admin_campaign_routing_save', ['emailId' => $id])),
                $this->escape($this->csrfToken('smart_mailer_email_routing_'.$id)),
                $this->escape($trackingDomain),
                $options,
                $this->modeOptions($selectedMode)
            );
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="3" style="padding:12px;">No hay emails de Mautic para configurar.</td></tr>';
        }

        return sprintf(
            '<h2>Routing por email</h2><div class="alert alert-info">Cada campaña de Mautic usa un email. Elegí acá el subdominio de tracking y cómo debe rutearse ese email. Dejá un campo vacío para conservar el comportamiento por defecto. El dominio de tracking debe estar en <strong>Allowed Domains</strong> y apuntar a esta instancia.</div><div style="overflow:auto"><table style="width:100%%;border-collapse:collapse"><thead><tr style="text-align:left;background:#f9fafb"><th style="padding:12px">Email</th><th style="padding:12px">Remitente</th><th style="padding:12px">Tracking y SmartMailer</th></tr></thead><tbody>%s</tbody></table></div>',
            $rows
        );
    }

    private function modeOptions(string $selected): string
    {
        $options = '<option value="">Usar modo del profile</option>';
        foreach (RoutingMode::cases() as $mode) {
            $options .= sprintf(
                '<option value="%s"%s>%s</option>',
                $this->escape($mode->value),
                $mode->value === $selected ? ' selected' : '',
                $this->escape(str_replace('_', ' ', $mode->value))
            );
        }

        return $options;
    }

    private function domain(string $value): ?string
    {
        $domain = strtolower(trim($value));
        $domain = preg_replace('#^https?://#', '', $domain) ?? '';
        $domain = preg_replace('#/.*$#', '', $domain) ?? '';
        $domain = trim($domain, '.');

        return $domain === '' ? null : $domain;
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
