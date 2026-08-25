<?php

use Flick\App\View;
use Flick\Flick;

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = [];
    $_GET = [];

    $this->assets = sys_get_temp_dir().'/flick-view-cache-'.uniqid();
    mkdir($this->assets, 0755, true);

    $this->viewFile = $this->assets.'/custom.view.php';

    $this->flick = new Flick([
        'csrf' => false,
        'echo' => false,
        'cache' => true,
        'assets' => $this->assets,
    ]);
});

afterEach(function () {
    // recursively remove the temp assets dir
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->assets, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($this->assets);
});

it('re-renders instead of serving a stale cache after the template changes (M14)', function () {
    file_put_contents($this->viewFile, 'VERSION-A static markup');

    $first = View::make(['templatePath' => $this->viewFile, 'value' => ''], $this->flick)->render();
    expect($first)->toContain('VERSION-A');

    // edit the template on disk
    file_put_contents($this->viewFile, 'VERSION-B static markup');

    $second = View::make(['templatePath' => $this->viewFile, 'value' => ''], $this->flick)->render();

    expect($second)->toContain('VERSION-B')
        ->and($second)->not->toContain('VERSION-A');
});

it('does not write a cache file when a per-field value is present (M14)', function () {
    file_put_contents($this->viewFile, 'Value is {{ value }}');

    View::make(['templatePath' => $this->viewFile, 'value' => 'user-5-name'], $this->flick)->render();

    $cacheDir = $this->assets.'/cache/views';
    $cacheFiles = is_dir($cacheDir) ? glob($cacheDir.'/*.html') : [];

    expect($cacheFiles)->toBeEmpty();
});

it('still caches value-independent markup (M14 regression)', function () {
    file_put_contents($this->viewFile, 'Static markup only');

    View::make(['templatePath' => $this->viewFile, 'value' => ''], $this->flick)->render();

    $cacheDir = $this->assets.'/cache/views';
    $cacheFiles = is_dir($cacheDir) ? glob($cacheDir.'/*.html') : [];

    expect($cacheFiles)->not->toBeEmpty();
});
