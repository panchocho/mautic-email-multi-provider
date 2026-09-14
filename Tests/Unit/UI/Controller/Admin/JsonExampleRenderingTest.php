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
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\ProviderConfig\ProviderConfigSchemaRegistry;
use MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\ProfilesAdminController;
use MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\ProvidersAdminController;
use MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\RulesAdminController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RequestStack;

final class JsonExampleRenderingTest extends TestCase
{
    public function testProviderFormIncludesDefaultJsonExample(): void
    {
        $controller = $this->makeController(ProvidersAdminController::class);

        $html = $this->invokePrivate($controller, 'buildProviderForm', [
            'action' => '/admin/smart-mailer/providers/create',
            'tokenId' => 'token',
            'buttonLabel' => 'Crear proveedor',
            'provider' => null,
        ]);

        self::assertStringContainsString('data-default-config=', $html);
        self::assertStringContainsString('REEMPLAZAR', $html);
        self::assertStringContainsString('api_key', $html);
        self::assertStringContainsString('id="smart-mailer-provider-config-json"', $html);
    }

    public function testProfileFormIncludesModeExample(): void
    {
        $controller = $this->makeController(ProfilesAdminController::class);

        $html = $this->invokePrivate($controller, 'buildProfileForm', [
            'action' => '/admin/smart-mailer/profiles/create',
            'tokenId' => 'token',
            'buttonLabel' => 'Crear profile',
            'profile' => ['mode' => 'domain_routing', 'enabled' => 1, 'config' => '{}'],
        ]);

        self::assertStringContainsString('data-default-config=', $html);
        self::assertStringContainsString('id="smart-mailer-profile-config-json"', $html);
        self::assertStringContainsString('allowed_domains', $html);
        self::assertStringContainsString('gmail.com', $html);
    }

    public function testRuleFormIncludesConstraintExample(): void
    {
        $controller = $this->makeController(RulesAdminController::class);

        $html = $this->invokePrivate($controller, 'buildRuleForm', [
            'action' => '/admin/smart-mailer/rules/create',
            'tokenId' => 'token',
            'buttonLabel' => 'Crear rule',
            'rule' => null,
            'profiles' => [['id' => '1', 'name' => 'default']],
            'providers' => [['id' => 'p1', 'name' => 'Provider', 'code' => 'provider-1']],
        ]);

        self::assertStringContainsString('id="smart-mailer-rule-constraints-json"', $html);
        self::assertStringContainsString('tenant_id', $html);
        self::assertStringContainsString('recipient_domain', $html);
    }

    private function registry(): JsonExampleRegistry
    {
        return new JsonExampleRegistry(new ProviderConfigSchemaRegistry());
    }

    private function makeController(string $className): object
    {
        $controller = new $className(
            $this->createMock(ManagerRegistry::class),
            $this->createMock(ModelFactory::class),
            $this->createConfiguredMock(UserHelper::class, ['getUser' => null]),
            $this->createMock(CoreParametersHelper::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(Translator::class),
            $this->createMock(FlashBag::class),
            $this->createMock(RequestStack::class),
            $this->createMock(CorePermissions::class),
            $this->registry(),
        );

        $reflection = new ReflectionClass($controller);
        $property = $reflection->getProperty('container');
        $property->setAccessible(true);
        $property->setValue($controller, new Container());

        return $controller;
    }

    /**
     * @param array<string, mixed> $args
     */
    private function invokePrivate(object $object, string $method, array $args): string
    {
        $reflection = new ReflectionClass($object);
        $methodRef = $reflection->getMethod($method);
        $methodRef->setAccessible(true);

        return (string) $methodRef->invokeArgs($object, $args);
    }
}
