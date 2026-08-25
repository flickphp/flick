<?php

use Flick\Flick;
use Flick\Support\FlickException;

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = [];
});

it('does not disclose the server file path on the exception page when debug is off (M6)', function () {
    $captured = null;

    Flick::resetDefaultRequest();

    $form = new Flick([
        'csrf' => false,
        'onException' => function ($response) use (&$captured) {
            $captured = $response->getContent();

            return null;
        },
    ]);

    $form->globalExceptionHandler(new RuntimeException('something went wrong'));

    expect($captured)
        ->toBeString()
        ->not->toContain('on line')
        ->and($captured)->not->toContain(__FILE__);
});

it('keeps every path-taking exception factory off the production page', function () {
    $sentinel = '/var/www/secret/db.php';

    // getMethods()'s filter mask is an OR, so public/static are re-checked here
    $factories = array_filter(
        (new ReflectionClass(FlickException::class))->getMethods(),
        function (ReflectionMethod $method) {
            $params = $method->getParameters();

            return $method->isPublic()
                && $method->isStatic()
                && $params !== []
                && $params[0]->getName() === 'path';
        }
    );

    // Guard the guard: a reflection filter that silently matched nothing
    // would make this whole test vacuous.
    expect(count($factories))->toBeGreaterThanOrEqual(11);

    foreach ($factories as $factory) {
        $args = [$sentinel];
        foreach (array_slice($factory->getParameters(), 1) as $param) {
            $args[] = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : 'x';
        }

        $page = renderExceptionPage($factory->invokeArgs(null, $args), debug: false)->getContent();

        expect($page)->not->toContain($sentinel, "{$factory->getName()}() leaked its path")
            ->and($page)->not->toContain('/var/www/secret');
    }
});

it('keeps a foreign throwable message off the production page', function () {
    $page = renderExceptionPage(new RuntimeException('could not open /var/www/secret/db.php'), debug: false)->getContent();

    expect($page)->not->toContain('/var/www/secret/db.php')
        ->and($page)->not->toContain('could not open');
});

it('keeps the developer remediation off the production page', function () {
    $page = renderExceptionPage(FlickException::serviceIsNotAvailable('mail'), debug: false)->getContent();

    expect($page)->not->toContain('$config')
        ->and($page)->not->toContain('hasService');
});

it('logs the developer payload when debug is off', function () {
    $log = sys_get_temp_dir().'/flick-exception-'.bin2hex(random_bytes(6)).'.log';
    $previous = ini_get('error_log');
    ini_set('error_log', $log);

    try {
        renderExceptionPage(FlickException::serviceIsNotAvailable('mail'), debug: false);
        $logged = file_exists($log) ? file_get_contents($log) : '';
    } finally {
        ini_set('error_log', $previous === false ? '' : $previous);
        if (file_exists($log)) {
            unlink($log);
        }
    }

    // What the page stops showing has to land somewhere the developer can
    // read it, or debug-off local development has nothing to go on.
    expect($logged)->toContain('The `mail` service is not available.')
        ->and($logged)->toContain('Service not available')
        ->and($logged)->toContain('Is the package that provides it installed')
        ->and($logged)->toContain("hasService('mail')")
        ->and($logged)->toContain('https://flickphp.com/services');
});

it('discloses the server file path when debug is on (M6)', function () {
    $captured = null;

    Flick::resetDefaultRequest();

    $form = new Flick([
        'csrf' => false,
        'debug' => true,
        'onException' => function ($response) use (&$captured) {
            $captured = $response->getContent();

            return null;
        },
    ]);

    $form->globalExceptionHandler(new RuntimeException('something went wrong'));

    expect($captured)->toContain('ExceptionDisclosureTest.php');
});

/*
 * A constructor check that throws before the global handler is installed
 * hands the developer PHP's default uncaught-exception output — a blank 500,
 * or a raw trace with display_errors — instead of Flick's card. Both checks
 * below live in the constructor, so the handler has to be in place first.
 */

// The handler Flick installs is class-owned and built once, so a throwaway
// standalone construction yields the exact callable a failing construction
// has to leave installed.
function flickExceptionDispatcher(): callable
{
    Flick::resetDefaultRequest();

    $before = snapshotExceptionHandler();

    new Flick(['csrf' => false, 'echo' => false]);

    $dispatcher = snapshotExceptionHandler();

    unwindExceptionHandlersTo($before);

    return $dispatcher;
}

it('renders a FlickException thrown during construction through its own handler', function () {
    $dispatcher = flickExceptionDispatcher();

    $before = snapshotExceptionHandler();
    $installed = null;

    try {
        new Flick([
            'assets' => sys_get_temp_dir().'/flick-no-such-dir-'.bin2hex(random_bytes(4)),
            'csrf' => false,
            'echo' => false,
        ]);
    } catch (FlickException) {
        $installed = snapshotExceptionHandler();
    } finally {
        unwindExceptionHandlersTo($before);
    }

    expect($installed)->toBe($dispatcher);
});

it('renders a missing language file thrown during construction through its own handler', function () {
    $dispatcher = flickExceptionDispatcher();

    $assets = sys_get_temp_dir().'/flick-assets-'.bin2hex(random_bytes(4));
    mkdir($assets);

    $before = snapshotExceptionHandler();
    $installed = null;

    try {
        new Flick([
            'assets' => $assets,
            'csrf' => false,
            'echo' => false,
            'lang' => 'xx',
        ]);
    } catch (FlickException) {
        $installed = snapshotExceptionHandler();
    } finally {
        unwindExceptionHandlersTo($before);
        rmdir($assets);
    }

    expect($installed)->toBe($dispatcher);
});
