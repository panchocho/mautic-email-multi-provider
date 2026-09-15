<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

/** Creates per-email and per-campaign routing tables on install and upgrade. */
final class Version202609150001 extends AbstractMigration
{
    protected function isApplicable(Schema $schema): bool
    {
        return !$schema->hasTable($this->concatPrefix('smr_email_routing'))
            || !$schema->hasTable($this->concatPrefix('smr_campaign_routing'));
    }

    protected function up(): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS smr_email_routing (
  email_id INT NOT NULL,
  tracking_domain VARCHAR(255) DEFAULT NULL,
  routing_profile VARCHAR(120) DEFAULT NULL,
  routing_mode VARCHAR(32) DEFAULT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY(email_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB
SQL);
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS smr_campaign_routing (
  campaign_id INT NOT NULL,
  tracking_domain VARCHAR(255) DEFAULT NULL,
  routing_profile VARCHAR(120) DEFAULT NULL,
  routing_mode VARCHAR(32) DEFAULT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY(campaign_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB
SQL);
    }
}
