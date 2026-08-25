<?php

use Flick\App\View;
use Flick\Flick;
use Flick\Http\ArrayRequest;

/**
 * With `cache` on, Flick writes compiled views into <assets>/cache/views. The
 * docs show `assets` beside the form script, which on a layout with no public/
 * directory is inside the docroot — so those files are fetchable over HTTP.
 * Flick drops an Apache deny file into <assets>/cache to close that by default.
 *
 * Apache and LiteSpeed only. nginx and Caddy ignore .htaccess; the
 * configuration docs carry the guidance for everyone else.
 */
function denyGuardAssets(): string
{
    $dir = sys_get_temp_dir().'/flick-denyguard-'.bin2hex(random_bytes(4));
    mkdir($dir, 0755, true);
    file_put_contents($dir.'/static.view.php', 'Static markup only');

    return $dir;
}

function denyGuardRender(string $assets, bool $cache = true): void
{
    $flick = new Flick([
        'cache' => $cache,
        'assets' => $assets,
        'csrf' => false,
        'echo' => false,
        'request' => ArrayRequest::createGet(),
    ]);

    View::make(['templatePath' => $assets.'/static.view.php', 'value' => ''], $flick)->render();
}

function denyGuardRemove(string $dir): void
{
    // Existence checks rather than @-suppression: PHPUnit 10+ reports a
    // suppressed warning anyway, and two scenarios never create cache/.
    if (is_dir($dir.'/cache')) {
        chmod($dir.'/cache', 0755);
    }

    foreach (glob($dir.'/cache/views/*') ?: [] as $file) {
        unlink($file);
    }

    foreach ([$dir.'/cache/.htaccess', $dir.'/static.view.php'] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }

    foreach ([$dir.'/cache/views', $dir.'/cache', $dir] as $directory) {
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
}

it('writes an Apache deny file when it creates the cache directory', function () {
    $assets = denyGuardAssets();

    denyGuardRender($assets);

    expect($assets.'/cache/.htaccess')->toBeFile();

    denyGuardRemove($assets);
});

it('writes the deny file into a cache directory that already exists', function () {
    // Installs whose cache directory an older Flick created must be closed
    // too — not just fresh ones.
    $assets = denyGuardAssets();
    mkdir($assets.'/cache/views', 0755, true);

    denyGuardRender($assets);

    expect($assets.'/cache/.htaccess')->toBeFile();

    denyGuardRemove($assets);
});

it('denies access for both Apache 2.4 and 2.2', function () {
    $assets = denyGuardAssets();

    denyGuardRender($assets);

    $contents = file_get_contents($assets.'/cache/.htaccess');

    expect($contents)->toContain('Require all denied')   // 2.4
        ->and($contents)->toContain('Deny from all');    // 2.2

    denyGuardRemove($assets);
});

it('leaves an existing deny file alone', function () {
    // Never clobber a developer's own rules.
    $assets = denyGuardAssets();
    mkdir($assets.'/cache', 0755, true);
    file_put_contents($assets.'/cache/.htaccess', '# mine');

    denyGuardRender($assets);

    expect(file_get_contents($assets.'/cache/.htaccess'))->toBe('# mine');

    denyGuardRemove($assets);
});

it('still caches the view when the deny file cannot be written', function () {
    // Defense in depth must never be the thing that breaks form rendering.
    $assets = denyGuardAssets();
    mkdir($assets.'/cache/views', 0755, true);
    chmod($assets.'/cache', 0555);

    denyGuardRender($assets);

    expect(glob($assets.'/cache/views/*.html'))->not->toBeEmpty()
        ->and($assets.'/cache/.htaccess')->not->toBeFile();

    denyGuardRemove($assets);
});

it('writes no cache directory at all when caching is off', function () {
    $assets = denyGuardAssets();

    denyGuardRender($assets, cache: false);

    expect(is_dir($assets.'/cache'))->toBeFalse();

    denyGuardRemove($assets);
});
