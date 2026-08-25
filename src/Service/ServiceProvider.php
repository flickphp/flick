<?php

declare(strict_types=1);

namespace Flick\Service;

use Flick\Support\Support;

/**
 * Interface for Flick service providers.
 *
 * Service providers connect custom services to the Flick ecosystem.
 * Implement this interface to create services that can be accessed
 * via $form->yourService->methodName().
 */
interface ServiceProvider
{
    /**
     * Register the service with the DI container.
     *
     * @param  mixed  $container  PHP-DI container instance
     */
    public function register(mixed $container): void;

    /**
     * Receive service-specific configuration.
     *
     * @param  array  $config  Configuration from the 'services.yourService' key
     */
    public function setConfig(array $config): void;

    /**
     * Receive the shared Support instance.
     *
     * Support provides utilities for error handling, sessions, and config.
     * Use $this->support->addError() to report failures that developers
     * can check via $form->hasError() or $form->getErrors().
     *
     * @param  Support  $support  Shared utilities instance
     */
    public function setSupport(Support $support): void;
}
