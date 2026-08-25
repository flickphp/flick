<?php

use DI\Container;
use Flick\Flick;
use Flick\Http\ArrayRequest;
use Flick\Service\Registry;
use Flick\Service\ServiceManager;
use Flick\Service\ServiceProvider;
use Flick\Support\Errors;
use Flick\Support\FlickException;
use Flick\Support\Support;

/**
 * ServiceManager wires providers into the PHP-DI container. Built-in
 * providers come first, then whatever the Registry holds (services/index.md,
 * "How Flick Finds It"). Nothing here reads or writes the filesystem.
 */
class ServiceManagerTestProvider implements ServiceProvider
{
    public static array $received = [];

    public function register(mixed $container): void
    {
        $container->set('smtest', fn () => 'smtest instance');
    }

    public function setConfig(array $config): void
    {
        self::$received = $config;
    }

    public function setSupport(Support $support): void {}
}

abstract class ServiceManagerTestAbstractProvider implements ServiceProvider
{
    public function register(mixed $container): void {}

    public function setConfig(array $config): void {}

    public function setSupport(Support $support): void {}
}

function serviceManagerForm(array $config = []): Flick
{
    return new Flick(array_merge([
        'csrf' => false,
        'echo' => false,
        'request' => ArrayRequest::createGet(),
    ], $config));
}

beforeEach(function () {
    $this->support = new Support([], new Errors);
    $this->serviceManager = new ServiceManager([], $this->support);
});

afterEach(function () {
    Registry::remove('smtest');
    Registry::remove('ghost');
    Registry::remove('notaprovider');
    Registry::remove('abstractprovider');
    Registry::remove('views');
    ServiceManagerTestProvider::$received = [];
});

it('can create an instance of ServiceManager', function () {
    expect($this->serviceManager)->toBeInstanceOf(ServiceManager::class);
});

it('can load the service container', function () {
    $this->serviceManager->loadServiceContainer();

    $container = (new ReflectionProperty($this->serviceManager, 'container'))->getValue($this->serviceManager);

    expect($container)->toBeInstanceOf(Container::class);
});

it('can add and get a service', function () {
    $this->serviceManager->addService(new ServiceManagerTestProvider);

    expect($this->serviceManager->hasService('smtest'))->toBeTrue()
        ->and($this->serviceManager->getService('smtest'))->toBe('smtest instance');
});

it('registers the built-in providers on every form', function () {
    $form = serviceManagerForm();

    foreach (['dropdowns', 'forms', 'mail', 'views'] as $service) {
        expect($form->hasService($service))->toBeTrue("built-in '{$service}' did not register");
    }
});

it('registers a provider from the Registry', function () {
    Registry::add('smtest', ServiceManagerTestProvider::class);

    $form = serviceManagerForm();

    expect($form->hasService('smtest'))->toBeTrue()
        ->and($form->smtest)->toBe('smtest instance');
});

it('hands a registered provider its own services config plus the form block', function () {
    Registry::add('smtest', ServiceManagerTestProvider::class);

    serviceManagerForm([
        'id' => 'smForm',
        'services' => ['smtest' => ['key' => 'value']],
    ]);

    expect(ServiceManagerTestProvider::$received['key'])->toBe('value')
        ->and(ServiceManagerTestProvider::$received['form']['id'])->toBe('smForm')
        ->and(ServiceManagerTestProvider::$received['form'])->toHaveKeys([
            'action', 'assets', 'cache', 'dateFormat', 'echo', 'id', 'lang', 'rules', 'trim', 'views',
        ]);
});

it('lets a Registry entry take over a built-in name', function () {
    // Built-ins register first, so a package can replace one — the same
    // order the old services.json merge had.
    Registry::add('views', ServiceManagerTestProvider::class);

    $form = serviceManagerForm();

    // Take over, not join. Overwriting $providers['views'] means
    // ViewsServiceProvider is never constructed, so the container has no
    // 'views' key at all. Asserting only that 'smtest' registered would still
    // pass if both providers were constructed and the Registry entry merely
    // sat alongside the built-in it is supposed to replace.
    expect($form->hasService('smtest'))->toBeTrue()
        ->and($form->hasService('views'))->toBeFalse();
});

it('throws when a registered provider class does not exist', function () {
    Registry::add('ghost', 'Flick\\Tests\\Fake\\DoesNotExist');

    expect(fn () => serviceManagerForm())
        ->toThrow(FlickException::class, 'ghost');
});

it('throws when a registered class is not a service provider', function () {
    Registry::add('notaprovider', stdClass::class);

    expect(fn () => serviceManagerForm())
        ->toThrow(FlickException::class, 'stdClass');
});

// An abstract class implementing ServiceProvider passes both class_exists()
// and is_subclass_of(), so without an instantiability check `new $package`
// raises a bare Error instead of Flick's card.
it('throws when a registered class is an abstract service provider', function () {
    Registry::add('abstractprovider', ServiceManagerTestAbstractProvider::class);

    expect(fn () => serviceManagerForm())
        ->toThrow(FlickException::class, 'abstractprovider');
});

it('writes nothing to disk while registering services', function () {
    serviceManagerForm();

    expect(is_dir(dirname(__DIR__, 2).'/cache'))->toBeFalse()
        ->and(file_exists(sys_get_temp_dir().'/services.json'))->toBeFalse();
});

it('reports an unknown service as unavailable', function () {
    expect(fn () => $this->serviceManager->getService('nope'))
        ->toThrow(FlickException::class, 'nope');
});
