<?php

declare(strict_types=1);

namespace Flick\Forms;

use Flick\Service\ServiceProvider;
use Flick\Support\Support;

class FormsServiceProvider implements ServiceProvider
{
    protected array $config;

    protected Support $support;

    public function register(mixed $container): void
    {
        $container->set('forms', function () {
            return new Forms($this->config, $this->support);
        });
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function setSupport(Support $support): void
    {
        $this->support = $support;
    }
}
