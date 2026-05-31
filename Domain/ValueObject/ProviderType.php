<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\ValueObject;

enum ProviderType: string
{
    case AMAZON_SES = 'amazon_ses';
    case SENDGRID = 'sendgrid';
    case MAILGUN = 'mailgun';
    case POSTAL = 'postal';
    case POWERMTA = 'powermta';
    case SMTP_GENERIC = 'smtp_generic';
    case SMTP_ONLY = 'smtp_only';
    case BREVO = 'brevo';
    case SPARKPOST = 'sparkpost';
    case RESEND = 'resend';
}
