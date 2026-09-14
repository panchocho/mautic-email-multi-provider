<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Tests\Unit\UI\Controller\Admin;

use Doctrine\Persistence\ManagerRegistry;
use Mautic\CoreBundle\Factory\ModelFactory;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Service\FlashBag;
use Mautic\CoreBundle\Translation\Translator;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Config\JsonExampleRegistry;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\SmartMailerSettingsRepository;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\ProviderConfig\ProviderConfigSchemaRegistry;
use MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\SettingsAdminController;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Doctrine\DBAL\Connection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class SettingsAdminControllerTest extends TestCase
{
    public function testInvokeRendersWithoutAccessingContainerParameters(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'smart_mailer_admin_settings');
        $renderedModuleContent = null;
        $generatedRoutes = [];

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with(
                '@SmartMailerRouter/Admin/page.html.twig',
                self::callback(static function (array $parameters) use (&$renderedModuleContent): bool {
                    $renderedModuleContent = (string) ($parameters['moduleContent'] ?? '');

                    return ($parameters['pageTitle'] ?? null) === 'Settings'
                        && ($parameters['module'] ?? null) === 'settings'
                        && str_contains($renderedModuleContent, 'delivery_log_retention_days');
                })
            )
            ->willReturn('settings rendered');

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')
            ->willReturnCallback(static function (string $route, array $parameters, int $referenceType) use (&$generatedRoutes): string {
                $generatedRoutes[] = [$route, $parameters, $referenceType];

                return match ($route) {
                    'smart_mailer_admin_settings_save', 'smart_mailer_admin_settings' => '/admin/smart-mailer/settings',
                    default => '/unexpected-route',
                };
            });

        $container = new class($twig, $router) implements ContainerInterface {
            public function __construct(
                private readonly Environment $twig,
                private readonly UrlGeneratorInterface $router
            ) {
            }

            public function get(string $id): mixed
            {
                return match ($id) {
                    'twig' => $this->twig,
                    'router' => $this->router,
                    default => throw new ServiceNotFoundException($id),
                };
            }

            public function has(string $id): bool
            {
                return in_array($id, ['twig', 'router'], true);
            }
        };

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(false);
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getConnection')->willReturn($connection);
        $repository = new SmartMailerSettingsRepository($connection);

        $controller = new SettingsAdminController(
            $registry,
            $this->createMock(ModelFactory::class),
            $this->createConfiguredMock(UserHelper::class, ['getUser' => null]),
            $this->createMock(CoreParametersHelper::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(Translator::class),
            $this->createMock(FlashBag::class),
            $requestStack,
            $this->createMock(CorePermissions::class),
            new JsonExampleRegistry(new ProviderConfigSchemaRegistry()),
            $repository
        );

        $reflection = new ReflectionClass($controller);
        $property = $reflection->getProperty('container');
        $property->setAccessible(true);
        $property->setValue($controller, $container);

        $response = $controller->__invoke($request);

        self::assertSame('settings rendered', $response->getContent());
        self::assertIsString($renderedModuleContent);
        self::assertStringContainsString('value="90"', $renderedModuleContent);
        self::assertStringContainsString('value="8"', $renderedModuleContent);
        self::assertContains(
            ['smart_mailer_admin_settings_save', [], UrlGeneratorInterface::ABSOLUTE_PATH],
            $generatedRoutes
        );
        self::assertContains(
            ['smart_mailer_admin_settings', [], UrlGeneratorInterface::ABSOLUTE_PATH],
            $generatedRoutes
        );
    }
}
