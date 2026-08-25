<?php

use Flick\Flick;
use Flick\Http\ArrayRequest;
use Flick\Support\FlickException;

/**
 * guide/configuration.md, `assets`: "The directory must already exist. Flick
 * throws a path error at instantiation if it doesn't." Beyond that check,
 * Flick only reads from the directory — `cache` is the one setting that
 * writes into it, so writability is demanded only then.
 */
function assetsForm(array $config = []): Flick
{
    return new Flick(array_merge([
        'csrf' => false,
        'echo' => false,
        'request' => ArrayRequest::createGet(),
    ], $config));
}

beforeEach(function () {
    $this->assets = sys_get_temp_dir().'/flick-assets-dir-'.bin2hex(random_bytes(4));
    mkdir($this->assets, 0755, true);
});

afterEach(function () {
    // Nothing below is guaranteed to exist — with caching off Flick creates
    // none of it. Each removal is guarded rather than silenced with `@`,
    // because phpunit.xml.dist sets failOnWarning and PHPUnit's error handler
    // reports a suppressed diagnostic all the same.
    chmod($this->assets, 0755);

    foreach (glob($this->assets.'/cache/views/*') ?: [] as $file) {
        unlink($file);
    }

    if (is_file($this->assets.'/cache/.htaccess')) {
        unlink($this->assets.'/cache/.htaccess');
    }

    foreach (['/cache/views', '/cache', ''] as $directory) {
        if (is_dir($this->assets.$directory)) {
            rmdir($this->assets.$directory);
        }
    }
});

it('throws at instantiation when the assets directory does not exist', function () {
    $missing = $this->assets.'/nope';

    try {
        assetsForm(['assets' => $missing]);
        $this->fail('Expected a path error');
    } catch (FlickException $e) {
        expect($e->heading)->toBe('Path error')
            ->and($e->getMessage())->toContain('/nope');
    }
});

it('treats an empty assets value as unset', function () {
    expect(fn () => assetsForm(['assets' => '']))->not->toThrow(FlickException::class);
});

it('creates nothing inside the assets directory when caching is off', function () {
    $before = scandir($this->assets);

    assetsForm(['assets' => $this->assets]);

    expect(scandir($this->assets))->toBe($before);
});

it('accepts a read-only assets directory when caching is off', function () {
    chmod($this->assets, 0555);

    expect(fn () => assetsForm(['assets' => $this->assets]))->not->toThrow(FlickException::class);
});

it('throws when caching is on and the assets directory is read-only', function () {
    chmod($this->assets, 0555);

    try {
        assetsForm(['assets' => $this->assets, 'cache' => true]);
        $this->fail('Expected a permissions error');
    } catch (FlickException $e) {
        expect($e->heading)->toBe('Permissions error')
            ->and($e->getMessage())->toContain('not writable');
    }
});
