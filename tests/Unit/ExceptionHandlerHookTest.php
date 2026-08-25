<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| The suite-wide exception-handler hook
|--------------------------------------------------------------------------
|
| Constructing a standalone Flick installs a global exception handler, so a
| test that builds one leaves a handler on the stack. That used to be undone by
| a beforeEach/afterEach pair hand-copied into ~66 files; it is now a single
| hook in Pest.php.
|
| What the hook is NOT for: leakage between tests. PHPUnit unwinds the handler
| stack itself, so the following test always sees the base handler whether the
| hook runs or not. What it does instead is flag the leaving test as risky, and
| phpunit.xml.dist sets failOnRisky="true" - so an uncovered directory turns
| every Flick-constructing test in it red rather than quietly green.
|
| The first test below is the fast, precise version of that signal: it fails
| the moment Pest.php's ->in() stops covering this directory, naming the cause
| instead of leaving 600-odd risky tests to be interpreted.
|
*/

function flickHookSentinel(Throwable $exception): void
{
    // Never invoked. It exists only so the handler stack carries a name this
    // file can recognise.
}

it('binds the hook to this file', function () {
    // beforeEach writes the property. Absent property, absent hook.
    expect(property_exists($this, 'previousExceptionHandler'))->toBeTrue();
});

it('leaves a handler on the stack the way a Flick construction does', function () {
    // Without the hook this test is the one PHPUnit reports as risky, which is
    // what makes the failure above concrete rather than theoretical.
    set_exception_handler('flickHookSentinel');

    expect(snapshotExceptionHandler())->toBe('flickHookSentinel');
});

it('unwinds a stack several handlers deep', function () {
    $base = snapshotExceptionHandler();

    set_exception_handler('flickHookSentinel');
    set_exception_handler('flickHookSentinel');
    set_exception_handler('flickHookSentinel');

    unwindExceptionHandlersTo($base);

    expect(snapshotExceptionHandler())->toBe($base);
});
