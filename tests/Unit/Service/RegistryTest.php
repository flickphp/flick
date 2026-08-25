<?php

use Flick\Service\Registry;

/**
 * The Registry is the only way a service package reaches Flick: its autoload
 * file calls add() on every request, and ServiceManager reads all() when a
 * form is built. It stores strings and nothing else — see the class docblock
 * for why it must not touch the autoloader.
 */
afterEach(function () {
    Registry::remove('registry-test');
});

it('does not list a name nobody registered', function () {
    expect(Registry::all())->not->toHaveKey('registry-test');
});

it('returns a registered provider under its name', function () {
    Registry::add('registry-test', 'Flick\\Tests\\Fake\\One');

    expect(Registry::all())->toHaveKey('registry-test')
        ->and(Registry::all()['registry-test'])->toBe('Flick\\Tests\\Fake\\One');
});

it('lets a later registration replace an earlier one', function () {
    Registry::add('registry-test', 'Flick\\Tests\\Fake\\One');
    Registry::add('registry-test', 'Flick\\Tests\\Fake\\Two');

    expect(Registry::all()['registry-test'])->toBe('Flick\\Tests\\Fake\\Two');
});

it('forgets a removed provider', function () {
    Registry::add('registry-test', 'Flick\\Tests\\Fake\\One');
    Registry::remove('registry-test');

    expect(Registry::all())->not->toHaveKey('registry-test');
});

it('does not ask the autoloader about the class when registering', function () {
    // add() runs from Composer's autoload.files on every request of the host
    // app, so it must be a plain array write. Validation happens when a form
    // is built, in ServiceManager::registerServices().
    $requested = [];
    $spy = function (string $class) use (&$requested): void {
        $requested[] = $class;
    };

    spl_autoload_register($spy, prepend: true);

    try {
        Registry::add('registry-test', 'Flick\\Tests\\Fake\\NeverLoaded');
    } finally {
        spl_autoload_unregister($spy);
    }

    expect($requested)->not->toContain('Flick\\Tests\\Fake\\NeverLoaded');
});
