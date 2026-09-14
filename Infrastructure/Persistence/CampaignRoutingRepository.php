<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;

final class CampaignRoutingRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array{tracking_domain:?string,routing_profile:?string,routing_mode:?string}|null
     */
    public function findForEmail(?int $emailId): ?array
    {
        if ($emailId === null || $emailId <= 0) {
            return null;
        }

        try {
            $row = $this->connection->fetchAssociative(
                'SELECT tracking_domain, routing_profile, routing_mode
                 FROM smr_email_routing WHERE email_id = :email_id',
                ['email_id' => $emailId]
            );
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($row)) {
            return null;
        }

        return [
            'tracking_domain' => $this->nullableString($row['tracking_domain'] ?? null),
            'routing_profile' => $this->nullableString($row['routing_profile'] ?? null),
            'routing_mode' => $this->nullableString($row['routing_mode'] ?? null),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }
}
