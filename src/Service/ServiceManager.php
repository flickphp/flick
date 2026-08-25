<?php

declare(strict_types=1);

namespace Flick\Service;

use DI\Container;
use DI\ContainerBuilder;
use DI\DependencyException;
use DI\NotFoundException;
use Exception;
use Flick\Support\FlickException;
use Flick\Support\Support;

class ServiceManager
{
    protected Container $container;

    /**
     * @throws Exception
     */
    public function __construct(protected array $config, protected Support $support)
    {
        $this->loadServiceContainer();
    }

    /**
     * @throws Exception
     */
    public function loadServiceContainer(): void
    {
        $containerBuilder = new ContainerBuilder;

        try {
            $this->container = $containerBuilder->build();
        } catch (Exception $exception) {
            throw FlickException::addServiceError($exception->getMessage());
        }
    }

    /**
     * Register the built-in providers, then everything in the Registry.
     *
     * Built-ins go first so a package can take over a built-in name. Service
     * packages reach the Registry from a file Composer includes on every
     * request (`autoload.files`), so by now the list is complete; nothing
     * here touches the filesystem.
     *
     * A name whose class is missing, is not a ServiceProvider, or cannot be
     * instantiated throws: that is a mistake in a Registry::add() call, and
     * skipping it quietly would surface later as "service not available" with
     * no hint why.
     */
    public function registerServices(): void
    {
        $providers = [];

        foreach ($this->getBuiltInProviders() as $provider) {
            $providers[$provider['name']] = $provider['package'];
        }

        foreach (Registry::all() as $name => $package) {
            $providers[$name] = $package;
        }

        foreach ($providers as $name => $package) {
            if (! class_exists($package)
                || ! is_subclass_of($package, ServiceProvider::class)
                || ! (new \ReflectionClass($package))->isInstantiable()) {
                throw FlickException::invalidServiceProvider($name, $package);
            }

            $provider = new $package;

            // Pro needs every service's config so it can distribute them to its sub-services
            if ($name === 'pro') {
                $serviceConfig = $this->config['services'] ?? [];
            } else {
                $serviceConfig = $this->config['services'][$name] ?? [];
            }

            // pass some flick config values to the service
            $serviceConfig['form']['action'] = $this->config['action'];
            $serviceConfig['form']['assets'] = $this->config['assets'];
            $serviceConfig['form']['cache'] = $this->config['cache'] ?? false;
            $serviceConfig['form']['dateFormat'] = $this->config['dateFormat'];
            $serviceConfig['form']['echo'] = $this->config['echo'];
            $serviceConfig['form']['id'] = $this->config['id'];
            $serviceConfig['form']['lang'] = $this->config['lang'];
            $serviceConfig['form']['rules'] = $this->config['validationMessages'];
            $serviceConfig['form']['trim'] = $this->config['trim'] ?? true;
            $serviceConfig['form']['views'] = $this->config['views'];

            $provider->setConfig($serviceConfig);
            $provider->setSupport($this->support);
            $this->addService($provider);
        }
    }

    public function addService(ServiceProvider $provider): void
    {
        $provider->register($this->container);
    }

    /**
     * @throws Exception
     */
    public function getService($name)
    {
        try {
            return $this->container->get($name);
        } catch (DependencyException|NotFoundException) {
            throw FlickException::serviceIsNotAvailable($name);
        }
    }

    public function hasService($name): bool
    {
        try {
            return $this->container->has($name);
        } catch (Exception $exception) {
            return false;
        }
    }

    /**
     * The providers bundled with flick itself.
     *
     * @return list<array{name: string, package: class-string<ServiceProvider>}>
     */
    protected function getBuiltInProviders(): array
    {
        return [
            ['name' => 'dropdowns', 'package' => 'Flick\\Dropdowns\\DropdownsServiceProvider'],
            ['name' => 'forms', 'package' => 'Flick\\Forms\\FormsServiceProvider'],
            ['name' => 'mail', 'package' => 'Flick\\Mailer\\MailerServiceProvider'],
            ['name' => 'views', 'package' => 'Flick\\Views\\ViewsServiceProvider'],
        ];
    }
}
