<?php

use Flick\Flick;

/*
 * flushCache() deletes the compiled views under <assets>/cache/views and
 * reports the outcome. A failed deletion must be reported and never masked
 * by a later successful one.
 */

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';

    $this->assets = sys_get_temp_dir().'/flick-assets-'.uniqid();
    mkdir($this->assets.'/cache/views', 0755, true);
});

afterEach(function () {
    chmod($this->assets.'/cache/views', 0755);
    foreach (glob($this->assets.'/cache/views/*') ?: [] as $file) {
        // The undeletable-file test leaves one read-only on Windows; clear the
        // attribute or the unlink below fails and the temp dir leaks.
        chmod($file, 0644);
        unlink($file);
    }
    rmdir($this->assets.'/cache/views');

    // Only written when a view is actually compiled, which these tests do not
    // do — guarded rather than silenced with `@`, because phpunit.xml.dist
    // sets failOnWarning and a suppressed diagnostic still gets reported.
    if (is_file($this->assets.'/cache/.htaccess')) {
        unlink($this->assets.'/cache/.htaccess');
    }

    rmdir($this->assets.'/cache');
    rmdir($this->assets);
});

it('deletes cached view files and reports success', function () {
    file_put_contents($this->assets.'/cache/views/view.php', 'cached');

    $form = new Flick(['assets' => $this->assets, 'csrf' => false]);

    ob_start();
    $form->flushCache();
    $output = ob_get_clean();

    $applicationMessages = require __DIR__.'/../../lang/en/messages.php';

    expect(file_exists($this->assets.'/cache/views/view.php'))->toBeFalse()
        ->and($output)->toContain($applicationMessages['AllCachedViewFilesWereDeleted']);
});

it('reports a warning when a cached view file cannot be deleted', function () {
    file_put_contents($this->assets.'/cache/views/view.php', 'cached');

    // Make the unlink fail. The two platforms disagree about who owns delete
    // permission, so the setup has to differ even though the behaviour under
    // test is the same:
    //
    //   POSIX   the right to remove a name belongs to the DIRECTORY, and a
    //           read-only file in a writable directory deletes fine. So the
    //           directory is the thing to lock.
    //   Windows there is no such directory right; DeleteFile refuses a file
    //           carrying the read-only attribute, which is what chmod sets
    //           here. Locking the directory instead would do nothing.
    if (PHP_OS_FAMILY === 'Windows') {
        chmod($this->assets.'/cache/views/view.php', 0444);
    } else {
        chmod($this->assets.'/cache/views', 0555);
    }

    $form = new Flick(['assets' => $this->assets, 'csrf' => false]);

    // The failing unlink emits an expected E_WARNING; the behaviour under
    // test (the reported warning message) is asserted below.
    set_error_handler(fn () => true);
    ob_start();
    $form->flushCache();
    $output = ob_get_clean();
    restore_error_handler();

    $applicationMessages = require __DIR__.'/../../lang/en/messages.php';

    expect($output)->toContain($applicationMessages['SomeFilesCouldNotBeDeleted'])
        ->and($output)->not->toContain($applicationMessages['AllCachedViewFilesWereDeleted']);
});
