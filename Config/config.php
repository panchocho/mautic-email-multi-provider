<?php

declare(strict_types=1);

return [
    'name'        => 'Smart Mailer Router',
    'description' => 'Enterprise-grade routing and deliverability optimization for Mautic 7.x.',
    'version'     => '0.1.4',
    'author'      => 'OpenAI',
    'routes'      => [
        'public' => [
            'smart_mailer_resend_webhook' => [
                'path'       => '/smart-mailer/resend/webhook',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Webhook\ResendWebhookController::class,
                'method'     => 'POST',
            ],
        ],
        'main' => [
            'smart_mailer_admin_dashboard' => [
                'path'       => '/admin/smart-mailer',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\DashboardAdminController::class,
                'method'     => 'GET',
            ],
            'smart_mailer_admin_settings' => [
                'path'       => '/admin/smart-mailer/settings',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\SettingsAdminController::class,
                'method'     => 'GET',
            ],
            'smart_mailer_admin_settings_save' => [
                'path'       => '/admin/smart-mailer/settings',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\SettingsAdminController::class.'::save',
                'method'     => 'POST',
            ],
            'smart_mailer_admin_providers' => [
                'path'       => '/admin/smart-mailer/providers',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\ProvidersAdminController::class,
                'method'     => 'GET',
            ],
            'smart_mailer_admin_bindings' => [
                'path'       => '/admin/smart-mailer/bindings',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\BindingsAdminController::class,
                'method'     => 'GET',
            ],
            'smart_mailer_admin_provider_create' => [
                'path'       => '/admin/smart-mailer/providers/create',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\ProvidersAdminController::class.'::create',
                'method'     => 'POST',
            ],
            'smart_mailer_admin_binding_create' => [
                'path'       => '/admin/smart-mailer/bindings/create',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\BindingsAdminController::class.'::create',
                'method'     => 'POST',
            ],
            'smart_mailer_admin_provider_update' => [
                'path'       => '/admin/smart-mailer/providers/{id}/update',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\ProvidersAdminController::class.'::update',
                'method'     => 'POST',
            ],
            'smart_mailer_admin_binding_update' => [
                'path'       => '/admin/smart-mailer/bindings/{id}/update',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\BindingsAdminController::class.'::update',
                'method'     => 'POST',
            ],
            'smart_mailer_admin_provider_delete' => [
                'path'       => '/admin/smart-mailer/providers/{id}/delete',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\ProvidersAdminController::class.'::delete',
                'method'     => 'POST',
            ],
            'smart_mailer_admin_binding_delete' => [
                'path'       => '/admin/smart-mailer/bindings/{id}/delete',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\BindingsAdminController::class.'::delete',
                'method'     => 'POST',
            ],
            'smart_mailer_admin_campaign_routing' => [
                'path'       => '/admin/smart-mailer/campaign-routing',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\CampaignRoutingAdminController::class,
                'method'     => 'GET',
            ],
            'smart_mailer_admin_campaign_routing_save' => [
                'path'       => '/admin/smart-mailer/campaign-routing/{emailId}',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\CampaignRoutingAdminController::class.'::save',
                'method'     => 'POST',
            ],
            'smart_mailer_admin_profiles' => [
                'path'       => '/admin/smart-mailer/profiles',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\ProfilesAdminController::class,
                'method'     => 'GET',
            ],
            'smart_mailer_admin_profile_create' => [
                'path'       => '/admin/smart-mailer/profiles/create',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\ProfilesAdminController::class.'::create',
                'method'     => 'POST',
            ],
            'smart_mailer_admin_profile_update' => [
                'path'       => '/admin/smart-mailer/profiles/{id}/update',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\ProfilesAdminController::class.'::update',
                'method'     => 'POST',
            ],
            'smart_mailer_admin_profile_delete' => [
                'path'       => '/admin/smart-mailer/profiles/{id}/delete',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\ProfilesAdminController::class.'::delete',
                'method'     => 'POST',
            ],
            'smart_mailer_admin_rules' => [
                'path'       => '/admin/smart-mailer/rules',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\RulesAdminController::class,
                'method'     => 'GET',
            ],
            'smart_mailer_admin_rule_create' => [
                'path'       => '/admin/smart-mailer/rules/create',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\RulesAdminController::class.'::create',
                'method'     => 'POST',
            ],
            'smart_mailer_admin_rule_update' => [
                'path'       => '/admin/smart-mailer/rules/{id}/update',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\RulesAdminController::class.'::update',
                'method'     => 'POST',
            ],
            'smart_mailer_admin_rule_delete' => [
                'path'       => '/admin/smart-mailer/rules/{id}/delete',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\RulesAdminController::class.'::delete',
                'method'     => 'POST',
            ],
            'smart_mailer_admin_health' => [
                'path'       => '/admin/smart-mailer/health',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\HealthAdminController::class,
                'method'     => 'GET',
            ],
            'smart_mailer_admin_health_recompute_provider' => [
                'path'       => '/admin/smart-mailer/health/providers/{id}/recompute',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\HealthAdminController::class.'::recomputeProvider',
                'method'     => 'POST',
            ],
            'smart_mailer_admin_health_quarantine_provider' => [
                'path'       => '/admin/smart-mailer/health/providers/{id}/quarantine',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\HealthAdminController::class.'::quarantineProvider',
                'method'     => 'POST',
            ],
            'smart_mailer_admin_health_unquarantine_provider' => [
                'path'       => '/admin/smart-mailer/health/providers/{id}/unquarantine',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\HealthAdminController::class.'::unquarantineProvider',
                'method'     => 'POST',
            ],
            'smart_mailer_admin_warmup' => [
                'path'       => '/admin/smart-mailer/warmup',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\WarmupAdminController::class,
                'method'     => 'GET',
            ],
            'smart_mailer_admin_warmup_schedule_create' => [
                'path'       => '/admin/smart-mailer/warmup/schedules/create',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\WarmupAdminController::class.'::createSchedule',
                'method'     => 'POST',
            ],
            'smart_mailer_admin_warmup_schedule_toggle' => [
                'path'       => '/admin/smart-mailer/warmup/schedules/{id}/toggle',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\WarmupAdminController::class.'::toggleSchedule',
                'method'     => 'POST',
            ],
            'smart_mailer_admin_warmup_schedule_delete' => [
                'path'       => '/admin/smart-mailer/warmup/schedules/{id}/delete',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\WarmupAdminController::class.'::deleteSchedule',
                'method'     => 'POST',
            ],
            'smart_mailer_admin_logs' => [
                'path'       => '/admin/smart-mailer/logs',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\LogsAdminController::class,
                'method'     => 'GET',
            ],
            'smart_mailer_admin_logs_maintenance_sweep' => [
                'path'       => '/admin/smart-mailer/logs/maintenance/sweep',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\LogsAdminController::class.'::maintenanceSweep',
                'method'     => 'POST',
            ],
            'smart_mailer_admin_delivery_bulk_action' => [
                'path'       => '/admin/smart-mailer/logs/delivery/bulk/{action}',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\LogsAdminController::class.'::bulkDelivery',
                'method'     => 'POST',
            ],
            'smart_mailer_admin_retry_bulk_action' => [
                'path'       => '/admin/smart-mailer/logs/retry/bulk/{action}',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\LogsAdminController::class.'::bulkRetry',
                'method'     => 'POST',
            ],
            'smart_mailer_admin_logs_process_retries' => [
                'path'       => '/admin/smart-mailer/logs/retry/process',
                'controller' => MauticPlugin\SmartMailerRouterBundle\UI\Controller\Admin\LogsAdminController::class.'::processRetries',
                'method'     => 'POST',
            ],
        ],
    ],
    'menu' => [
        'admin' => [
            'priority' => 60,
            'items'    => [
                'smart_mailer.router' => [
                    'id'        => 'smart_mailer_router',
                    'route'     => 'smart_mailer_admin_dashboard',
                    'parent'    => 'mautic.core.integrations',
                    'priority'  => 5,
                    'iconClass' => 'ri-route-line',
                ],
            ],
        ],
    ],
    'parameters' => [
        'smart_mailer_router' => [
            'default_profile'  => 'default',
            'default_strategy' => 'round_robin',
            'queues'           => [
                'routing'     => 'smart_mailer.routing',
                'warmup'      => 'smart_mailer.warmup',
                'logs'        => 'smart_mailer.logs',
                'dead_letter' => 'smart_mailer.dead_letter',
            ],
            'health' => [
                'min_score'           => 60,
                'bounce_threshold'    => 0.03,
                'complaint_threshold'  => 0.001,
                'defer_threshold'     => 0.06,
            ],
            'warmup' => [
                'enabled'      => true,
                'default_ramp' => [100, 500, 1000, 2500, 5000],
            ],
            'retry' => [
                'max_retries'   => 8,
                'base_delay_ms' => 2000,
                'multiplier'    => 2.0,
                'max_delay_ms'  => 300000,
            ],
            'maintenance' => [
                'delivery_log_retention_days' => 90,
                'delivery_archive_retention_days' => 180,
                'health_history_retention_days' => 180,
                'retry_retention_days'         => 30,
            ],
        ],
    ],
];
