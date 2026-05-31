<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Application\Provider;

use DateTimeImmutable;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Contract\ProviderGuardInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Contract\ProviderHealthRepositoryInterface;
use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Model\ProviderProfile;
use MauticPlugin\SmartMailerRouterBundle\Domain\Routing\Model\RoutingRequest;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\ProviderConfigurationRepository;

final class ProviderGuard implements ProviderGuardInterface
{
    public function __construct(
        private readonly ProviderHealthRepositoryInterface $healthRepository,
        private readonly ProviderConfigurationRepository $providerConfigurationRepository,
        private readonly float $minimumHealthScore = 40.0
    ) {
    }

    public function isEligible(ProviderProfile $profile, RoutingRequest $request): bool
    {
        if (!$profile->enabled) {
            return false;
        }

        $recipientDomain = ltrim((string) (strrchr($request->recipient, '@') ?: ''), '@');
        if (!$profile->supportsDomain($recipientDomain)) {
            return false;
        }

        if ($profile->supportedMessageTypes !== [] && !in_array($request->messageType, $profile->supportedMessageTypes, true)) {
            return false;
        }

        if ($profile->supportedRegions !== [] && !in_array($request->region, $profile->supportedRegions, true)) {
            return false;
        }

        $storedProvider = $this->providerConfigurationRepository->resolveProvider($profile->type->value, $profile->name);
        if (is_array($storedProvider)) {
            if ((int) ($storedProvider['enabled'] ?? 0) !== 1) {
                return false;
            }

            $quarantinedUntil = $storedProvider['quarantined_until'] ?? null;
            if (is_string($quarantinedUntil) && $quarantinedUntil !== '') {
                $quarantineUntil = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $quarantinedUntil);
                if ($quarantineUntil instanceof DateTimeImmutable && $quarantineUntil > new DateTimeImmutable('now')) {
                    return false;
                }
            }

            if ((float) ($storedProvider['health_score'] ?? 100.0) < $this->minimumHealthScore) {
                return false;
            }
        }

        $health = $this->healthRepository->get($profile->name);
        if ($health === null) {
            return true;
        }

        return $health->score >= $this->minimumHealthScore;
    }
}
