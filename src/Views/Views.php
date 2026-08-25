<?php

declare(strict_types=1);

namespace Flick\Views;

use Flick\Support\FlickException;
use Flick\Support\Support;

class Views
{
    public function __construct(protected array $config, protected Support $support) {}

    /**
     * Resolve a view name to the file that should be rendered.
     *
     * Assets first, theme second. This is the ONLY place the search order is
     * written: it used to be spelled out separately for field templates, for
     * alerts and for the multistep views, and only the first consulted the
     * assets directory at all - so a documented override of an alert or a
     * multistep template sat there inert.
     *
     * $view is a theme-relative name without the .view.php suffix, and may
     * carry one or more path segments ('input', 'alerts/error').
     */
    public function resolve(string $view): string
    {
        $view = $this->assertSafeViewName($view);

        $assets = $this->config['form']['assets'] ?? null;

        if (is_string($assets) && $assets !== '') {
            $override = $assets.'/views/'.$view.'.view.php';

            if (is_file($override)) {
                return $override;
            }
        }

        return $this->bundledPath($view);
    }

    /**
     * The shipped theme's copy of a view.
     */
    private function bundledPath(string $view): string
    {
        $theme = $this->config['form']['views'] ?? 'flick';

        return __DIR__.'/../../resources/views/'.$theme.'/'.$view.'.view.php';
    }

    /**
     * A view name is interpolated into a path, so it has to be segments of
     * plain identifiers. `/` is permitted for multi-segment names like
     * 'alerts/error'; `..`, absolute paths and anything else are not.
     *
     * The name is NOT trimmed of leading slashes first - doing that turns an
     * absolute path into a relative one that then passes, so '/etc/passwd'
     * would be accepted as 'etc/passwd'. Anything that is not already a plain
     * relative name is rejected outright.
     */
    private function assertSafeViewName(string $view): string
    {
        if (preg_match('#^[A-Za-z0-9_-]+(/[A-Za-z0-9_-]+)*$#', $view) !== 1) {
            throw FlickException::viewFileNotFound($view);
        }

        return $view;
    }

    /**
     * Read a resolved view file.
     */
    public function read(string $path): string
    {
        if (! is_file($path)) {
            throw FlickException::viewFileNotFound($path);
        }

        return file_get_contents($path);
    }

    public function load(string $path, bool $custom = false): string
    {
        if (! $custom) {
            $path = __DIR__.'/../../resources/views/'.$path;
        }

        return $this->read($path);
    }

    public function loadAsset(string $path): string
    {
        return self::load($path, true);
    }
}
