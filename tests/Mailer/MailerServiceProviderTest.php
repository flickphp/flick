<?php

use Flick\Mailer\Mailer;
use Flick\Mailer\MailerServiceProvider;
use Flick\Support\Support;

beforeEach(function () {
    $this->config = [
        'fromAddress' => 'noreply@example.com',
        'fromName' => 'Test App',
        'mailer' => [
            'transport' => 'mail',
        ],
    ];
    $this->support = Mockery::mock(Support::class)->makePartial();
    $this->container = Mockery::mock('Container');
    $this->provider = new MailerServiceProvider;
});

afterEach(function () {
    Mockery::close();
});

describe('MailerServiceProvider::setConfig()', function () {

    it('accepts and stores configuration', function () {
        $this->provider->setConfig($this->config);

        $reflection = new ReflectionClass($this->provider);
        $property = $reflection->getProperty('config');

        expect($property->getValue($this->provider))->toBe($this->config);
    });

});

describe('MailerServiceProvider::setSupport()', function () {

    it('accepts and stores support instance', function () {
        $this->provider->setSupport($this->support);

        $reflection = new ReflectionClass($this->provider);
        $property = $reflection->getProperty('support');

        expect($property->getValue($this->provider))->toBe($this->support);
    });

});

describe('MailerServiceProvider::register()', function () {

    it('registers mail service in container', function () {
        $this->provider->setConfig($this->config);
        $this->provider->setSupport($this->support);

        $this->container->shouldReceive('set')
            ->once()
            ->with('mail', Mockery::type('callable'));

        $this->provider->register($this->container);
    });

    it('registered factory returns Mailer instance with mail transport', function () {
        $this->provider->setConfig($this->config);
        $this->provider->setSupport($this->support);

        $capturedCallback = null;
        $this->container->shouldReceive('set')
            ->once()
            ->with('mail', Mockery::on(function ($callback) use (&$capturedCallback) {
                $capturedCallback = $callback;

                return is_callable($callback);
            }));

        $this->provider->register($this->container);

        $result = $capturedCallback();
        expect($result)->toBeInstanceOf(Mailer::class);
    });

    it('registered factory returns Mailer instance with smtp transport', function () {
        $smtpConfig = [
            'fromAddress' => 'noreply@example.com',
            'mailer' => [
                'transport' => 'smtp',
                'host' => 'smtp.example.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'user',
                'password' => 'pass',
            ],
        ];

        $this->provider->setConfig($smtpConfig);
        $this->provider->setSupport($this->support);

        $capturedCallback = null;
        $this->container->shouldReceive('set')
            ->once()
            ->with('mail', Mockery::on(function ($callback) use (&$capturedCallback) {
                $capturedCallback = $callback;

                return is_callable($callback);
            }));

        $this->provider->register($this->container);

        $result = $capturedCallback();
        expect($result)->toBeInstanceOf(Mailer::class);
    });

});

describe('MailerServiceProvider config validation', function () {

    it('throws exception when fromAddress is missing', function () {
        $invalidConfig = [
            'mailer' => ['transport' => 'mail'],
        ];

        $this->provider->setConfig($invalidConfig);
        $this->provider->setSupport($this->support);

        $capturedCallback = null;
        $this->container->shouldReceive('set')
            ->once()
            ->with('mail', Mockery::on(function ($callback) use (&$capturedCallback) {
                $capturedCallback = $callback;

                return is_callable($callback);
            }));

        $this->provider->register($this->container);

        $capturedCallback();
    })->throws(InvalidArgumentException::class, 'fromAddress');

    it('throws exception when mailer config is missing', function () {
        $invalidConfig = [
            'fromAddress' => 'noreply@example.com',
        ];

        $this->provider->setConfig($invalidConfig);
        $this->provider->setSupport($this->support);

        $capturedCallback = null;
        $this->container->shouldReceive('set')
            ->once()
            ->with('mail', Mockery::on(function ($callback) use (&$capturedCallback) {
                $capturedCallback = $callback;

                return is_callable($callback);
            }));

        $this->provider->register($this->container);

        $capturedCallback();
    })->throws(InvalidArgumentException::class, 'mailer');

    it('throws exception when transport is missing', function () {
        $invalidConfig = [
            'fromAddress' => 'noreply@example.com',
            'mailer' => [],
        ];

        $this->provider->setConfig($invalidConfig);
        $this->provider->setSupport($this->support);

        $capturedCallback = null;
        $this->container->shouldReceive('set')
            ->once()
            ->with('mail', Mockery::on(function ($callback) use (&$capturedCallback) {
                $capturedCallback = $callback;

                return is_callable($callback);
            }));

        $this->provider->register($this->container);

        $capturedCallback();
    })->throws(InvalidArgumentException::class, 'transport');

    it('throws exception for unsupported transport', function () {
        $invalidConfig = [
            'fromAddress' => 'noreply@example.com',
            'mailer' => ['transport' => 'sendgrid'],
        ];

        $this->provider->setConfig($invalidConfig);
        $this->provider->setSupport($this->support);

        $capturedCallback = null;
        $this->container->shouldReceive('set')
            ->once()
            ->with('mail', Mockery::on(function ($callback) use (&$capturedCallback) {
                $capturedCallback = $callback;

                return is_callable($callback);
            }));

        $this->provider->register($this->container);

        $capturedCallback();
    })->throws(InvalidArgumentException::class, 'sendgrid');

});
