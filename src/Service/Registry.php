<?php

declare(strict_types=1);

namespace Flick\Service;

/**
 * Where service packages announce their provider.
 *
 * A package registers itself from a file Composer includes on every request
 * (listed under `autoload.files` in its composer.json), so the list is
 * complete before any `new Flick()` runs. Nothing is scanned and nothing is
 * written to disk. The installed.json scan and its services.json cache this
 * replaced (removed 2026-08-21) saved about a millisecond per request and
 * cost ~320 lines, three fixed bugs, and a write into the assets directory.
 *
 * An application can register a provider of its own the same way, before it
 * builds a form — no package required:
 *
 *     Registry::add('foo', FooServiceProvider::class);
 *
 * add() stores the class name and does nothing else: it runs at autoload time
 * on every request of the host application, so it must never touch the
 * autoloader. ServiceManager::registerServices() validates the class when a
 * form is built. Last registration for a name wins.
 *
 * There is deliberately no reset(). Pro's own test suite receives `pro` from
 * the autoload file once per process, and a reset in one test would
 * unregister it for every test that follows. Tests that add a provider
 * remove that one name when they are done.
 */
final class Registry
{
    /** @var array<string, class-string<ServiceProvider>> name => provider class */
    private static array $providers = [];

    /**
     * @param  class-string<ServiceProvider>  $provider
     */
    public static function add(string $name, string $provider): void
    {
        self::$providers[$name] = $provider;
    }

    public static function remove(string $name): void
    {
        unset(self::$providers[$name]);
    }

    /**
     * @return array<string, class-string<ServiceProvider>>
     */
    public static function all(): array
    {
        return self::$providers;
    }
}
