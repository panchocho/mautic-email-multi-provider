<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin;

use Doctrine\DBAL\Connection;
use Mautic\CoreBundle\Controller\CommonController;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Config\JsonExampleRegistry;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\SmartMailerSettingsRepository;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\SchemaInitializer;
use Doctrine\Persistence\ManagerRegistry;
use Mautic\CoreBundle\Factory\ModelFactory;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Service\FlashBag;
use Mautic\CoreBundle\Translation\Translator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractAdminController extends CommonController
{
    public function __construct(
        ManagerRegistry $doctrine,
        ModelFactory $modelFactory,
        UserHelper $userHelper,
        CoreParametersHelper $coreParametersHelper,
        EventDispatcherInterface $dispatcher,
        Translator $translator,
        FlashBag $flashBag,
        ?RequestStack $requestStack,
        ?CorePermissions $security,
        protected ?JsonExampleRegistry $jsonExampleRegistry = null,
        protected ?SmartMailerSettingsRepository $settingsRepository = null
    ) {
        parent::__construct(
            $doctrine,
            $modelFactory,
            $userHelper,
            $coreParametersHelper,
            $dispatcher,
            $translator,
            $flashBag,
            $requestStack,
            $security
        );
    }

    protected function renderAdminPage(
        Request $request,
        string $module,
        string $title,
        string $content,
        ?string $notice = null
    ): Response {
        $activeRoute = $request->attributes->get('_route');
        if (!is_string($activeRoute) || $activeRoute === '') {
            $activeRoute = 'smart_mailer_admin_dashboard';
        }

        return $this->delegateView([
            'viewParameters' => [
                'module' => $module,
                'pageTitle' => $title,
                'moduleContent' => $content,
                'notice' => $notice,
                'sections' => $this->modules(),
            ],
            'contentTemplate' => '@SmartMailerRouter/Admin/page.html.twig',
            'passthroughVars' => [
                'activeLink' => '#smart_mailer_router',
                'route' => $this->generateUrl($activeRoute),
                'mauticContent' => 'smartMailerRouter',
            ],
        ]);
    }

    protected function csrfToken(string $tokenId): string
    {
        if (!$this->container->has('security.csrf.token_manager')) {
            return '';
        }

        $tokenManager = $this->container->get('security.csrf.token_manager');

        return (string) $tokenManager->getToken($tokenId);
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected function db(): Connection
    {
        if ($this->container->has(SchemaInitializer::class)) {
            $this->container->get(SchemaInitializer::class)->ensureInitialized();
        }

        return $this->doctrine->getConnection();
    }

    protected function jsonExampleRegistry(): ?JsonExampleRegistry
    {
        return $this->jsonExampleRegistry;
    }

    protected function settingsRepository(): ?SmartMailerSettingsRepository
    {
        return $this->settingsRepository;
    }

    #[Required]
    public function setJsonExampleRegistry(?JsonExampleRegistry $jsonExampleRegistry): void
    {
        $this->jsonExampleRegistry = $jsonExampleRegistry;
    }

    #[Required]
    public function setSettingsRepository(?SmartMailerSettingsRepository $settingsRepository): void
    {
        $this->settingsRepository = $settingsRepository;
    }

    /**
     * @return array<string, string>
     */
    private function modules(): array
    {
        return [
            'dashboard' => 'smart_mailer_admin_dashboard',
            'settings'  => 'smart_mailer_admin_settings',
            'providers' => 'smart_mailer_admin_providers',
            'bindings' => 'smart_mailer_admin_bindings',
            'domains' => 'smart_mailer_admin_domains',
            'profiles'  => 'smart_mailer_admin_profiles',
            'campaign-routing' => 'smart_mailer_admin_campaign_routing',
            'campaign-overrides' => 'smart_mailer_admin_campaign_overrides',
            'rules'     => 'smart_mailer_admin_rules',
            'health'    => 'smart_mailer_admin_health',
            'warmup'    => 'smart_mailer_admin_warmup',
            'logs'      => 'smart_mailer_admin_logs',
        ];
    }
}
