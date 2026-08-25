<?php

declare(strict_types=1);

namespace Flick\Dropdowns;

use Flick\Support\FlickException;
use Flick\Support\Support;

class Dropdowns
{
    public function __construct(protected array $config, protected Support $support) {}

    public function load(string $path, bool $custom = false): array
    {
        if (! $custom) {
            // a name is interpolated into a path and then included, so it has to
            // be a plain identifier and nothing else
            Support::assertSafeLoaderName($path);

            $path = __DIR__.'/../../lang/'.$this->config['form']['lang'].'/dropdowns/'.$path.'.php';
        }

        if (! is_file($path)) {
            throw FlickException::dropdownNotFound($path);
        }

        return include $path;
    }

    public function loadAsset(string $path): array
    {
        return self::load($path, true);
    }
}
