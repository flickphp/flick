<?php

declare(strict_types=1);

use Flick\Http\HtmlResponse;
use Flick\Http\ResponseHandlers;
use Flick\Support\ExceptionRenderer;
use Flick\Support\FlickException;

function renderExceptionPage(Throwable $exception, bool $debug = false): HtmlResponse
{
    $captured = null;

    $handlers = (new ResponseHandlers)->onException(function (HtmlResponse $response) use (&$captured) {
        $captured = $response;

        return null;
    });

    ExceptionRenderer::render($exception, $handlers, $debug);

    return $captured;
}

it('responds with a 500', function () {
    expect(renderExceptionPage(new RuntimeException('boom'))->getStatusCode())->toBe(500);
});

// The message reaches a page only when debug is on; the quiet card shows a
// fixed sentence instead, so these escaping rules are pinned there.
it('escapes a hostile message (§2.9, moved from message build to render)', function () {
    $page = renderExceptionPage(new RuntimeException('bad <script>alert(1)</script> value'), debug: true)->getContent();

    expect($page)->not->toContain('<script>alert(1)</script>')
        ->and($page)->toContain('&lt;script&gt;');
});

it('renders inline backticks as code spans', function () {
    $page = renderExceptionPage(new FlickException('The `views` directory was not found.'), debug: true)->getContent();

    expect($page)->toContain('<code>views</code>');
});

it('cannot be broken out of by a backtick inside a value', function () {
    $page = renderExceptionPage(new FlickException('path: `/tmp/x` <img src=x onerror=alert(1)>`.php`'), debug: true)->getContent();

    expect($page)->not->toContain('<img src=x');
});

it('uses the FlickException heading on the page and in the title', function () {
    $page = renderExceptionPage(FlickException::viewFileNotFound('/tmp/nope.view.php'))->getContent();

    expect($page)->toContain('<h1>View file not found</h1>')
        ->and($page)->toContain('<title>View file not found</title>');
});

it('keeps the tab title plain text when the heading has backticks', function () {
    $page = renderExceptionPage(new FlickException('x', heading: 'Missing `driver` config'))->getContent();

    expect($page)->toContain('<title>Missing `driver` config</title>')
        ->and($page)->toContain('<h1>Missing <code>driver</code> config</h1>');
});

it('renders a heading containing placeholder-like tokens verbatim', function () {
    // the heading is the only dynamic slot left on the quiet card, so it is
    // where the single-pass strtr property is now observable there
    $page = renderExceptionPage(new FlickException('x', heading: 'unknown placeholder {{ code }} here'))->getContent();

    expect($page)->toContain('unknown placeholder {{ code }} here');
});

it('renders a message containing placeholder-like tokens verbatim on the debug page', function () {
    $page = renderExceptionPage(new RuntimeException('unknown placeholder {{ trace }} in template'), debug: true)->getContent();

    // pin the message paragraph itself — the copy-button text and the source
    // excerpt also echo the message, so a bare toContain() would pass even
    // when a sequential replace eats the paragraph copy
    expect($page)->toContain('unknown placeholder {{ trace }} in template</p>');
});

it('falls back to Whoops for a non-Flick throwable', function () {
    $page = renderExceptionPage(new RuntimeException('boom'))->getContent();

    expect($page)->toContain('<h1>Whoops</h1>');
});

// help, codeSample and docsUrl are written for whoever builds the site, so
// they render on the debug page only — the quiet card leaves those slots empty
// whatever the exception carries.
it('renders help, code and link slots for a fully loaded exception', function () {
    $page = renderExceptionPage(FlickException::serviceIsNotAvailable('mail'), debug: true)->getContent();

    expect($page)->toContain('class="help"')
        ->and($page)->toContain('<div class="code">')
        ->and($page)->toContain('<a href="https://flickphp.com/services"');
});

it('escapes the code sample', function () {
    $page = renderExceptionPage(new FlickException('x', codeSample: "\$config = ['a' => '<b>'];"), debug: true)->getContent();

    expect($page)->not->toContain('<b>')
        ->and($page)->toContain('&lt;b&gt;');
});

it('escapes a hostile docs url', function () {
    $page = renderExceptionPage(new FlickException('x', docsUrl: 'https://x.test/"><script>alert(1)</script>'), debug: true)->getContent();

    expect($page)->not->toContain('"><script>');
});

it('omits empty slots entirely', function () {
    $page = renderExceptionPage(new FlickException('plain'), debug: true)->getContent();

    expect($page)->not->toContain('class="help"')
        ->and($page)->not->toContain('<div class="code">')
        ->and($page)->not->toContain('href="https://');
});

it('leaves the help, code and link slots empty on the production page', function () {
    $page = renderExceptionPage(FlickException::serviceIsNotAvailable('mail'), debug: false)->getContent();

    expect($page)->not->toContain('class="help"')
        ->and($page)->not->toContain('<div class="code">')
        ->and($page)->not->toContain('<a href');
});

it('loads no external resources', function () {
    // an error page must not phone home: no CDN fonts, styles, or scripts.
    // The docsUrl anchor is a link the user clicks, not a resource load.
    $page = renderExceptionPage(FlickException::serviceIsNotAvailable('mail'))->getContent();

    expect($page)->not->toContain('fonts.bunny.net')
        ->and($page)->not->toContain('cdnjs.cloudflare.com')
        ->and($page)->not->toMatch('/<(script|link)[^>]+(src|href)="http/');
});

it('omits the file and line when debug is off', function () {
    $page = renderExceptionPage(new RuntimeException('boom'), debug: false)->getContent();

    expect($page)->not->toContain('on line')
        ->and($page)->not->toContain(__FILE__);
});

// DEBUG PAGE -----------------------------------------------------------------

it('shows the throwing file and line on the debug page', function () {
    $page = renderExceptionPage(new RuntimeException('boom'), debug: true)->getContent();

    expect($page)->toContain(basename(__FILE__))
        ->and($page)->toContain('class="file-row"');
});

it('renders a source excerpt with the throwing line highlighted', function () {
    $page = renderExceptionPage(new RuntimeException('excerpt marker boom'), debug: true)->getContent();

    // the exception is constructed on a line whose source text contains this
    // literal, so the excerpt must show it, escaped, in a highlighted row
    expect($page)->toContain('excerpt marker boom')
        ->and($page)->toContain('excerpt-row hl')
        ->and($page)->toContain('class="gutter"');
});

it('pluralizes the show-more label correctly', function () {
    // the real hidden-frame count is not controllable from inside PHPUnit
    // (the test runner's own stack is in every trace), so pin the helper
    $label = new ReflectionMethod(ExceptionRenderer::class, 'moreFramesLabel');

    expect($label->invoke(null, 1))->toBe('Show 1 more frame')
        ->and($label->invoke(null, 2))->toBe('Show 2 more frames');
});

it('renders the stack trace panel with frames', function () {
    $page = renderExceptionPage(FlickException::viewFileNotFound('/tmp/nope.view.php'), debug: true)->getContent();

    expect($page)->toContain('Stack trace')
        ->and($page)->toContain('frames')
        ->and($page)->toContain('#1 ');
});

it('uses the heading as the headline for a FlickException', function () {
    $page = renderExceptionPage(FlickException::viewFileNotFound('/tmp/nope.view.php'), debug: true)->getContent();

    expect($page)->toContain('<h1');
    expect($page)->toContain('View file not found');
});

it('uses the split class name as the headline for a foreign throwable', function () {
    $page = renderExceptionPage(new RuntimeException('boom'), debug: true)->getContent();

    expect($page)->toContain('RuntimeException');
});

it('escapes source code in the excerpt', function () {
    // this test file starts with <?php, which is inside no excerpt — but the
    // helper closure below contains markup-significant characters on the
    // construction line, which is what the excerpt shows
    $page = renderExceptionPage(new RuntimeException('esc <b>bold</b> boom'), debug: true)->getContent();

    expect($page)->not->toContain('<b>bold</b>')
        ->and($page)->toContain('&lt;b&gt;');
});

it('offers a copy button carrying the class, message and location', function () {
    $page = renderExceptionPage(FlickException::viewFileNotFound('/tmp/nope.view.php'), debug: true)->getContent();

    expect($page)->toContain('data-copy=')
        ->and($page)->toContain('FlickException');
});

it('offers the three-state theme toggle', function () {
    $page = renderExceptionPage(new RuntimeException('boom'), debug: true)->getContent();

    expect($page)->toContain('>Auto<')
        ->and($page)->toContain('>Light<')
        ->and($page)->toContain('>Dark<');
});

it('keeps the debug page free of external resources too', function () {
    $page = renderExceptionPage(FlickException::serviceIsNotAvailable('mail'), debug: true)->getContent();

    // needles built by concatenation: the excerpt panel shows THIS file's
    // source around the throw line, so a literal needle would match itself
    expect($page)->not->toContain('fonts.google'.'apis.com')
        ->and($page)->not->toContain('fonts.bunny'.'.net')
        ->and($page)->not->toMatch('/<(?:script|link)[^>]+(?:src|href)="http/');
});

// The production page is shown to a site's own visitors, so it carries no
// Flick branding, no theme switcher, and writes nothing to their browser.
it('gives the production page the card without branding or dev panels', function () {
    $page = renderExceptionPage(FlickException::viewFileNotFound('/tmp/nope.view.php'), debug: false)->getContent();

    expect($page)->toContain('class="card"')
        ->and($page)->not->toContain('wordmark')
        ->and($page)->not->toContain('theme-toggle')
        ->and($page)->not->toContain('Uncaught Exception')
        ->and($page)->not->toContain('copy-btn');
});

it('leaves no Flick logo or unsubstituted placeholder on the production page', function () {
    $page = renderExceptionPage(FlickException::viewFileNotFound('/tmp/nope.view.php'), debug: false)->getContent();

    expect($page)->not->toContain('<svg')
        ->and($page)->not->toContain('<img')
        ->and($page)->not->toMatch('/\{\{ \w+ \}\}/');
});

it('writes nothing to the visitor browser on the production page', function () {
    $page = renderExceptionPage(FlickException::viewFileNotFound('/tmp/nope.view.php'), debug: false)->getContent();

    expect($page)->not->toContain('localStorage')
        ->and($page)->not->toContain('<script');
});

it('inlines the flick logo on the debug page', function () {
    $page = renderExceptionPage(FlickException::viewFileNotFound('/tmp/nope.view.php'), debug: true)->getContent();

    expect($page)->toContain('<svg')
        ->and($page)->not->toContain('flick<span>()</span>')
        ->and($page)->not->toContain('<img');
});

it('shows no excerpt, trace, or file path on the production page', function () {
    $page = renderExceptionPage(FlickException::viewFileNotFound('/tmp/nope.view.php'), debug: false)->getContent();

    expect($page)->not->toContain('Stack trace')
        ->and($page)->not->toContain('excerpt-row')
        ->and($page)->not->toContain(basename(__FILE__));
});

it('reports the throw site, not the factory, as the origin', function () {
    // the factory constructs the exception, so getFile() points at the
    // FlickException class file; the first trace frame is the real origin.
    // Needle built by concatenation — see the external-resources test.
    $page = renderExceptionPage(FlickException::viewFileNotFound('/tmp/nope.view.php'), debug: true)->getContent();

    expect($page)->toContain(basename(__FILE__))
        ->and($page)->not->toContain('FlickException'.'.php');
});

it('honors each instance debug config independently', function () {
    $originalHandler = snapshotExceptionHandler();

    Flick\Flick::resetDefaultRequest();

    $rendered = [];
    $capture = function (string $slot) use (&$rendered) {
        return function (HtmlResponse $response) use (&$rendered, $slot) {
            $rendered[$slot] = $response->getContent();

            return null;
        };
    };

    $debugForm = new Flick\Flick(['csrf' => false, 'debug' => true, 'onException' => $capture('debug')]);
    $quietForm = new Flick\Flick(['csrf' => false, 'debug' => false, 'onException' => $capture('quiet')]);

    $debugForm->globalExceptionHandler(new RuntimeException('boom'));
    $quietForm->globalExceptionHandler(new RuntimeException('boom'));

    // pop the two handlers the constructors registered, leaving the stack as
    // this test found it — set_exception_handler would push a third instead
    restore_exception_handler();
    restore_exception_handler();

    expect(snapshotExceptionHandler())->toBe($originalHandler);

    // the file-row (server path disclosure) is the debug page's marker; the
    // production page must never carry it
    expect($rendered['debug'])->toContain('class="file-row"')
        ->and($rendered['quiet'])->not->toContain('class="file-row"');
});
