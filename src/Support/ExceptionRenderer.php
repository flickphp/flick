<?php

declare(strict_types=1);

namespace Flick\Support;

use Flick\Http\HtmlResponse;
use Flick\Http\ResponseHandlers;
use ReflectionClass;
use Throwable;

/**
 * Renders Flick's standalone error pages and dispatches them through the
 * response handlers. All escaping happens here — exception messages are
 * plain text everywhere else. Flick::globalExceptionHandler is the caller;
 * without handlers there is no render path, only an ordinary uncaught throw.
 *
 * Two pages: with $debug on, the rich developer page (source excerpt, stack
 * trace, copy button); with $debug off, a quiet card that discloses no
 * paths, while file and line go to error_log. $debug
 * is a per-call parameter (each caller passes its own instance's 'debug'
 * config) — deliberately not static state, so two Flick instances with
 * different debug settings cannot affect each other.
 */
class ExceptionRenderer
{
    /** How many stack frames the trace panel shows before "Show more". */
    private const VISIBLE_FRAMES = 5;

    /**
     * What the quiet card says in place of the exception message. The page is
     * shown to the site's own visitors, so it carries nothing written for a
     * developer — the message goes to error_log instead.
     */
    private const PRODUCTION_MESSAGE = 'Something went wrong. Please try again later.';

    public static function render(Throwable $exception, ResponseHandlers $handlers, bool $debug = false): void
    {
        // FlickException fixes its own file/line to the factory caller at
        // construction, so getFile()/getLine() are the real origin here
        $file = $exception->getFile();
        $line = $exception->getLine();

        if ($debug) {
            $content = static::debugPage($exception, $file, $line);
        } else {
            error_log(static::logLine($exception, $file, $line));
            $content = static::productionPage($exception);
        }

        $handlers->handleException(new HtmlResponse($content, 500));
    }

    /**
     * The whole developer payload, in one log entry. With the quiet card
     * showing none of it, this is the only place the message, the help text,
     * the code sample and the docs link survive.
     */
    private static function logLine(Throwable $exception, string $file, int $line): string
    {
        $entry = 'Flick exception: '.$exception->getMessage().' in '.$file.' on line '.$line;

        if (! $exception instanceof FlickException) {
            return $entry;
        }

        $entry .= ' | heading: '.$exception->heading;

        if ($exception->help !== '') {
            $entry .= ' | help: '.$exception->help;
        }

        if ($exception->docsUrl !== '') {
            $entry .= ' | docs: '.$exception->docsUrl;
        }

        if ($exception->codeSample !== '') {
            $entry .= " | code:\n".$exception->codeSample;
        }

        return $entry;
    }

    /**
     * The quiet card: a heading and one fixed sentence, nothing else.
     *
     * Every other slot is deliberately empty. The exception message names
     * absolute server paths in 11 of the factories, and `help`, `codeSample`
     * and `docsUrl` are written for whoever is building the site, not for the
     * visitor who happened to trip the error.
     */
    private static function productionPage(Throwable $exception): string
    {
        $heading = $exception instanceof FlickException ? $exception->heading : 'Whoops';

        // strtr, not str_replace: a single pass over the template, so a
        // placeholder-like token inside a substituted value can never be
        // consumed by a later replacement. Same reason in debugPage().
        return strtr(static::template('exception'), [
            // escape() for the tab title (plain text), text() for the <h1>
            // (inline backticks become <code>) — same value, two contexts.
            '{{ heading }}' => static::escape($heading),
            '{{ headline }}' => static::text($heading),
            '{{ message }}' => static::text(self::PRODUCTION_MESSAGE),
            '{{ help }}' => '',
            '{{ code }}' => '',
            '{{ link }}' => '',
        ]);
    }

    private static function debugPage(Throwable $exception, string $file, int $line): string
    {
        $flick = $exception instanceof FlickException ? $exception : null;

        if ($flick !== null) {
            $title = $flick->heading;
            $headline = static::escape($flick->heading);
        } else {
            $class = $exception::class;
            $slash = strrpos($class, '\\');
            $title = $slash === false ? $class : substr($class, $slash + 1);
            $headline = $slash === false
                ? static::escape($class)
                : '<span class="ns">'.static::escape(substr($class, 0, $slash + 1)).'</span>'.static::escape(substr($class, $slash + 1));
        }

        $copyText = $exception::class.': '.$exception->getMessage()."\nat ".static::relative($file).':'.$line;

        return strtr(static::template('exception-debug'), [
            '{{ logo }}' => static::logo(),
            '{{ heading }}' => static::escape($title),
            '{{ headline }}' => $headline,
            '{{ message }}' => static::text($exception->getMessage()),
            '{{ help }}' => static::helpFragment($flick->help ?? ''),
            '{{ code }}' => static::codeFragment($flick->codeSample ?? ''),
            '{{ link }}' => static::linkFragment($flick->docsUrl ?? ''),
            '{{ fileRow }}' => '<div class="file-row"><span>'.static::escape(static::relative($file)).'</span><span class="sep">:</span><span>'.$line.'</span></div>',
            '{{ excerpt }}' => static::excerptPanel($file, $line),
            '{{ trace }}' => static::tracePanel($exception),
            '{{ copyText }}' => static::escape($copyText),
        ]);
    }

    /** A source excerpt around the throw line, or empty if unreadable. */
    private static function excerptPanel(string $file, int $line): string
    {
        if (! is_file($file) || ! is_readable($file)) {
            return '';
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false || ! isset($lines[$line - 1])) {
            return '';
        }

        $first = max(1, $line - 3);
        $last = min(count($lines), $line + 3);

        $rows = '';
        for ($num = $first; $num <= $last; $num++) {
            $classes = $num === $line ? 'excerpt-row hl' : 'excerpt-row';
            $rows .= '<div class="'.$classes.'">'
                .'<span class="gutter">'.$num.'</span>'
                .'<span class="excerpt-text">'.static::escape($lines[$num - 1]).'</span>'
                .'</div>';
        }

        return '<section class="panel">'
            .'<header class="panel-header"><span>'.static::escape(static::relative($file)).'</span><span class="faint">PHP</span></header>'
            .'<div class="excerpt">'.$rows.'</div>'
            .'</section>';
    }

    /** The stack trace panel, first frames visible, the rest behind a toggle. */
    private static function tracePanel(Throwable $exception): string
    {
        $frames = $exception->getTrace();
        if ($frames === []) {
            return '';
        }

        $rendered = [];
        foreach ($frames as $i => $frame) {
            $call = ($frame['class'] ?? '').($frame['type'] ?? '').$frame['function']
                .'('.implode(', ', array_map(static::argPreview(...), $frame['args'] ?? [])).')';

            $location = isset($frame['file'], $frame['line'])
                ? static::relative($frame['file']).':'.$frame['line']
                : '[internal]';

            $rendered[] = '<div class="frame">'
                .'<div class="frame-call">#'.($i + 1).' '.static::escape($call).'</div>'
                .'<div class="frame-loc">'.static::escape($location).'</div>'
                .'</div>';
        }

        $visible = implode('', array_slice($rendered, 0, self::VISIBLE_FRAMES));
        $hidden = array_slice($rendered, self::VISIBLE_FRAMES);

        $more = '';
        if ($hidden !== []) {
            $more = '<div id="more-frames" hidden>'.implode('', $hidden).'</div>'
                .'<button type="button" class="more-btn" id="more-frames-btn">'.static::moreFramesLabel(count($hidden)).'</button>';
        }

        return '<section class="panel">'
            .'<header class="panel-header"><span>Stack trace</span><span class="faint">'.count($rendered).' frames</span></header>'
            .'<div>'.$visible.$more.'</div>'
            .'</section>';
    }

    private static function moreFramesLabel(int $hidden): string
    {
        return 'Show '.$hidden.' more '.($hidden === 1 ? 'frame' : 'frames');
    }

    /** A short, safe preview of one trace-frame argument. */
    private static function argPreview(mixed $arg): string
    {
        return match (true) {
            is_string($arg) => "'".(mb_strlen($arg) > 40 ? mb_substr($arg, 0, 37).'...' : $arg)."'",
            is_bool($arg) => $arg ? 'true' : 'false',
            $arg === null => 'null',
            is_int($arg), is_float($arg) => (string) $arg,
            is_array($arg) => '[...]',
            is_object($arg) => (new ReflectionClass($arg))->getShortName().'{...}',
            default => '...',
        };
    }

    private static function helpFragment(string $help): string
    {
        return $help === '' ? '' : '<p class="help">'.static::text($help).'</p>';
    }

    private static function codeFragment(string $code): string
    {
        return $code === '' ? '' : '<div class="code">'.static::escape($code).'</div>';
    }

    private static function linkFragment(string $url): string
    {
        return $url === '' ? '' : '<div class="link"><a href="'.static::escape($url).'">'.static::escape($url).'</a></div>';
    }

    private static function template(string $name): string
    {
        return file_get_contents(__DIR__.'/../../resources/core/views/'.$name.'.view.php') ?: '';
    }

    /** The inlined brand SVG; the text wordmark is the missing-file fallback. */
    private static function logo(): string
    {
        $svg = file_get_contents(__DIR__.'/../../resources/core/img/flick-logo.svg');

        return $svg !== false ? $svg : 'flick<span>()</span>';
    }

    /** Paths relative to the application when possible; shorter everywhere. */
    private static function relative(string $path): string
    {
        $cwd = getcwd();

        if (is_string($cwd) && $cwd !== '' && str_starts_with($path, $cwd.'/')) {
            return substr($path, strlen($cwd) + 1);
        }

        return $path;
    }

    /**
     * Escape a plain-text value, then promote inline backtick spans to
     * <code>. Escaping first means a backtick inside a value can mis-shape a
     * span but can never smuggle markup in.
     */
    private static function text(string $value): string
    {
        return preg_replace('/`([^`\n]+)`/', '<code>$1</code>', static::escape($value));
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
