<?php

declare(strict_types=1);

use Flick\Flick;
use Flick\Http\ArrayRequest;
use Flick\Session\ArraySession;

afterEach(function () {
    Flick::resetDefaultConfig();
});

it('applies default config to a bare new Flick()', function () {
    Flick::setDefaultConfig(['id' => 'publishedForm', 'views' => 'tailwind']);

    $form = new Flick;

    expect($form->config('id'))->toBe('publishedForm');
    expect($form->config('views'))->toBe('tailwind');
});

it('lets instance config override the default', function () {
    Flick::setDefaultConfig(['id' => 'publishedForm', 'views' => 'tailwind']);

    $form = new Flick(['id' => 'explicitForm']);

    expect($form->config('id'))->toBe('explicitForm');
    // untouched default still applies
    expect($form->config('views'))->toBe('tailwind');
});

it('merges the default beneath a string (views shorthand) config', function () {
    Flick::setDefaultConfig(['echo' => false]);

    $form = new Flick('bootstrap');

    expect($form->config('views'))->toBe('bootstrap');
    expect($form->config('echo'))->toBeFalse();
});

it('deep-merges nested default config keys', function () {
    Flick::setDefaultConfig(['rules' => ['name' => ['required']]]);

    $form = new Flick(['rules' => ['email' => ['email']]]);

    expect($form->config('rules'))->toBe([
        'name' => ['required'],
        'email' => ['email'],
    ]);
});

it('is a no-op when no default is set', function () {
    Flick::resetDefaultConfig();

    $form = new Flick(['id' => 'plain']);

    expect($form->config('id'))->toBe('plain');
    expect(Flick::getDefaultConfig())->toBe([]);
});

it('honours an action published as a framework default', function () {
    // The Laravel adapter bridges config/flick.php through setDefaultConfig(),
    // which merges beneath the instance config before setApplicationConfig()
    // runs -- so a framework-supplied action counts as configured.
    Flick::setDefaultConfig(['action' => '/framework-route']);

    $form = new Flick([
        'request' => new ArrayRequest(['server' => ['REQUEST_URI' => '/contact']]),
        'session' => new ArraySession,
        'echo' => false,
        'csrf' => false,
    ]);

    preg_match('/<form[^>]*\baction="([^"]*)"/', $form->open(), $matches);

    expect($matches[1] ?? '')->toBe('/framework-route');
});
