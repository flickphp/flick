<?php

declare(strict_types=1);

namespace Flick\App;

use Flick\Flick;
use Flick\Support\FlickException;
use Stringable;

class View
{
    /**
     * Slots that render as nothing when the caller supplied no value, rather
     * than leaving a literal {{tag}} in the markup.
     */
    private const CLEARED_PLACEHOLDERS = ['classes', 'value', 'attributes', 'options', 'datalist'];

    /**
     * Attributes that are not plain template values: 'error' is escaped into
     * {{message}}, the rest are either assembled markup handled separately or
     * arrays that have no placeholder at all.
     */
    private const NON_VALUE_ATTRIBUTES = ['classes', 'error', 'options', 'attributes', 'rules', 'messages'];

    private bool $cacheViews = false;

    /**
     * The resolved template file. One key, whether the template came from the
     * assets directory or the shipped theme - Views::resolve() already decided
     * that, so this class no longer branches on which key is present.
     */
    private readonly string $templatePath;

    public function __construct(protected array $attributes, protected Flick $flick)
    {
        if (! array_key_exists('templatePath', $attributes)) {
            // dereferencing the missing key to build the message produced a
            // TypeError instead of this exception
            throw FlickException::viewPathIsNotDefined('');
        }

        $this->templatePath = $attributes['templatePath'];

        if ($this->flick->config('cache') && $this->flick->config('assets')) {
            $this->cacheViews = true;
        }
    }

    public static function make(array $attributes, Flick $flick): static
    {
        return new static($attributes, $flick);
    }

    public function __toString(): string
    {
        return $this->render().PHP_EOL;
    }

    public function render(): string
    {
        $cacheFilePath = null;

        // load the template first so the cache key can be tied to its content.
        // Deliberately NOT memoized: a template edited between two renders that
        // share one Views instance must be re-read.
        $view = $this->flick->views->read($this->templatePath);

        // only cache value-independent static markup: a per-field value would
        // produce one cache file per value with no eviction, so skip caching then
        $cacheable = $this->cacheViews && empty($this->attributes['value']);

        if ($cacheable) {
            // key on the template content too, so editing a .view.php template
            // invalidates any existing cache file instead of serving stale markup
            $cacheFilename = md5(serialize($this->attributes).'|'.$view);
            $cacheFilePath = $this->flick->config('assets').'/cache/views/'.$cacheFilename.'.html';

            // load the cached view
            if (! $this->flick->submitted() && $this->flick->config('cache')) {
                if (is_file($cacheFilePath)) {
                    return file_get_contents($cacheFilePath);
                }
            }
        }

        // Every @directive is resolved against $this->attributes, never against
        // the rendered output, so they all run before any value is substituted.

        // parse the @attributes slot
        // if an attribute exists, add the provided text from the view
        $view = preg_replace_callback(
            '/@attributes\(\'?(.*?)\'?\)(.*?)@endattributes/s',
            function ($matches) {
                if (str_contains($this->attributes['attributes'], $matches[1])) {
                    return trim($matches[2]);
                } else {
                    return '';
                }
            },
            $view
        );

        // label is empty: remove the html between the @label/@endlabel tags
        if (empty($this->attributes['label'])) {
            $view = preg_replace('/@label[\s\S]+?@endlabel/', '', $view);
        }

        // error is empty: remove the html between the @error/@enderror tags
        if (empty($this->attributes['error'])) {
            $view = preg_replace('/@error[\s\S]+?@enderror/', '', $view);
        }

        // help text is empty: remove the html between the @help/@endhelp tags
        if (empty($this->attributes['help'])) {
            $view = preg_replace('/@help[\s\S]+?@endhelp/', '', $view);
        }

        $view = str_replace(
            ['@label', '@endlabel', '@error', '@enderror', '@help', '@endhelp'],
            '',
            $view
        );

        // Replace the template {{ tags }} in a single pass.
        //
        // The map is built in full first and substitution happens exactly once,
        // so a value can never be re-scanned for placeholders. Substituting one
        // key at a time over already-substituted output let a submitted value of
        // '{{datalist}}' expand into real markup and break out of value="...".
        $replacements = $this->buildReplacements();

        $view = preg_replace_callback(
            '/\{\{\s*(\w+)\s*\}\}/',
            static function ($matches) use ($replacements) {
                if (array_key_exists($matches[1], $replacements)) {
                    return $replacements[$matches[1]];
                }

                // a slot the caller supplied nothing for renders as nothing
                return in_array($matches[1], self::CLEARED_PLACEHOLDERS, true) ? '' : $matches[0];
            },
            $view
        );

        // remove extra blank lines to clean up the html
        $view = preg_replace('/(\r\n|\r|\n){2,}/', '', $view);

        // cache the view
        if (! $this->flick->submitted() && $cacheable) {
            $this->cacheView($cacheFilePath, $view);
        }

        return $view;
    }

    /**
     * Build the complete {{key}} => replacement map for one render.
     *
     * @return array<string, string>
     */
    private function buildReplacements(): array
    {
        $replacements = [];

        foreach ($this->attributes as $key => $value) {
            if (in_array($key, self::NON_VALUE_ATTRIBUTES, true)) {
                continue;
            }

            // arrays and objects have no placeholder; skipping them keeps the
            // map a plain string map the callback can return directly
            if (is_scalar($value) || $value === null || $value instanceof Stringable) {
                $replacements[$key] = (string) $value;
            }
        }

        if (isset($this->attributes['error'])) {
            // Escape here: error text can reflect raw user input (e.g. the date rules
            // echo the submitted value), so rendering it unescaped is a reflected-XSS hole.
            $replacements['message'] = htmlspecialchars((string) $this->attributes['error'], ENT_QUOTES, 'UTF-8');
        }

        // pre-assembled markup the builders produced; escaped where it was built
        foreach (['classes', 'options', 'attributes', 'datalist'] as $key) {
            if (isset($this->attributes[$key])) {
                $replacements[$key] = (string) $this->attributes[$key];
            }
        }

        return $replacements;
    }

    private function cacheView(string $cacheFilePath, string $view): void
    {
        $this->checkViewsDirectory($cacheFilePath);

        if (! file_put_contents($cacheFilePath, $view)) {
            throw FlickException::viewCacheFileNotWritable($cacheFilePath, $view);
        }
    }

    private function checkViewsDirectory(string $cacheFilePath): void
    {
        $viewsDirectory = dirname($cacheFilePath);

        // The second is_dir() absorbs a concurrent request that created the
        // directory between the first check and our mkdir().
        if (! is_dir($viewsDirectory) && ! @mkdir($viewsDirectory, 0755, true) && ! is_dir($viewsDirectory)) {
            throw FlickException::cacheDirectoryCannotBeCreated($viewsDirectory);
        }

        if (! is_writable($viewsDirectory)) {
            throw FlickException::viewCacheDirectoryNotWritable($cacheFilePath);
        }

        $this->protectCacheDirectory(dirname($viewsDirectory));
    }

    /**
     * Drop an Apache deny file into <assets>/cache.
     *
     * The documented `assets` layout puts this directory inside the docroot —
     * the configuration docs show a path relative to the form script, and a
     * project with no public/ dir serves it — so the compiled views are
     * fetchable over HTTP without it. Written whenever it is missing, not only
     * at mkdir time, because the installs most in need of it are the ones
     * whose cache directory an older Flick already created.
     *
     * Apache (and LiteSpeed) only: nginx and Caddy never read .htaccess. Those
     * need the assets directory moved out of the docroot, which is what the
     * configuration docs tell people to do.
     *
     * Best-effort by design. A read-only cache directory is already a problem
     * Flick reports elsewhere; failing to write a defense-in-depth file is not
     * worth breaking form rendering over.
     */
    private function protectCacheDirectory(string $cacheDirectory): void
    {
        $guard = $cacheDirectory.'/.htaccess';

        // Never clobber rules the developer put here themselves.
        if (file_exists($guard)) {
            return;
        }

        if (! is_writable($cacheDirectory)) {
            return;
        }

        @file_put_contents($guard, <<<'HTACCESS'
            # Written by Flick. Holds the compiled views — nothing here should be
            # reachable over HTTP.
            #
            # Apache and LiteSpeed only. nginx and Caddy ignore this file; there,
            # keep the assets directory outside your public folder instead.
            <IfModule mod_authz_core.c>
                Require all denied
            </IfModule>
            <IfModule !mod_authz_core.c>
                Order allow,deny
                Deny from all
            </IfModule>

            HTACCESS);
    }
}
