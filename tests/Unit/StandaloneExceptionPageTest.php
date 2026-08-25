<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Standalone error page (§2.6)
|--------------------------------------------------------------------------
|
| A missing view in standalone mode must still end in Flick's styled error
| page: the uncaught RuntimeException reaches Flick::globalExceptionHandler,
| which dispatches through the default handlers (HtmlResponse::send() and
| exit). That path ends the process, so it has to run in a subprocess.
|
*/

it('renders the styled error page for a missing view in standalone mode (§2.6)', function () {
    $script = <<<'PHP'
    <?php
    require $argv[1].'/vendor/autoload.php';

    $_SERVER['REQUEST_METHOD'] = 'GET';

    $form = new Flick\Flick(['echo' => false, 'csrf' => false, 'debug' => true]);
    $form->views->load('flick/nope.view.php');

    echo 'UNREACHABLE';
    PHP;

    $scriptPath = tempnam(sys_get_temp_dir(), 'flick-standalone-');
    file_put_contents($scriptPath, $script);

    // cmd.exe has no /dev/null; its bit bucket is NUL. Passing the POSIX form
    // on Windows makes the shell fail before PHP runs, which reads as a
    // non-zero exit and an empty page rather than as a wrong error page.
    $discardStderr = PHP_OS_FAMILY === 'Windows' ? '2>NUL' : '2>/dev/null';

    exec(sprintf(
        '%s %s %s %s',
        escapeshellarg(PHP_BINARY),
        escapeshellarg($scriptPath),
        escapeshellarg(dirname(__DIR__, 2)),
        $discardStderr,
    ), $output, $exitCode);

    unlink($scriptPath);

    $page = implode("\n", $output);

    expect($exitCode)->toBe(0)
        ->and($page)->toContain('View file not found')
        ->and($page)->toContain('nope.view.php')
        ->and($page)->not->toContain('UNREACHABLE')
        ->and($page)->not->toContain('Fatal error');
});
