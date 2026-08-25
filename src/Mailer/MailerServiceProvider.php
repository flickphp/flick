<?php

declare(strict_types=1);

namespace Flick\Mailer;

use Flick\Mailer\Message\FormDataFormatter;
use Flick\Mailer\Transport\PhpMailTransport;
use Flick\Mailer\Transport\SmtpTransport;
use Flick\Mailer\Transport\TransportInterface;
use Flick\Service\ServiceProvider;
use Flick\Support\Support;
use InvalidArgumentException;

class MailerServiceProvider implements ServiceProvider
{
    protected array $config;

    protected Support $support;

    public function register(mixed $container): void
    {
        $container->set('mail', function () {
            $this->validateConfig();

            $transport = $this->createTransport();
            $formDataFormatter = new FormDataFormatter;

            return new Mailer(
                $this->config,
                $this->support,
                $transport,
                $formDataFormatter
            );
        });
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function setSupport(Support $support): void
    {
        $this->support = $support;
    }

    protected function validateConfig(): void
    {
        if (empty($this->config['fromAddress'])) {
            throw new InvalidArgumentException(
                "Mail configuration requires 'fromAddress'. ".
                "Example: 'mail' => ['fromAddress' => 'noreply@example.com', ...]"
            );
        }

        if (empty($this->config['mailer']) || ! is_array($this->config['mailer'])) {
            throw new InvalidArgumentException(
                "Mail configuration requires 'mailer' array with transport settings. ".
                "Example: 'mail' => ['mailer' => ['transport' => 'mail'], ...]"
            );
        }

        if (empty($this->config['mailer']['transport'])) {
            throw new InvalidArgumentException(
                "Mail configuration requires 'mailer.transport'. ".
                "Supported in core: 'mail', 'smtp'. For more transports, upgrade to Flick Pro."
            );
        }

        $transport = $this->config['mailer']['transport'];
        if (! in_array($transport, ['mail', 'smtp'], true)) {
            throw new InvalidArgumentException(
                "Unsupported mail transport: '{$transport}'. ".
                "Core supports: 'mail', 'smtp'. ".
                'For SendGrid, Mailgun, Postmark, SES, etc., upgrade to Flick Pro.'
            );
        }
    }

    protected function createTransport(): TransportInterface
    {
        $transport = $this->config['mailer']['transport'];

        return match ($transport) {
            'mail' => new PhpMailTransport,
            'smtp' => new SmtpTransport($this->config['mailer']),
            default => throw new InvalidArgumentException("Unknown transport: {$transport}"),
        };
    }
}
