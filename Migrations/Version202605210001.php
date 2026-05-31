<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

final class Version202605210001 extends AbstractMigration
{
    protected function isApplicable(Schema $schema): bool
    {
        return !$schema->hasTable($this->concatPrefix('smr_provider'));
    }

    protected function up(): void
    {
        // Schema is initialized lazily by the plugin bootstrap.
        // Keep this migration as a compatibility marker for Mautic's plugin reload flow.
    }
}
