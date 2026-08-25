<?php

declare(strict_types=1);

use Flick\Flick;

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = [];
    $_GET = [];

    $this->form = new Flick([
        'id' => 'myform',
        'csrf' => false,
        'echo' => false,
    ]);
});

// Bug #29a (loadView returning false) retired 2026-08-11: loadView and the
// Exceptions class were deleted with the FlickException/ExceptionRenderer
// redesign.

// Bug #29b — slug must not drop non-Latin letters when some ASCII survives.
it('preserves non-Latin letters alongside surviving digits (#29b)', function () {
    $slug = $this->form->slug('Тест 2026');

    expect($slug)->toContain('Тест');
    expect($slug)->toBe('Тест_2026');
});

it('still transliterates accented Latin to ASCII', function () {
    expect($this->form->slug('café'))->toBe('cafe');
});

// Bug #29c — config() dot-path must not descend into a string value.
it('returns null for a dot-path into a string config value (#29c)', function () {
    expect($this->form->config('id'))->toBe('myform');
    expect($this->form->config('id.0'))->toBeNull();
});

// Bug #29d — flushCache() must restore the caller's cache setting.
it('restores the cache config after flushing (#29d)', function () {
    $assets = sys_get_temp_dir().'/flick-helper-assets-'.uniqid();
    mkdir($assets.'/cache/views', 0755, true);

    try {
        $form = new Flick([
            'assets' => $assets,
            'cache' => true,
            'csrf' => false,
            'echo' => false,
        ]);

        $form->flushCache();

        expect($form->config('cache'))->toBe(true);
    } finally {
        foreach (glob($assets.'/cache/views/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($assets.'/cache/views');
        @rmdir($assets.'/cache');
        @rmdir($assets);
    }
});
