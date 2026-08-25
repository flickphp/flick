<?php

use Flick\Flick;
use Flick\Support\FlickException;

/*
|--------------------------------------------------------------------------
| Overriding a shipped view from the assets directory
|--------------------------------------------------------------------------
|
| The docs promise that dropping a .view.php into <assets>/views/ replaces the
| theme's copy. Only the field templates honoured that: alerts and the
| multistep views resolved their own paths without ever consulting assets, so
| an override file sat there inert.
|
| Prebuilt forms had the mirror-image bug. Once an app had an assets/forms/
| directory, a shipped form name THREW instead of falling back to the shipped
| copy - the opposite of how dropdowns behave.
|
*/

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SESSION = [];
    $_POST = [];
    $_GET = [];

    // a private assets dir per test, so a written override can't leak
    $this->assets = sys_get_temp_dir().'/flick-assets-'.bin2hex(random_bytes(6));
    mkdir($this->assets.'/views/alerts', 0777, true);
});

afterEach(function () {
    $_SESSION = [];
    $_POST = [];
    $_GET = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->assets, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $file) {
        $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
    }
    @rmdir($this->assets);
});

function overrideForm(string $assets, array $config = []): Flick
{
    return new Flick(array_merge([
        'csrf' => false,
        'echo' => false,
        'assets' => $assets,
    ], $config));
}

it('lets an assets copy of an alert view win over the theme copy', function () {
    file_put_contents(
        $this->assets.'/views/alerts/error.view.php',
        '<div class="my-alert">{{ heading }}{{ message }}</div>'
    );

    expect(overrideForm($this->assets)->errorMessage('boom'))
        ->toContain('class="my-alert"')
        ->and(overrideForm($this->assets)->errorMessage('boom'))->toContain('boom');
});

it('lets an assets copy of the breadcrumbs view win over the theme copy', function () {
    file_put_contents($this->assets.'/views/breadcrumbs.view.php', '<nav class="my-crumbs"></nav>');

    $form = overrideForm($this->assets);

    expect($form->multistepBreadcrumbs(getBasicMultistepForm()))->toContain('class="my-crumbs"');
});

it('lets an assets copy of a multistep view win over the theme copy', function () {
    file_put_contents($this->assets.'/views/multistep-submit.view.php', '<button class="my-submit">Go</button>');

    $form = overrideForm($this->assets);
    $form->createMultistep(getBasicMultistepForm());

    expect($form->submitMultistep())->toContain('class="my-submit"');
});

it('falls back to the shipped prebuilt form when the assets copy is absent', function () {
    // an assets/forms directory exists but holds no login.php; the shipped
    // form must still load, the way a shipped dropdown already does
    mkdir($this->assets.'/forms', 0777, true);
    file_put_contents($this->assets.'/forms/unrelated.php', '<?php return [];');

    expect(overrideForm($this->assets)->create('/login'))->toContain('<form');
});

it('rejects a view name that tries to climb out of the views directory', function () {
    $views = overrideForm($this->assets)->views;

    expect(fn () => $views->resolve('../../../../etc/passwd'))
        ->toThrow(FlickException::class)
        ->and(fn () => $views->resolve('alerts/../../secret'))
        ->toThrow(FlickException::class)
        ->and(fn () => $views->resolve('/etc/passwd'))
        ->toThrow(FlickException::class);
});

it('resolves a multi-segment view name', function () {
    // alerts/error is legitimate; the traversal guard must not reject it
    expect(overrideForm($this->assets)->views->resolve('alerts/error'))
        ->toEndWith('/alerts/error.view.php');
});

it('still prefers an assets prebuilt form over the shipped one', function () {
    mkdir($this->assets.'/forms', 0777, true);
    file_put_contents(
        $this->assets.'/forms/login.php',
        "<?php return ['fields' => ['nickname' => ['type' => 'text', 'label' => 'Nickname']]];"
    );

    expect(overrideForm($this->assets)->create('/login'))->toContain('Nickname');
});
