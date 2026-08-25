<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

// uses(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Exception-handler stack hygiene
|--------------------------------------------------------------------------
|
| PHP keeps exception handlers on a stack: set_exception_handler() always
| pushes and restore_exception_handler() pops — there is no way to read the
| active handler without pushing. Every standalone `new Flick()` pushes a
| global handler, so tests snapshot the active handler in beforeEach and
| unwind back to it in afterEach. Pinned by ExceptionHandlerHygieneTest.
|
| Registered once, here, for the whole tests directory. It used to be copied
| into 66 test files by hand, which meant a new test file silently opted out of
| the invariant by forgetting it — and naming suite directories individually
| would have left the same gap for a directory added later.
|
| The three files that drive the handler stack inside their own test bodies
| keep their own beforeEach/afterEach as well. Unwinding to a snapshot that is
| already active is a no-op, so the two layers do not fight.
|
| Skipping the hook is not silently survivable: PHPUnit reports a test that
| leaves a handler behind as risky, and phpunit.xml.dist sets failOnRisky.
| ExceptionHandlerHookTest turns that into a named failure.
*/
pest()
    ->beforeEach(function () {
        // Every test starts from the same request state. This runs BEFORE a
        // file's own beforeEach (verified), so a file that sets up its own
        // $_POST still wins - this only clears what the previous test left.
        $_POST = [];
        $_GET = [];
        $_FILES = [];
        $_SESSION = [];
        $_REQUEST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->previousExceptionHandler = snapshotExceptionHandler();
    })
    ->afterEach(function () {
        unwindExceptionHandlersTo($this->previousExceptionHandler);
    })
    ->in(__DIR__);

// The active exception handler, read without growing the handler stack.
function snapshotExceptionHandler(): ?callable
{
    $handler = set_exception_handler(null);
    restore_exception_handler();

    return $handler;
}

// Pop handlers until $previous is active again (or the stack is empty),
// undoing whatever a test pushed — typically one handler per standalone
// Flick construction.
//
// Termination depends on snapshotExceptionHandler() popping what it pushes. A
// peek that stops popping leaves this spinning, which hangs the whole suite
// with no output to explain it — so keep the pair above intact.
function unwindExceptionHandlersTo(?callable $previous): void
{
    $active = snapshotExceptionHandler();

    while ($active !== null && $active !== $previous) {
        restore_exception_handler();
        $active = snapshotExceptionHandler();
    }
}

/*
|--------------------------------------------------------------------------
| Shared Fixtures
|--------------------------------------------------------------------------
*/

// a basic two-step multistep form, shared by every test that drives
// createMultistep(); keep it here so the suite exercises one form shape
function getBasicMultistepForm(): array
{
    return [
        'Personal Info' => [
            'text' => 'Enter your personal information',
            'fields' => [
                'name' => [
                    'type' => 'text',
                    'label' => 'Name',
                ],
                'email' => [
                    'type' => 'email',
                    'label' => 'Email',
                ],
            ],
        ],
        'Professional Info' => [
            'text' => 'Enter your professional information',
            'fields' => [
                'occupation' => [
                    'type' => 'text',
                    'label' => 'Occupation',
                ],
            ],
        ],
    ];
}
