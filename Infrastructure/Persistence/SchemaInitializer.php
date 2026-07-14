<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Platforms\AbstractPlatform;

final class SchemaInitializer
{
    private bool $initialized = false;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function ensureInitialized(): void
    {
        if ($this->initialized) {
            return;
        }

        $connection = $this->entityManager->getConnection();
        $schemaManager = $connection->createSchemaManager();
        $tables = [
            'smr_provider',
            'smr_routing_profile',
            'smr_provider_domain_binding',
            'smr_routing_rule',
            'smr_provider_metric_bucket',
            'smr_provider_health_history',
            'smr_warmup_schedule',
            'smr_warmup_state',
            'smr_setting',
            'smr_delivery_log',
            'smr_delivery_log_archive',
            'smr_retry_queue',
        ];

        try {
            if (!$schemaManager->tablesExist($tables)) {
                foreach (self::createStatements() as $sql) {
                    $connection->executeStatement($sql);
                }
            }

            $this->ensureRetryQueueColumns($connection, $schemaManager);
            $this->ensureDefaultSettings($connection);
            $this->initialized = true;
        } catch (\Throwable $exception) {
            throw $exception;
        }
    }

    /**
     * @return list<string>
     */
    public static function createStatements(): array
    {
        return [
            <<<'SQL'
CREATE TABLE IF NOT EXISTS smr_provider (
  id CHAR(36) NOT NULL,
  name VARCHAR(128) NOT NULL,
  code VARCHAR(64) NOT NULL,
  provider_type VARCHAR(32) NOT NULL,
  enabled TINYINT(1) NOT NULL,
  weight INT NOT NULL,
  priority INT NOT NULL,
  throughput_limit INT NOT NULL,
  cost_per_email NUMERIC(12, 6) NOT NULL,
  reputation NUMERIC(5, 2) NOT NULL,
  health_score SMALLINT NOT NULL,
  tags JSON NOT NULL,
  notes LONGTEXT DEFAULT NULL,
  quarantined_until DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  config JSON NOT NULL,
  created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  UNIQUE INDEX uniq_smr_provider_code (code),
  INDEX idx_smr_provider_enabled_priority (enabled, priority),
  INDEX idx_smr_provider_type_enabled (provider_type, enabled),
  PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS smr_routing_profile (
  id INT AUTO_INCREMENT NOT NULL,
  name VARCHAR(120) NOT NULL,
  mode VARCHAR(32) NOT NULL,
  enabled TINYINT(1) NOT NULL,
  config JSON NOT NULL,
  created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  UNIQUE INDEX uniq_smr_routing_profile_name (name),
  PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS smr_provider_domain_binding (
  id INT AUTO_INCREMENT NOT NULL,
  provider_id CHAR(36) NOT NULL,
  domain VARCHAR(255) NOT NULL,
  priority SMALLINT NOT NULL,
  active TINYINT(1) NOT NULL,
  created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  INDEX idx_smr_domain_binding_provider (provider_id),
  UNIQUE INDEX uniq_smr_provider_domain (provider_id, domain),
  PRIMARY KEY(id),
  CONSTRAINT fk_smr_domain_binding_provider FOREIGN KEY (provider_id) REFERENCES smr_provider (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS smr_routing_rule (
  id INT AUTO_INCREMENT NOT NULL,
  profile_id INT NOT NULL,
  provider_id CHAR(36) DEFAULT NULL,
  domain_pattern VARCHAR(255) DEFAULT NULL,
  priority INT NOT NULL,
  weight SMALLINT NOT NULL,
  enabled TINYINT(1) NOT NULL,
  constraints JSON NOT NULL,
  created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  INDEX idx_smr_rule_profile_priority (profile_id, priority),
  INDEX idx_smr_rule_provider (provider_id),
  PRIMARY KEY(id),
  CONSTRAINT fk_smr_rule_profile FOREIGN KEY (profile_id) REFERENCES smr_routing_profile (id) ON DELETE CASCADE,
  CONSTRAINT fk_smr_rule_provider FOREIGN KEY (provider_id) REFERENCES smr_provider (id) ON DELETE SET NULL
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS smr_provider_metric_bucket (
  id BIGINT AUTO_INCREMENT NOT NULL,
  provider_id CHAR(36) NOT NULL,
  bucket_start DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  bucket_interval_minutes SMALLINT NOT NULL,
  sent_count INT NOT NULL,
  delivered_count INT NOT NULL,
  bounced_count INT NOT NULL,
  complaint_count INT NOT NULL,
  open_count INT NOT NULL,
  click_count INT NOT NULL,
  deferral_count INT NOT NULL,
  failure_count INT NOT NULL,
  avg_latency_ms INT DEFAULT NULL,
  inbox_placement_estimate NUMERIC(5, 2) DEFAULT NULL,
  created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  UNIQUE INDEX uniq_smr_metric_bucket (provider_id, bucket_start, bucket_interval_minutes),
  INDEX idx_smr_metric_provider (provider_id),
  PRIMARY KEY(id),
  CONSTRAINT fk_smr_metric_provider FOREIGN KEY (provider_id) REFERENCES smr_provider (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS smr_provider_health_history (
  id BIGINT AUTO_INCREMENT NOT NULL,
  provider_id CHAR(36) NOT NULL,
  checked_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  score SMALLINT NOT NULL,
  status VARCHAR(32) NOT NULL,
  message LONGTEXT DEFAULT NULL,
  details JSON NOT NULL,
  INDEX idx_smr_health_provider_checked (provider_id, checked_at),
  PRIMARY KEY(id),
  CONSTRAINT fk_smr_health_provider FOREIGN KEY (provider_id) REFERENCES smr_provider (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS smr_warmup_schedule (
  id INT AUTO_INCREMENT NOT NULL,
  provider_id CHAR(36) DEFAULT NULL,
  scope_type VARCHAR(16) NOT NULL,
  scope_key VARCHAR(255) NOT NULL,
  day_offset INT NOT NULL,
  day_of_week SMALLINT DEFAULT NULL,
  hour_utc SMALLINT DEFAULT NULL,
  target_volume INT NOT NULL,
  increment_step INT NOT NULL,
  active TINYINT(1) NOT NULL,
  created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  INDEX idx_smr_warmup_schedule_scope_active (scope_type, scope_key, active),
  PRIMARY KEY(id),
  CONSTRAINT fk_smr_warmup_schedule_provider FOREIGN KEY (provider_id) REFERENCES smr_provider (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS smr_warmup_state (
  id INT AUTO_INCREMENT NOT NULL,
  provider_id CHAR(36) DEFAULT NULL,
  scope_type VARCHAR(16) NOT NULL,
  scope_key VARCHAR(255) NOT NULL,
  current_day INT NOT NULL,
  sent_today INT NOT NULL,
  current_daily_limit INT NOT NULL,
  current_hourly_limit INT NOT NULL,
  consecutive_healthy_days SMALLINT NOT NULL,
  consecutive_unhealthy_days SMALLINT NOT NULL,
  last_advanced_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  next_planned_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  notes JSON NOT NULL,
  created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  UNIQUE INDEX uniq_smr_warmup_state_scope (scope_type, scope_key),
  PRIMARY KEY(id),
  CONSTRAINT fk_smr_warmup_state_provider FOREIGN KEY (provider_id) REFERENCES smr_provider (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS smr_setting (
  id INT AUTO_INCREMENT NOT NULL,
  setting_key VARCHAR(190) NOT NULL,
  setting_value LONGTEXT DEFAULT NULL,
  updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  UNIQUE INDEX uniq_smr_setting_key (setting_key),
  PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS smr_delivery_log (
  id BIGINT AUTO_INCREMENT NOT NULL,
  provider_id CHAR(36) NOT NULL,
  routing_profile_id INT DEFAULT NULL,
  external_message_id VARCHAR(190) DEFAULT NULL,
  recipient VARCHAR(320) NOT NULL,
  sender VARCHAR(320) DEFAULT NULL,
  subject VARCHAR(255) DEFAULT NULL,
  domain VARCHAR(255) NOT NULL,
  status VARCHAR(32) NOT NULL,
  attempt_no SMALLINT NOT NULL,
  failover_count SMALLINT NOT NULL,
  selection_reason VARCHAR(64) DEFAULT NULL,
  http_status SMALLINT DEFAULT NULL,
  latency_ms INT DEFAULT NULL,
  failure_reason LONGTEXT DEFAULT NULL,
  metadata JSON NOT NULL,
  attempted_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  INDEX idx_smr_delivery_provider_attempted (provider_id, attempted_at),
  INDEX idx_smr_delivery_status_attempted (status, attempted_at),
  INDEX idx_smr_delivery_domain (domain),
  INDEX idx_smr_delivery_external_id (external_message_id),
  INDEX idx_smr_delivery_profile (routing_profile_id),
  PRIMARY KEY(id),
  CONSTRAINT fk_smr_delivery_provider FOREIGN KEY (provider_id) REFERENCES smr_provider (id) ON DELETE RESTRICT,
  CONSTRAINT fk_smr_delivery_profile FOREIGN KEY (routing_profile_id) REFERENCES smr_routing_profile (id) ON DELETE SET NULL
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS smr_delivery_log_archive (
  id BIGINT AUTO_INCREMENT NOT NULL,
  delivery_log_id BIGINT NOT NULL,
  provider_id CHAR(36) NOT NULL,
  routing_profile_id INT DEFAULT NULL,
  external_message_id VARCHAR(190) DEFAULT NULL,
  recipient VARCHAR(320) NOT NULL,
  sender VARCHAR(320) DEFAULT NULL,
  subject VARCHAR(255) DEFAULT NULL,
  domain VARCHAR(255) NOT NULL,
  status VARCHAR(32) NOT NULL,
  attempt_no SMALLINT NOT NULL,
  failover_count SMALLINT NOT NULL,
  selection_reason VARCHAR(64) DEFAULT NULL,
  http_status SMALLINT DEFAULT NULL,
  latency_ms INT DEFAULT NULL,
  failure_reason LONGTEXT DEFAULT NULL,
  metadata JSON NOT NULL,
  attempted_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  archived_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  UNIQUE INDEX uniq_smr_delivery_archive_source (delivery_log_id),
  INDEX idx_smr_delivery_archive_provider_attempted (provider_id, attempted_at),
  INDEX idx_smr_delivery_archive_status_attempted (status, attempted_at),
  INDEX idx_smr_delivery_archive_domain (domain),
  INDEX idx_smr_delivery_archive_external_id (external_message_id),
  INDEX idx_smr_delivery_archive_profile (routing_profile_id),
  PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS smr_retry_queue (
  id BIGINT AUTO_INCREMENT NOT NULL,
  message_id VARCHAR(128) NOT NULL,
  attempt INT NOT NULL,
  next_attempt_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  status VARCHAR(24) NOT NULL,
  provider_name VARCHAR(64) DEFAULT NULL,
  provider_code VARCHAR(64) DEFAULT NULL,
  routing_mode VARCHAR(32) DEFAULT NULL,
  command_json JSON DEFAULT NULL,
  last_error LONGTEXT DEFAULT NULL,
  created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  UNIQUE INDEX uniq_smr_retry_message (message_id),
  INDEX idx_smr_retry_status_next_attempt (status, next_attempt_at),
  PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL,
        ];
    }

    /**
     * @param \Doctrine\DBAL\Schema\AbstractSchemaManager<AbstractPlatform> $schemaManager
     */
    private function ensureRetryQueueColumns(\Doctrine\DBAL\Connection $connection, \Doctrine\DBAL\Schema\AbstractSchemaManager $schemaManager): void
    {
        $columns = $schemaManager->listTableColumns('smr_retry_queue');
        $columnNames = array_map(static fn ($column): string => strtolower($column->getName()), $columns);

        $missingColumns = [
            'provider_name' => 'ALTER TABLE smr_retry_queue ADD COLUMN provider_name VARCHAR(64) DEFAULT NULL',
            'provider_code' => 'ALTER TABLE smr_retry_queue ADD COLUMN provider_code VARCHAR(64) DEFAULT NULL',
            'routing_mode' => 'ALTER TABLE smr_retry_queue ADD COLUMN routing_mode VARCHAR(32) DEFAULT NULL',
            'command_json' => 'ALTER TABLE smr_retry_queue ADD COLUMN command_json JSON DEFAULT NULL',
        ];

        foreach ($missingColumns as $columnName => $sql) {
            if (!in_array($columnName, $columnNames, true)) {
                $connection->executeStatement($sql);
            }
        }
    }

    private function ensureDefaultSettings(\Doctrine\DBAL\Connection $connection): void
    {
        foreach (SmartMailerSettingsRepository::defaultValues() as $settingKey => $settingValue) {
            $connection->executeStatement(
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
