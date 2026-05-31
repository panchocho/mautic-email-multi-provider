<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\ProviderConfig;

use MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject\ProviderType;

final class ProviderConfigSchemaRegistry
{
    private const TRANSPORT_API = 'api';
    private const TRANSPORT_SMTP = 'smtp';

    /**
     * @return array<string, mixed>
     */
    public function defaultTemplate(string $providerType): array
    {
        return match ($this->normalizeProviderType($providerType)) {
            ProviderType::BREVO->value => [
                'transport' => self::TRANSPORT_API,
                'api_key' => 'xkeysib-REEMPLAZAR',
                'base_url' => 'https://api.brevo.com/v3',
                'sender_email' => 'no-reply@tu-dominio.com',
                'sender_name' => 'Tu Marca',
            ],
            ProviderType::RESEND->value => [
                'transport' => self::TRANSPORT_API,
                'api_key' => 're_REEMPLAZAR',
                'base_url' => 'https://api.resend.com',
                'sender_email' => 'no-reply@tu-dominio.com',
                'sender_name' => 'Tu Marca',
            ],
            ProviderType::AMAZON_SES->value => [
                'transport' => self::TRANSPORT_API,
                'access_key_id' => 'AKIA...',
                'secret_access_key' => 'REEMPLAZAR',
                'region' => 'us-east-1',
                'sender_email' => 'no-reply@tu-dominio.com',
                'sender_name' => 'Tu Marca',
            ],
            ProviderType::SENDGRID->value => [
                'transport' => self::TRANSPORT_API,
                'api_key' => 'SG.REEMPLAZAR',
                'base_url' => 'https://api.sendgrid.com',
                'sender_email' => 'no-reply@tu-dominio.com',
                'sender_name' => 'Tu Marca',
            ],
            ProviderType::MAILGUN->value => [
                'transport' => self::TRANSPORT_API,
                'api_key' => 'key-REEMPLAZAR',
                'base_url' => 'https://api.mailgun.net',
                'domain' => 'mg.tu-dominio.com',
                'sender_email' => 'no-reply@tu-dominio.com',
                'sender_name' => 'Tu Marca',
            ],
            ProviderType::POSTAL->value => [
                'transport' => self::TRANSPORT_API,
                'api_key' => 'postal_server_api_key',
                'base_url' => 'https://postal.tu-dominio.com',
                'sender_email' => 'no-reply@tu-dominio.com',
                'sender_name' => 'Tu Marca',
            ],
            ProviderType::POWERMTA->value => [
                'transport' => self::TRANSPORT_SMTP,
                'host' => 'powermta.tu-dominio.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'smtp-user',
                'password' => 'smtp-password',
                'sender_email' => 'no-reply@tu-dominio.com',
                'sender_name' => 'Tu Marca',
            ],
            ProviderType::SPARKPOST->value => [
                'transport' => self::TRANSPORT_API,
                'api_key' => 'SPARKPOST_REEMPLAZAR',
                'base_url' => 'https://api.sparkpost.com/api/v1',
                'sender_email' => 'no-reply@tu-dominio.com',
                'sender_name' => 'Tu Marca',
            ],
            ProviderType::SMTP_ONLY->value => [
                'transport' => self::TRANSPORT_SMTP,
                'host' => 'smtp.tu-proveedor.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'usuario',
                'password' => 'clave',
                'sender_email' => 'no-reply@tu-dominio.com',
                'sender_name' => 'Tu Marca',
            ],
            default => [
                'transport' => self::TRANSPORT_SMTP,
                'host' => 'smtp.tu-proveedor.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'usuario',
                'password' => 'clave',
                'sender_email' => 'no-reply@tu-dominio.com',
                'sender_name' => 'Tu Marca',
            ],
        };
    }

    /**
     * @param array<string, mixed> $config
     * @return array{valid: bool, reason?: string, normalized?: array<string, mixed>}
     */
    public function validate(string $providerType, array $config): array
    {
        $providerType = $this->normalizeProviderType($providerType);

        $normalized = $config;
        $normalized['transport'] = $this->resolveTransport($providerType, $normalized);
        $normalized['sender_email'] = isset($normalized['sender_email']) ? trim((string) $normalized['sender_email']) : '';
        $normalized['sender_name'] = isset($normalized['sender_name']) ? trim((string) $normalized['sender_name']) : '';

        if ($normalized['sender_email'] === '' || filter_var($normalized['sender_email'], FILTER_VALIDATE_EMAIL) === false) {
            return ['valid' => false, 'reason' => 'sender_email_invalid'];
        }

        return match ($providerType) {
            ProviderType::BREVO->value => $this->validateBrevoConfig($normalized),
            ProviderType::RESEND->value => $this->validateResendConfig($normalized),
            ProviderType::SENDGRID->value => $this->validateSendGridConfig($normalized),
            ProviderType::SPARKPOST->value => $this->validateSparkPostConfig($normalized),
            ProviderType::POSTAL->value => $this->validatePostalConfig($normalized),
            ProviderType::MAILGUN->value => $this->validateMailgunConfig($normalized),
            ProviderType::AMAZON_SES->value => $this->validateSesConfig($normalized),
            ProviderType::POWERMTA->value, ProviderType::SMTP_GENERIC->value, ProviderType::SMTP_ONLY->value => $this->validateSmtpConfig($normalized),
            default => ['valid' => false, 'reason' => 'unsupported_provider_type'],
        };
    }

    private function normalizeProviderType(string $providerType): string
    {
        return strtolower(trim($providerType));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function resolveTransport(string $providerType, array $config): string
    {
        $explicitTransport = isset($config['transport']) ? strtolower(trim((string) $config['transport'])) : '';
        if ($explicitTransport === self::TRANSPORT_API || $explicitTransport === self::TRANSPORT_SMTP) {
            return $explicitTransport;
        }

        $hasApiCredentials = $this->hasApiCredentials($config);
        $hasSmtpCredentials = $this->hasSmtpCredentials($config);

        if ($hasApiCredentials && !$hasSmtpCredentials) {
            return self::TRANSPORT_API;
        }

        if ($hasSmtpCredentials && !$hasApiCredentials) {
            return self::TRANSPORT_SMTP;
        }

        return match ($providerType) {
            ProviderType::POWERMTA->value,
            ProviderType::SMTP_GENERIC->value,
            ProviderType::SMTP_ONLY->value => self::TRANSPORT_SMTP,
            default => self::TRANSPORT_API,
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    private function hasApiCredentials(array $config): bool
    {
        foreach (['api_key', 'access_key_id', 'secret_access_key'] as $key) {
            if (array_key_exists($key, $config) && trim((string) $config[$key]) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function hasSmtpCredentials(array $config): bool
    {
        foreach (['host', 'port', 'username', 'password'] as $key) {
            if (array_key_exists($key, $config) && trim((string) $config[$key]) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string> $requiredKeys
     * @return array{valid: bool, reason?: string, normalized?: array<string, mixed>}
     */
    private function validateApiProvider(array $config, array $requiredKeys, string $providerLabel): array
    {
        foreach ($requiredKeys as $requiredKey) {
            if (!array_key_exists($requiredKey, $config) || trim((string) $config[$requiredKey]) === '') {
                return ['valid' => false, 'reason' => $providerLabel . '_' . $requiredKey . '_missing'];
            }
        }

        if (isset($config['base_url']) && !filter_var((string) $config['base_url'], FILTER_VALIDATE_URL)) {
            return ['valid' => false, 'reason' => $providerLabel . '_base_url_invalid'];
        }

        return ['valid' => true, 'normalized' => $config];
    }

    /**
     * @param array<string, mixed> $config
     * @return array{valid: bool, reason?: string, normalized?: array<string, mixed>}
     */
    private function validateHybridProviderConfig(array $config, string $providerLabel, array $apiRequiredKeys): array
    {
        return $config['transport'] === self::TRANSPORT_SMTP
            ? $this->validateSmtpConfig($config)
            : $this->validateApiProvider($config, $apiRequiredKeys, $providerLabel);
    }

    /**
     * @param array<string, mixed> $config
     * @return array{valid: bool, reason?: string, normalized?: array<string, mixed>}
     */
    private function validatePostalConfig(array $config): array
    {
        if ($config['transport'] === self::TRANSPORT_SMTP) {
            return $this->validateSmtpConfig($config);
        }

        $result = $this->validateApiProvider($config, ['api_key'], 'postal');
        if (!$result['valid']) {
            return $result;
        }

        if (isset($config['base_url']) && !filter_var((string) $config['base_url'], FILTER_VALIDATE_URL)) {
            return ['valid' => false, 'reason' => 'postal_base_url_invalid'];
        }

        return ['valid' => true, 'normalized' => $config];
    }

    /**
     * @param array<string, mixed> $config
     * @return array{valid: bool, reason?: string, normalized?: array<string, mixed>}
     */
    private function validateMailgunConfig(array $config): array
    {
        if ($config['transport'] === self::TRANSPORT_SMTP) {
            return $this->validateSmtpConfig($config);
        }

        $requiredKeys = ['api_key', 'domain'];
        foreach ($requiredKeys as $requiredKey) {
            if (!array_key_exists($requiredKey, $config) || trim((string) $config[$requiredKey]) === '') {
                return ['valid' => false, 'reason' => 'mailgun_' . $requiredKey . '_missing'];
            }
        }

        if (isset($config['base_url']) && !filter_var((string) $config['base_url'], FILTER_VALIDATE_URL)) {
            return ['valid' => false, 'reason' => 'mailgun_base_url_invalid'];
        }

        return ['valid' => true, 'normalized' => $config];
    }

    /**
     * @param array<string, mixed> $config
     * @return array{valid: bool, reason?: string, normalized?: array<string, mixed>}
     */
    private function validateSesConfig(array $config): array
    {
        if ($config['transport'] === self::TRANSPORT_SMTP) {
            $requiredKeys = ['username', 'password'];
            foreach ($requiredKeys as $requiredKey) {
                if (!array_key_exists($requiredKey, $config) || trim((string) $config[$requiredKey]) === '') {
                    return ['valid' => false, 'reason' => 'amazon_ses_' . $requiredKey . '_missing'];
                }
            }

            if (isset($config['port']) && (!is_numeric($config['port']) || (int) $config['port'] < 1 || (int) $config['port'] > 65535)) {
                return ['valid' => false, 'reason' => 'smtp_port_invalid'];
            }

            if (
                (!isset($config['host']) || trim((string) $config['host']) === '')
                && (!isset($config['region']) || trim((string) $config['region']) === '')
            ) {
                return ['valid' => false, 'reason' => 'amazon_ses_region_missing'];
            }

            if (isset($config['region']) && !preg_match('/^[a-z]{2}(-gov)?-[a-z]+-\d$/', (string) $config['region'])) {
                return ['valid' => false, 'reason' => 'amazon_ses_region_invalid'];
            }

            return ['valid' => true, 'normalized' => $config];
        }

        $requiredKeys = ['access_key_id', 'secret_access_key', 'region'];
        foreach ($requiredKeys as $requiredKey) {
            if (!array_key_exists($requiredKey, $config) || trim((string) $config[$requiredKey]) === '') {
                return ['valid' => false, 'reason' => 'amazon_ses_' . $requiredKey . '_missing'];
            }
        }

        if (!preg_match('/^[a-z]{2}(-gov)?-[a-z]+-\d$/', (string) $config['region'])) {
            return ['valid' => false, 'reason' => 'amazon_ses_region_invalid'];
        }

        return ['valid' => true, 'normalized' => $config];
    }

    /**
     * @param array<string, mixed> $config
     * @return array{valid: bool, reason?: string, normalized?: array<string, mixed>}
     */
    private function validateBrevoConfig(array $config): array
    {
        return $this->validateHybridProviderConfig($config, 'brevo', ['api_key']);
    }

    /**
     * @param array<string, mixed> $config
     * @return array{valid: bool, reason?: string, normalized?: array<string, mixed>}
     */
    private function validateResendConfig(array $config): array
    {
        return $this->validateHybridProviderConfig($config, 'resend', ['api_key']);
    }

    /**
     * @param array<string, mixed> $config
     * @return array{valid: bool, reason?: string, normalized?: array<string, mixed>}
     */
    private function validateSendGridConfig(array $config): array
    {
        return $this->validateHybridProviderConfig($config, 'sendgrid', ['api_key']);
    }

    /**
     * @param array<string, mixed> $config
     * @return array{valid: bool, reason?: string, normalized?: array<string, mixed>}
     */
    private function validateSparkPostConfig(array $config): array
    {
        return $this->validateHybridProviderConfig($config, 'sparkpost', ['api_key']);
    }

    /**
     * @param array<string, mixed> $config
     * @return array{valid: bool, reason?: string, normalized?: array<string, mixed>}
     */
    private function validateSmtpConfig(array $config): array
    {
        $requiredKeys = ['host', 'port', 'username', 'password'];
        foreach ($requiredKeys as $requiredKey) {
            if (!array_key_exists($requiredKey, $config) || trim((string) $config[$requiredKey]) === '') {
                return ['valid' => false, 'reason' => 'smtp_' . $requiredKey . '_missing'];
            }
        }

        if (!is_numeric($config['port']) || (int) $config['port'] < 1 || (int) $config['port'] > 65535) {
            return ['valid' => false, 'reason' => 'smtp_port_invalid'];
        }

        return ['valid' => true, 'normalized' => $config];
    }
}
