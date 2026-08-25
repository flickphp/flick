<?php

declare(strict_types=1);

/*
 * examples/ ships in the dist, is referenced by nothing and was covered by
 * nothing. The 2026-08-15 audit flagged it as a keep-and-exercise-or-delete
 * decision to settle before 1.0; the decision was keep (2026-08-17), which only
 * means anything if the files are actually run.
 *
 * Each one executes in its OWN process: they are top-level scripts that
 * construct Flick instances and touch superglobals, so running them in the test
 * process would leak session and global state into everything after them. A
 * separate process also means a fatal shows up as a non-zero exit rather than
 * taking the suite down.
 *
 * They are not asserted on beyond "runs clean". They are documentation, and the
 * failure they exist to prevent is drifting into code that no longer works.
 */

/**
 * Run one example file in a fresh PHP process with the package autoloader.
 *
 * @return array{exit: int, output: string}
 */
function runFlickExample(string $file): array
{
    $root = dirname(__DIR__, 2);

    $bootstrap = sprintf(
        'require %s; require %s;',
        var_export($root.'/vendor/autoload.php', true),
        var_export($root.'/examples/'.$file, true),
    );

    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

    $process = proc_open(
        [PHP_BINARY, '-d', 'error_reporting=E_ALL', '-d', 'display_errors=1', '-r', $bootstrap],
        $descriptors,
        $pipes,
    );

    if (! is_resource($process)) {
        throw new RuntimeException("could not start a process for examples/{$file}");
    }

    $stdout = (string) stream_get_contents($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return ['exit' => proc_close($process), 'output' => $stdout.$stderr];
}

it('runs without error', function (string $file) {
    $result = runFlickExample($file);

    expect($result['exit'])->toBe(0, "examples/{$file} exited non-zero:\n".$result['output']);

    // A warning or deprecation is drift too: the example is telling a reader to
    // write something the library no longer wants.
    foreach (['Fatal error', 'Warning:', 'Deprecated:', 'Uncaught'] as $signal) {
        expect($result['output'])->not->toContain(
            $signal,
            "examples/{$file} emitted a {$signal}\n".$result['output']
        );
    }
})->with([
    'configuration.php',
    'create.php',
    'requests.php',
]);

it('covers every file in the examples directory', function () {
    $found = array_values(array_diff(
        scandir(dirname(__DIR__, 2).'/examples') ?: [],
        ['.', '..']
    ));

    // A new example must be added to the dataset above, or it ships unexercised
    // exactly as these three did.
    expect($found)->toBe(['configuration.php', 'create.php', 'requests.php']);
});
