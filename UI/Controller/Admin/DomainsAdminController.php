<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin;

use Mautic\CoreBundle\Configurator\Configurator;
use Mautic\CoreBundle\Helper\CacheHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DomainsAdminController extends AbstractAdminController
{
    private const TOKEN_ID = 'smart_mailer_domains';

    #[Route(path: '/admin/smart-mailer/domains', name: 'smart_mailer_admin_domains', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $domains = $this->domains((string) $this->coreParametersHelper->get('allowed_domains', '')) ?? [];

        return $this->renderAdminPage($request, 'domains', 'Dominios y tracking', $this->content($this->multidomainInstalled(), $domains), $this->notice((string) $request->query->get('status', '')));
    }

    #[Route(path: '/admin/smart-mailer/domains', name: 'smart_mailer_admin_domains_save', methods: ['POST'])]
    public function save(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(self::TOKEN_ID, (string) $request->request->get('_token', ''))) {
            return $this->redirectWithStatus('csrf_error');
        }
        if (!$this->multidomainInstalled()) {
            return $this->redirectWithStatus('multidomain_missing');
        }

        $domains = $this->domains((string) $request->request->get('tracking_domains', ''));
        if ($domains === null) {
            return $this->redirectWithStatus('validation_error');
        }
        foreach ($domains as $domain) {
            if (!$this->resolvesDns($domain)) {
                return $this->redirectWithStatus('dns_error');
            }
        }

        try {
            $configurator = $this->container->get('mautic.configurator');
            assert($configurator instanceof Configurator);
            $configurator->mergeParameters(['allowed_domains' => implode(', ', $domains)]);
            $configurator->write();
            $cacheHelper = $this->container->get('mautic.helper.cache');
            assert($cacheHelper instanceof CacheHelper);
            $cacheHelper->refreshConfig();
        } catch (\Throwable) {
            return $this->redirectWithStatus('save_error');
        }

        return $this->redirectWithStatus('saved');
    }

    private function multidomainInstalled(): bool
    {
        try {
            return (int) $this->db()->fetchOne('SELECT COUNT(*) FROM plugins WHERE bundle = :bundle AND is_missing = 0', ['bundle' => 'MauticMultidomainBundle']) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<string>|null */
    private function domains(string $raw): ?array
    {
        $result = [];
        foreach (preg_split('/[\s,]+/', trim($raw)) ?: [] as $domain) {
            if ($domain === '') {
                continue;
            }
            $domain = strtolower(trim($domain));
            $domain = preg_replace('#^https?://#', '', $domain) ?? '';
            $domain = preg_replace('#/.*$#', '', $domain) ?? '';
            $domain = rtrim($domain, '.');
            if ($domain === '' || !filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                return null;
            }
            $result[$domain] = $domain;
        }

        return array_values($result);
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

    /** @param list<string> $domains */
    private function content(bool $installed, array $domains): string
    {
        if (!$installed) {
            return '<h2>Dominios y tracking</h2><div class="alert alert-warning">Mautic Multidomain no esta instalado o esta deshabilitado.</div>';
        }

        $rows = '';
        foreach ($domains as $domain) {
            $status = $this->resolvesDns($domain) ? '<span style="color:#15803d;">DNS resuelve</span>' : '<span style="color:#b45309;">DNS no resuelve</span>';
            $rows .= sprintf('<tr><td style="padding:8px;border-top:1px solid #e5e7eb;"><code>%s</code></td><td style="padding:8px;border-top:1px solid #e5e7eb;">%s</td></tr>', $this->escape($domain), $status);
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="2" style="padding:8px;border-top:1px solid #e5e7eb;color:#6b7280;">No hay dominios de tracking habilitados.</td></tr>';
        }

        return sprintf(
            '<h2>Dominios y tracking</h2><div class="alert alert-info"><strong>Un solo lugar para cada cosa.</strong><br>1. Agrega aqui los hosts de clicks y aperturas, por ejemplo <code>trk.cliente.com</code>.<br>2. En <strong>Providers</strong> y <strong>Bindings</strong> define el proveedor de envio.<br>3. En <strong>Routing por email</strong> o <strong>Campaign overrides</strong> elegi el tracking y el perfil. Multidomain sirve las URLs; no configura SMTP.</div><form method="post" action="%s" style="max-width:760px;"><input type="hidden" name="_token" value="%s"><label style="display:flex;flex-direction:column;gap:6px;"><strong>Hosts de tracking permitidos</strong><textarea class="form-control" name="tracking_domains" rows="6" placeholder="trk.cliente.com&#10;trk.otrocliente.com">%s</textarea><small style="color:#6b7280;">Uno por linea o separados por coma. Cada host debe tener DNS A, AAAA o CNAME antes de guardar.</small></label><button class="btn btn-primary" type="submit" style="margin-top:12px;">Guardar dominios</button></form><h3 style="margin-top:24px;">Estado</h3><table style="width:100%%;max-width:760px;border-collapse:collapse;"><thead><tr style="text-align:left;background:#f9fafb;"><th style="padding:8px;">Host</th><th style="padding:8px;">DNS</th></tr></thead><tbody>%s</tbody></table>',
            $this->escape($this->generateUrl('smart_mailer_admin_domains_save')),
            $this->escape($this->csrfToken(self::TOKEN_ID)),
            $this->escape(implode("\n", $domains)),
            $rows,
        );
    }

    private function redirectWithStatus(string $status): RedirectResponse
    {
        return $this->redirect($this->generateUrl('smart_mailer_admin_domains', ['status' => $status]));
    }

    private function notice(string $status): ?string
    {
        return match ($status) {
            'saved' => 'Dominios de tracking guardados.',
            'dns_error' => 'Uno o mas hosts no tienen DNS A, AAAA o CNAME resolvible.',
            'validation_error' => 'Ingresa nombres de host validos, sin rutas ni puertos.',
            'multidomain_missing' => 'Mautic Multidomain no esta disponible.',
            'csrf_error' => 'Token de seguridad invalido.',
            'save_error' => 'No se pudo guardar la configuracion de Multidomain.',
            default => null,
        };
    }
}
