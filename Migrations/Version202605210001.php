<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\SchemaInitializer;
use MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence\SmartMailerSettingsRepository;

final class Version202605210001 extends AbstractMigration
{
    protected function isApplicable(Schema $schema): bool
    {
        return !$schema->hasTable($this->concatPrefix('smr_provider'));
    }

    protected function up(): void
    {
        foreach (SchemaInitializer::createStatements() as $sql) {
            $this->addSql($sql);
        }

        foreach (SmartMailerSettingsRepository::defaultValues() as $settingKey => $settingValue) {
            $this->addSql(
                'INSERT IGNORE INTO smr_setting (setting_key, setting_value, updated_at)
                 VALUES (:setting_key, :setting_value, UTC_TIMESTAMP())',
                [
                    'setting_key' => $settingKey,
                    'setting_value' => $settingValue,
                ]
            );
        }
    }
}
