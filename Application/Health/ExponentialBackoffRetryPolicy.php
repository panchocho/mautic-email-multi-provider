<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Application\Health;

use MauticPlugin\SmartMailerRouterBundle\Domain\Provider\Contract\RetryPolicyInterface;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\SmartMailerSettingsRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class ExponentialBackoffRetryPolicy implements RetryPolicyInterface
{
    public function __construct(
        private readonly SmartMailerSettingsRepository $settingsRepository,
        private readonly ParameterBagInterface $parameterBag
    ) {
    }

    public function shouldRetry(int $attempt, string $reason): bool
    {
        if ($attempt >= $this->maxAttempts()) {
            return false;
        }

        $terminalReasons = ['hard_bounce', 'policy_block', 'invalid_recipient'];
        if (in_array($reason, $terminalReasons, true)) {
            return false;
        }

        return true;
    }

    public function nextDelaySeconds(int $attempt, string $reason): int
    {
        if (!$this->shouldRetry($attempt, $reason)) {
            return 0;
        }

        $power = max(0, $attempt - 1);
        $baseDelaySeconds = max(1, (int) ceil($this->baseDelayMs() / 1000));
        $multiplier = max(1.0, $this->multiplier());
        $maxDelaySeconds = max(1, (int) ceil($this->maxDelayMs() / 1000));
        $delay = (int) ceil($baseDelaySeconds * ($multiplier ** $power));
        $jitter = ($attempt * 13) % 17;

        return min($maxDelaySeconds, $delay + $jitter);
    }

    private function maxAttempts(): int
    {
        return max(1, $this->settingsRepository->getInt(
            'retry.max_retries',
            $this->configInt('smart_mailer_router.retry', 'max_retries', 5)
        ));
    }

    private function baseDelayMs(): int
    {
        return max(0, $this->settingsRepository->getInt(
            'retry.base_delay_ms',
            $this->configInt('smart_mailer_router.retry', 'base_delay_ms', 5000)
        ));
    }

    private function multiplier(): float
    {
        return max(0.1, $this->settingsRepository->getFloat(
            'retry.multiplier',
            $this->configFloat('smart_mailer_router.retry', 'multiplier', 2.0)
        ));
    }

    private function maxDelayMs(): int
    {
        return max(0, $this->settingsRepository->getInt(
            'retry.max_delay_ms',
            $this->configInt('smart_mailer_router.retry', 'max_delay_ms', 300000)
        ));
    }

    private function configInt(string $parameter, string $key, int $default): int
    {
        $config = $this->config($parameter);
        $value = $config[$key] ?? $default;

        return is_numeric($value) ? (int) $value : $default;
    }

    private function configFloat(string $parameter, string $key, float $default): float
    {
        $config = $this->config($parameter);
        $value = $config[$key] ?? $default;

        return is_numeric($value) ? (float) $value : $default;
    }

    /**
     * @return array<string, mixed>
     */
    private function config(string $parameter): array
    {
        if (!$this->parameterBag->has($parameter)) {
            return [];
        }

        $value = $this->parameterBag->get($parameter);

        return is_array($value) ? $value : [];
    }
}
