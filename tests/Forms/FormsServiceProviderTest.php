<?php

use Flick\Forms\Forms;
use Flick\Forms\FormsServiceProvider;
use Flick\Support\Support;

beforeEach(function () {
    $this->config = ['form' => ['lang' => 'en']];
    $this->support = Mockery::mock(Support::class)->makePartial();
    $this->container = Mockery::mock('Container');
    $this->provider = new FormsServiceProvider;
});

afterEach(function () {
    Mockery::close();
});

describe('FormsServiceProvider::setConfig()', function () {

    it('accepts and stores configuration', function () {
        $this->provider->setConfig($this->config);

        $reflection = new ReflectionClass($this->provider);
        $property = $reflection->getProperty('config');

        expect($property->getValue($this->provider))->toBe($this->config);
    });

    it('accepts empty configuration', function () {
        $this->provider->setConfig([]);

        $reflection = new ReflectionClass($this->provider);
        $property = $reflection->getProperty('config');

        expect($property->getValue($this->provider))->toBe([]);
    });

});

describe('FormsServiceProvider::setSupport()', function () {

    it('accepts and stores support instance', function () {
        $this->provider->setSupport($this->support);

        $reflection = new ReflectionClass($this->provider);
        $property = $reflection->getProperty('support');

        expect($property->getValue($this->provider))->toBe($this->support);
    });

});

describe('FormsServiceProvider::register()', function () {

    it('registers forms service in container', function () {
        $this->provider->setConfig($this->config);
        $this->provider->setSupport($this->support);

        $this->container->shouldReceive('set')
            ->once()
            ->with('forms', Mockery::type('callable'));

        $this->provider->register($this->container);
    });

    it('registered factory returns Forms instance', function () {
        $this->provider->setConfig($this->config);
        $this->provider->setSupport($this->support);

        $capturedCallback = null;
        $this->container->shouldReceive('set')
            ->once()
            ->with('forms', Mockery::on(function ($callback) use (&$capturedCallback) {
                $capturedCallback = $callback;

                return is_callable($callback);
            }));

        $this->provider->register($this->container);

        $result = $capturedCallback();
        expect($result)->toBeInstanceOf(Forms::class);
    });

});
