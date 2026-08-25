<?php

declare(strict_types=1);

namespace Flick;

use Closure;
use Exception;
use Flick\App\Build;
use Flick\App\Validate;
use Flick\Http\JsonResponse;
use Flick\Http\NativeRequest;
use Flick\Http\RequestInterface;
use Flick\Http\ResponseHandlers;
use Flick\Service\ServiceManager;
use Flick\Session\NativeSession;
use Flick\Session\SessionInterface;
use Flick\Support\Errors;
use Flick\Support\ExceptionRenderer;
use Flick\Support\FlickException;
use Flick\Support\Helpers;
use Flick\Support\Support;
use Flick\Validation\ValidationDelegateInterface;

class Flick
{
    use Helpers;

    /**
     * Global default request adapter for framework integration.
     *
     * Set this once in your service provider to use a framework adapter
     * (e.g., Laravel Request) for all Flick instances.
     */
    private static ?RequestInterface $defaultRequest = null;

    /**
     * Global default session adapter for framework integration.
     *
     * Set this once in your service provider to use a framework adapter
     * (e.g., Laravel Session) for all Flick instances.
     */
    private static ?SessionInterface $defaultSession = null;

    /**
     * Global default validation delegate for framework integration.
     *
     * Set this once in your service provider to delegate unrecognized
     * validation rules to a framework validator (e.g., Laravel Validator).
     */
    private static ?ValidationDelegateInterface $defaultValidationDelegate = null;

    /**
     * Global default CSRF token generator for framework integration.
     *
     * Set this once in your service provider to use a framework's CSRF token
     * (e.g., Laravel's csrf_token()) instead of Flick's native CSRF handling.
     * When set and csrf config is false, the generator provides the token.
     */
    private static ?Closure $defaultCsrfTokenGenerator = null;

    /**
     * Default config merged beneath every instance's config.
     *
     * Frameworks bridge their published config here at boot (e.g. Laravel's
     * config('flick')) so that a bare `new Flick()` honors it. Per-instance
     * config always overrides these defaults.
     */
    private static array $defaultConfig = [];

    public array $config = [];

    public array $fields = [];

    public ?string $id = null;

    public Build $build;

    public ResponseHandlers $handlers;

    /**
     * HTTP request adapter for accessing POST, GET, FILES, SERVER, etc.
     *
     * Defaults to NativeRequest (PHP superglobals) but can be replaced
     * with a framework adapter for Laravel/Symfony integration.
     */
    public RequestInterface $request;

    private Errors $errors;

    /** Whether a CSRF failure has already been reported for this instance. */
    private bool $csrfFailureReported = false;

    /**
     * The process-global exception handler is owned by the class, not by an
     * instance: every standalone `new Flick()` used to push its own copy onto
     * PHP's handler stack, so two forms on a page stacked two handlers. One
     * dispatcher is installed at most once and forwards to the most recently
     * constructed standalone form (last-wins, no stacking).
     */
    private static ?self $exceptionHandlerHost = null;

    /** @var Closure|null the single installed dispatcher, kept for identity checks */
    private static ?Closure $exceptionHandlerDispatcher = null;

    /** @var callable|null Previous exception handler to chain to */
    private static $previousExceptionHandler = null;

    private ServiceManager $services;

    public SessionInterface $session;

    public Support $support;

    public Validate $validate;

    /**
     * Set the default request adapter for all Flick instances.
     *
     * Use this in your framework's service provider to inject
     * a framework-specific request adapter.
     *
     * @example
     * // In Laravel AppServiceProvider:
     * Flick::setDefaultRequest(new LaravelRequest(request()));
     */
    public static function setDefaultRequest(RequestInterface $request): void
    {
        self::$defaultRequest = $request;
    }

    /**
     * Get the default request adapter.
     */
    public static function getDefaultRequest(): ?RequestInterface
    {
        return self::$defaultRequest;
    }

    /**
     * Reset the default request adapter (useful for testing).
     */
    public static function resetDefaultRequest(): void
    {
        self::$defaultRequest = null;
    }

    /**
     * Set the default session adapter for all Flick instances.
     *
     * Use this in your framework's service provider to inject
     * a framework-specific session adapter.
     *
     * @example
     * // In Laravel AppServiceProvider:
     * Flick::setDefaultSession(new LaravelSession(session()));
     */
    public static function setDefaultSession(SessionInterface $session): void
    {
        self::$defaultSession = $session;
    }

    /**
     * Get the default session adapter.
     */
    public static function getDefaultSession(): ?SessionInterface
    {
        return self::$defaultSession;
    }

    /**
     * Reset the default session adapter (useful for testing).
     */
    public static function resetDefaultSession(): void
    {
        self::$defaultSession = null;
    }

    /**
     * Set the default validation delegate for all Flick instances.
     *
     * Use this in your framework's service provider to delegate
     * unrecognized validation rules to the framework's validator.
     *
     * @example
     * // In Laravel AppServiceProvider:
     * Flick::setDefaultValidationDelegate(new LaravelValidationDelegate());
     */
    public static function setDefaultValidationDelegate(ValidationDelegateInterface $delegate): void
    {
        self::$defaultValidationDelegate = $delegate;
    }

    /**
     * Get the default validation delegate.
     */
    public static function getDefaultValidationDelegate(): ?ValidationDelegateInterface
    {
        return self::$defaultValidationDelegate;
    }

    /**
     * Reset the default validation delegate (useful for testing).
     */
    public static function resetDefaultValidationDelegate(): void
    {
        self::$defaultValidationDelegate = null;
    }

    /**
     * Set the default CSRF token generator for all Flick instances.
     *
     * Use this in your framework's service provider to provide
     * the framework's CSRF token when Flick's native CSRF is disabled.
     *
     * @example
     * // In Laravel AppServiceProvider:
     * Flick::setDefaultCsrfTokenGenerator(fn() => csrf_token());
     */
    public static function setDefaultCsrfTokenGenerator(Closure $generator): void
    {
        self::$defaultCsrfTokenGenerator = $generator;
    }

    /**
     * Get the default CSRF token generator.
     */
    public static function getDefaultCsrfTokenGenerator(): ?Closure
    {
        return self::$defaultCsrfTokenGenerator;
    }

    /**
     * Reset the default CSRF token generator (useful for testing).
     */
    public static function resetDefaultCsrfTokenGenerator(): void
    {
        self::$defaultCsrfTokenGenerator = null;
    }

    /**
     * Set default config merged beneath every Flick instance's config.
     *
     * Use this in a framework service provider to bridge published config so
     * that the zero-config `new Flick()` path honors it. Per-instance config
     * always wins over these defaults.
     *
     * @example
     * // In Laravel's FlickServiceProvider::boot():
     * Flick::setDefaultConfig(config('flick', []));
     */
    public static function setDefaultConfig(array $config): void
    {
        self::$defaultConfig = $config;
    }

    /**
     * Get the default config.
     */
    public static function getDefaultConfig(): array
    {
        return self::$defaultConfig;
    }

    /**
     * Reset the default config (useful for testing).
     */
    public static function resetDefaultConfig(): void
    {
        self::$defaultConfig = [];
    }

    /**
     * Create a new Flick form instance.
     */
    public function __construct(array|string $config = [])
    {
        // Merge any framework-published defaults beneath this instance's config
        // so a bare new Flick() honors them. Done first so the merged config
        // flows into honeypot/handlers/language checks too, not just app config.
        $config = $this->mergeDefaultConfig($config);

        // Initialize request adapter first (needed for honeypot check)
        [$this->request, $requestSuppliedByCaller] = $this->resolveRequest($config);

        // Initialize response handlers (must be first for honeypot/CSRF checks)
        $this->initializeHandlers($config);
        $this->checkForHoneypot($config);

        // Standalone only: a caller-supplied HTTP layer (explicit adapter or
        // framework default) owns its own error rendering.
        if (! $requestSuppliedByCaller) {
            self::installGlobalExceptionHandler($this);
        }

        // Installed before these two: both throw on a config mistake (a wrong
        // `assets` path, a missing language file), and the handler is what
        // turns that into Flick's card instead of PHP's blank 500.
        $this->setApplicationConfig($config);
        $this->setApplicationLanguage($config);

        $this->errors = new Errors($this->config['validationMessages']);
        $this->support = new Support($this->config, $this->errors, $this->request);

        $this->services = new ServiceManager($this->config, $this->support);
        $this->services->registerServices();

        $this->build = new Build($this, $this->support, $this->request);
        $this->session = $this->resolveSession($config);
        $this->support->setSession($this->session);
        $this->validate = new Validate($this, $this->request, self::$defaultValidationDelegate);

        $this->manageCache();

        $this->checkForAndProcessXhrSubmission($config);
    }

    /**
     * Create a new Flick form instance.
     *
     * A fluent alternative to `new Flick()`.
     *
     * @example
     * // Basic usage
     * $form = Flick::make();
     *
     * // With configuration
     * $form = Flick::make(['id' => 'contactForm', 'views' => 'bootstrap']);
     */
    public static function make(array|string $config = []): self
    {
        return new self($config);
    }

    /**
     * Resolve the request adapter based on configuration.
     *
     * Resolution order:
     * 1. Explicit 'request' key in config
     * 2. Static default (for framework service providers)
     * 3. NativeRequest (PHP superglobals)
     */
    /**
     * Resolve the request adapter and say whether the CALLER supplied it.
     *
     * The flag drives the global-exception-handler gate: whoever owns the
     * HTTP layer owns error rendering. It used to be inferred from
     * `self::$defaultRequest === null` alone, so an explicit config adapter
     * (tier 1) still got Flick's handler installed over the host's.
     *
     * @return array{RequestInterface, bool} the adapter, and true when it
     *                                       came from the caller (tier 1 or 2)
     */
    private function resolveRequest(array $config): array
    {
        // 1. Explicit adapter in config
        if (isset($config['request']) && $config['request'] instanceof RequestInterface) {
            return [$config['request'], true];
        }

        // 2. Global default (for framework service providers)
        if (self::$defaultRequest !== null) {
            return [self::$defaultRequest, true];
        }

        // 3. Fall back to native superglobals
        if (isset($config['trustedProxies']) && is_array($config['trustedProxies'])) {
            return [new NativeRequest($config['trustedProxies']), false];
        }

        return [new NativeRequest, false];
    }

    /**
     * Resolve the session adapter based on configuration.
     *
     * Resolution order:
     * 1. Explicit 'session' key in config (SessionInterface instance)
     * 2. Static default (for framework service providers)
     * 3. NativeSession (PHP native sessions)
     *
     * For frameworks that manage sessions themselves, set 'sessionAutoStart' => false
     * in config or provide a session adapter.
     */
    private function resolveSession(array $config): SessionInterface
    {
        // 1. Explicit adapter in config
        if (isset($config['session']) && $config['session'] instanceof SessionInterface) {
            return $config['session'];
        }

        // 2. Global default (for framework service providers)
        if (self::$defaultSession !== null) {
            return self::$defaultSession;
        }

        // 3. Fall back to native session
        // Check if autostart should be disabled (for framework integration)
        $autoStart = true;
        if (isset($config['sessionAutoStart']) && $config['sessionAutoStart'] === false) {
            $autoStart = false;
        }

        return new NativeSession($this->request, $autoStart);
    }

    /**
     * Get a dynamically generated service from the Service Manager.
     *
     * @return mixed|null
     *
     * @throws Exception
     */
    public function __get($name)
    {
        return $this->services->getService($name);
    }

    /**
     * Report whether a service is available.
     *
     * Services are reached through __get(), and PHP does not consult a magic
     * getter for isset(). Without this, `isset($form->upload)` is always false
     * even when Upload is installed, so any guard written that way reports the
     * service missing.
     */
    public function __isset($name): bool
    {
        return $this->services->hasService($name);
    }

    /**
     * Check if a service is available.
     */
    public function hasService(string $name): bool
    {
        return $this->services->hasService($name);
    }

    /**
     * Check if the Pro package is installed and available.
     */
    public function hasProPackage(): bool
    {
        return $this->services->hasService('pro');
    }

    /**
     * Check if any fields have validation rules.
     */
    public function hasValidationRules(): bool
    {
        if (empty($this->fields)) {
            return false;
        }

        foreach ($this->fields as $field) {
            if (! empty($field['rules'])) {
                return true;
            }
        }

        // Also check config for global rules
        if (isset($this->config['rules']) && ! empty($this->config['rules'])) {
            return true;
        }

        return false;
    }

    /**
     * Get all fields with their validation rules.
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    // FORM ELEMENTS ----------------------------------------------------------

    /**
     * Create a checkbox element.
     */
    public function checkbox(array|string $name, string $label = '', string $value = '', array|string $attributes = []): string
    {
        return $this->build->boolean('checkbox', $name, $label, $value, $attributes);
    }

    /**
     * Create an inline checkbox element.
     */
    public function checkboxInline(array|string $name, string $label = '', string $value = '', array|string $attributes = []): string
    {
        $attributes = Build::attributesToArray($attributes);
        $attributes['inline'] = true;

        return $this->build->boolean('checkbox', $name, $label, $value, $attributes);
    }

    /**
     * Create a color element.
     */
    public function color(array|string $name, string $label = '', string $value = '', array|string $attributes = []): string
    {
        return $this->build->input('color', $name, $label, $value, $attributes);
    }

    /**
     * Create a text input with a datalist for autocomplete suggestions.
     */
    public function datalist(array|string $name, string $label = '', string $value = '', array $options = [], array|string $attributes = []): string
    {
        return $this->build->datalist($name, $label, $value, $options, $attributes);
    }

    /**
     * Create a date element.
     */
    public function date(array|string $name, string $label = '', string $value = '', array|string $attributes = []): string
    {
        return $this->build->input('date', $name, $label, $value, $attributes);
    }

    /**
     * Create a datetime-local element.
     */
    public function datetime(array|string $name, string $label = '', string $value = '', array|string $attributes = []): string
    {
        return $this->build->input('datetime-local', $name, $label, $value, $attributes);
    }

    /**
     * Create an email element.
     */
    public function email(array|string $name, string $label = '', string $value = '', array|string $attributes = []): string
    {
        return $this->build->input('email', $name, $label, $value, $attributes);
    }

    /**
     * Create a file element.
     */
    public function file(array|string $name, string $label = '', string $value = '', array|string $attributes = []): string
    {
        return $this->build->input('file', $name, $label, $value, $attributes);
    }

    /**
     * Create a file element which allows multiple files to be selected.
     */
    public function files(array|string $name, string $label = '', string $value = '', array|string $attributes = []): string
    {
        $attributes['multiple'] = true;

        // append [] like selectMultiple() does: without it PHP keeps only the
        // last file of a multi-file selection in $_FILES
        if (is_array($name)) {
            $nameKey = $name['name'] ?? '';
            if (! str_ends_with($nameKey, ']')) {
                $name['name'] = $nameKey.'[]';
            }
        } elseif (! str_ends_with($name, ']')) {
            $name .= '[]';
        }

        return $this->build->input('file', $name, $label, $value, $attributes);
    }

    /**
     * Create a hidden element.
     */
    public function hidden(string $name, string $value): string
    {
        return $this->build->hidden($name, $value);
    }

    /**
     * Create a generic input element.
     */
    public function input(array|string $name, string $label = '', string $value = '', array|string $attributes = []): string
    {
        if (is_array($name)) {
            $type = $name['type'];
        } elseif (! empty($attributes['type'])) {
            $type = $attributes['type'];
        } else {
            $type = 'text';
        }

        return $this->build->input($type, $name, $label, $value, $attributes);
    }

    /**
     * Create a month element.
     */
    public function month(array|string $name, string $label = '', string $value = '', array|string $attributes = []): string
    {
        return $this->build->input('month', $name, $label, $value, $attributes);
    }

    /**
     * Create a number element.
     */
    public function number(array|string $name, string $label = '', string $value = '', array|string $attributes = []): string
    {
        return $this->build->input('number', $name, $label, $value, $attributes);
    }

    /**
     * Create a password element.
     */
    public function password(array|string $name, string $label = '', string $value = '', array|string $attributes = []): string
    {
        return $this->build->input('password', $name, $label, $value, $attributes);
    }

    /**
     * Create a radio element.
     */
    public function radio(array|string $name, string $label = '', string $value = '', array|string $attributes = []): string
    {
        return $this->build->boolean('radio', $name, $label, $value, $attributes);
    }

    /**
     * Create an inline radio element.
     */
    public function radioInline(array|string $name, string $label = '', string $value = '', array|string $attributes = []): string
    {
        $attributes = Build::attributesToArray($attributes);
        $attributes['inline'] = true;

        return $this->build->boolean('radio', $name, $label, $value, $attributes);
    }

    /**
     * Create a range (slider) element.
     */
    public function range(array|string $name, string $label = '', string $value = '', array|string $attributes = []): string
    {
        return $this->build->input('range', $name, $label, $value, $attributes);
    }

    /**
     * Create a search element.
     */
    public function search(array|string $name, string $label = '', string $value = '', array|string $attributes = []): string
    {
        return $this->build->input('search', $name, $label, $value, $attributes);
    }

    /**
     * Create a select (dropdown) element.
     */
    public function select(array|string $name, string $label = '', string $value = '', array|string $attributes = []): string
    {
        return $this->build->select($name, $label, $value, $attributes);
    }

    /**
     * Create a select (dropdown) element which allows for multiple selections.
     */
    public function selectMultiple(array|string $name, string $label = '', string $value = '', array|string $attributes = []): string
    {
        return $this->build->selectMultiple($name, $label, $value, $attributes);
    }

    /**
     * Create a tel element.
     */
    public function tel(array|string $name, string $label = '', string $value = '', array|string $attributes = []): string
    {
        return $this->build->input('tel', $name, $label, $value, $attributes);
    }

    /**
     * Create a text element.
     */
    public function text(array|string $name, string $label = '', string $value = '', array|string $attributes = []): string
    {
        return $this->build->input('text', $name, $label, $value, $attributes);
    }

    /**
     * Create a textarea element.
     */
    public function textarea(array|string $name, string $label = '', string $value = '', array|string $attributes = []): string
    {
        return $this->build->textarea($name, $label, $value, $attributes);
    }

    /**
     * Create a time element.
     */
    public function time(array|string $name, string $label = '', string $value = '', array|string $attributes = []): string
    {
        return $this->build->input('time', $name, $label, $value, $attributes);
    }

    /**
     * Create a url element.
     */
    public function url(array|string $name, string $label = '', string $value = '', array|string $attributes = []): string
    {
        return $this->build->input('url', $name, $label, $value, $attributes);
    }

    /**
     * Create a week element.
     */
    public function week(array|string $name, string $label = '', string $value = '', array|string $attributes = []): string
    {
        return $this->build->input('week', $name, $label, $value, $attributes);
    }

    // FORM PARTS -------------------------------------------------------------

    /**
     * Create a closing form tag.
     */
    public function close(): string
    {
        return $this->build->close();
    }

    /**
     * Create a closing fieldset tag.
     */
    public function fieldsetClose(): string
    {
        return $this->build->fieldsetClose();
    }

    /**
     * Create an opening fieldset tag.
     */
    public function fieldsetOpen(string $legend = ''): string
    {
        return $this->build->fieldsetOpen($legend);
    }

    /**
     * Create a closing label tag.
     */
    public function labelClose(): string
    {
        return $this->build->labelClose();
    }

    /**
     * Create an opening label tag.
     */
    public function labelOpen(string $for, string $label): string
    {
        return $this->build->labelOpen($for, $label);
    }

    /**
     * Create an opening form tag.
     */
    public function open(string $action = '/', string $method = 'POST', array|string $attributes = []): string
    {
        return $this->build->open($action, $method, $attributes);
    }

    /**
     * Return the CSRF hidden field for a hand-written form.
     *
     * Forms built with open()/create() already include the CSRF token
     * automatically — use this only when you write your own <form> markup:
     *
     *   <form method="POST">
     *       <?= $form->csrf() ?>
     *       ...
     *   </form>
     *
     * Emits <input type="hidden" name="_token" ...> and stores the token in the
     * session. Returns '' when CSRF is disabled (config 'csrf' => false); uses a
     * framework token generator when one is configured. Native CSRF requires an
     * active session.
     */
    public function csrf(): string
    {
        return $this->build->addCsrfToken();
    }

    /**
     * Create an opening form tag which supports uploading files.
     */
    public function openMultipart(string $action = '/', string $method = 'POST', array|string $attributes = []): string
    {
        return $this->build->open($action, $method, $this->withMultipart($attributes));
    }

    /**
     * Create a submit button.
     */
    public function submit(string $text = 'Submit', array|string $attributes = []): string
    {
        return $this->build->submit($text, $attributes);
    }

    /**
     * Return the current value of a form field.
     *
     * Honors the 'echo' config: in the default echo mode the value is echoed and
     * an empty string is returned; with 'echo' => false the value is returned as
     * a string for the caller to output.
     */
    public function value(string $name, string $value = ''): string
    {
        if ($this->submitted()) {
            // A request value is mixed: PHP turns a crafted `email[]=x` into an
            // array, and sanitizeRequest() would hand that array straight to
            // return(), breaking this method's string contract with a TypeError.
            // Only a non-bool scalar can render as the field's value — the same
            // boundary sanitizeRequest() stringifies at — so anything else
            // clears the field.
            $requestValue = $this->request->post($name) ?? $this->request->query($name);

            if ($requestValue !== null) {
                $value = is_scalar($requestValue) && ! is_bool($requestValue)
                    ? $this->sanitizeRequest($requestValue)
                    : '';
            }
        }

        return $this->support->return($value);
    }

    // BUILDERS & VALIDATORS --------------------------------------------------

    /**
     * Handle an XHR/Ajax form submission.
     */
    private function checkForAndProcessXhrSubmission(array $config): void
    {
        if (! empty($config['ajax']) && $this->submitted()) {
            if ($this->request->isAjax()) {
                $return = [];
                $postData = $this->request->postAll();
                foreach ($postData as $key => $value) {
                    if (! in_array($key, ['_id', '_token', 'submit'])) {
                        $rules = $config['rules'][$key] ?? [];
                        $messages = $config['messages'][$key] ?? [];
                        $return[$key] = $this->validate->input($key, $rules, $messages);
                    }
                }

                if ($this->ok()) {
                    $responseData = [
                        'success' => true,
                        'data' => $return,
                    ];
                } else {
                    $responseData = [
                        'error' => true,
                        'data' => $this->getErrors(),
                    ];
                }

                // Send JSON response through response handlers
                $response = new JsonResponse($responseData);
                $this->handlers->handleJson($response);
            }
        }
    }

    /**
     * Create a form using a comma-separated string of field labels or an array.
     */
    public function create(array|string $fields, array|string $attributes = []): string
    {
        $this->syncFormId($attributes);

        if (is_array($fields) || str_starts_with($fields, '/')) {
            $return = $this->support->return($this->build->fastForm($fields, $attributes));
        } else {
            $return = $this->support->return($this->build->createFormFromString($fields, $attributes));
        }

        // Store the field definition for request() to use — after rendering,
        // because open() resolves the final form id (a form file may carry its
        // own). Storing first filed the definition under the stale constructor
        // id, where a later request() could never find it.
        $this->storeFormDefinition($fields);

        return $return;
    }

    /**
     * Create a form, validate it on submission, and display appropriate messages.
     *
     * This is the simplest way to create a validated form - it handles
     * form creation, validation, success/error messages, and optionally
     * hides the form after successful submission.
     *
     * @param  array|string  $fields  Field definitions (string or array)
     * @param  array|string  $attributes  Form attributes (action, method, id, etc.)
     * @param  callable|null  $onSuccess  Optional callback, runs only when validation passes
     * @param  string  $successMessage  Message shown on success
     * @param  string  $errorMessage  Message shown on validation failure
     * @param  bool  $hideOnSuccess  Hide form after successful submission
     * @return array|string|null Returns validated data when submitted, null otherwise
     */
    public function createAndValidate(
        array|string $fields,
        array|string $attributes = [],
        ?callable $onSuccess = null,
        string $successMessage = 'Thank you for filling out our form!',
        string $errorMessage = 'Please fix the errors',
        bool $hideOnSuccess = true
    ): array|string|null {
        // Echo-mode variant: the message/create calls output as they run, so
        // only the validated data is returned and the strings are dropped.
        [$data] = $this->runValidatedLifecycle(
            $fields, $attributes, $onSuccess, $successMessage, $errorMessage, $hideOnSuccess
        );

        return $data;
    }

    /**
     * Render-first variant of createAndValidate() for echo=false (Blade/Laravel).
     *
     * Handles the submission check, validation, and success/error messages, and
     * returns the form markup (plus messages) as a string so it can be echoed in a
     * template: {!! $form->renderValidated('Name, Email', onSuccess: ...) !!}
     *
     * Unlike createAndValidate(), the return value is always the rendered output,
     * never the data — the validated data is delivered via the $onSuccess callback.
     * In echo=true mode the output is echoed and an empty string is returned.
     *
     * @param  array|string  $fields  Field definitions (string or array)
     * @param  array|string  $attributes  Form attributes (action, method, id, etc.)
     * @param  callable|null  $onSuccess  Optional callback, receives the validated data on success
     * @param  string  $successMessage  Message shown on success
     * @param  string  $errorMessage  Message shown on validation failure
     * @param  bool  $hideOnSuccess  Hide the form after successful submission
     */
    public function renderValidated(
        array|string $fields,
        array|string $attributes = [],
        ?callable $onSuccess = null,
        string $successMessage = 'Thank you for filling out our form!',
        string $errorMessage = 'Please fix the errors',
        bool $hideOnSuccess = true
    ): string {
        // Render-mode variant: the same lifecycle, keeping the markup.
        [, $output] = $this->runValidatedLifecycle(
            $fields, $attributes, $onSuccess, $successMessage, $errorMessage, $hideOnSuccess
        );

        return $output;
    }

    /**
     * The submit-validate-message-render lifecycle createAndValidate() and
     * renderValidated() share. It used to be written twice — the two bodies
     * differed only in whether the emitted strings were accumulated
     * (Support::return() makes echo mode return '' for each piece, so the
     * accumulation is harmless there), and every fix had to land in both
     * by hand (audit 2026-08-15, A18).
     *
     * @return array{0: array|string|null, 1: string} validated data, markup
     */
    private function runValidatedLifecycle(
        array|string $fields,
        array|string $attributes,
        ?callable $onSuccess,
        string $successMessage,
        string $errorMessage,
        bool $hideOnSuccess
    ): array {
        $this->syncFormId($attributes);

        $data = null;
        $output = '';

        if ($this->submitted()) {
            $data = $this->request($fields);

            if ($this->ok()) {
                if ($onSuccess !== null) {
                    $onSuccess($data);
                }
                $output .= $this->successMessage($successMessage);
                if ($hideOnSuccess) {
                    return [$data, $output];
                }
            } else {
                $output .= $this->errorMessage($errorMessage);
            }
        }

        $output .= $this->create($fields, $attributes);

        return [$data, $output];
    }

    /**
     * Create a multipart form using a comma-separated string of field labels or an array.
     */
    public function createMultipart(array|string $fields, array|string $attributes = []): string
    {
        return $this->create($fields, $this->withMultipart($attributes));
    }

    /**
     * Force the multipart flag onto a set of form attributes, whichever shape
     * the caller passed them in ('string' is Build's raw-attribute passthrough).
     */
    private function withMultipart(array|string $attributes): array
    {
        if (is_string($attributes)) {
            $attributes = $attributes === '' ? [] : ['string' => $attributes];
        }

        $attributes['multipart'] = true;

        return $attributes;
    }

    /**
     * Process a submitted form using a comma-separated string of field labels or an array.
     *
     * When called without arguments, automatically uses the field definition from create().
     * When called with explicit fields, uses those instead (allowing stricter server-side rules).
     *
     * Note: the no-argument form retrieves the stored definition by the form's id, which
     * defaults to 'myForm'. When several forms share a session, give each an explicit unique
     * 'id' — in the constructor config or in create()'s attributes — so their stored
     * definitions don't overwrite one another. A form file's own id ('/login' declares
     * 'form-login') is adopted when create() renders it; calling request() before create()
     * on a fresh instance can only know that id from the constructor config.
     *
     * @return string|array|string[]|null
     */
    public function request(array|string|null $fields = null, array|string $rules = [], array $messages = []): string|array|null
    {
        if ($fields === null) {
            $fields = $this->getStoredFormDefinition();

            if ($fields === null) {
                throw new FlickException(
                    "No form definition found for form `{$this->id}`. Call create() before request() without arguments, or provide fields directly to request()."
                );
            }
        }

        return $this->validate->formInput($fields, $rules, $messages);
    }

    /**
     * Adopt a form id passed via create()/createAndValidate() attributes.
     *
     * Build::open() performs the same sync while rendering, but that is too
     * late for submitted()'s _id comparison and for storeFormDefinition(),
     * both of which may run first. The one id source this cannot see is a
     * form file's own attributes ('/login'), which only surface when the file
     * is loaded during rendering — for those, set the id in the constructor
     * config instead.
     */
    private function syncFormId(array|string $attributes): void
    {
        if (is_array($attributes) && isset($attributes['id'])) {
            $this->adoptFormId((string) $attributes['id']);
        }
    }

    /**
     * Set the form's id everywhere it is read.
     *
     * The id lives in three places - this property, the config array, and the
     * Support instance's own copy of the config, which services read lazily so
     * their client JS targets the right form. Two call sites wrote all three by
     * hand; a third writer that forgot one would leave submitted() matching a
     * different id than the rendered _id field carries.
     *
     * $this->id is a read-only view for callers. Assigning to it directly does
     * not update the other two.
     *
     * @internal public so Build's open() can call it through $this->flick; not
     * part of the developer API
     */
    public function adoptFormId(string $id): void
    {
        $this->id = $id;
        $this->config['id'] = $id;
        $this->support->setConfigValue('id', $id);
    }

    /**
     * Store the form field definition in session for later retrieval by request().
     */
    private function storeFormDefinition(array|string $fields): void
    {
        if ($this->sessionIsActive()) {
            $definitions = $this->session->getValue('_form_definitions') ?? [];
            $definitions[$this->id] = $fields;
            $this->session->setValue('_form_definitions', $definitions);
        }
    }

    /**
     * Retrieve a stored form definition from session.
     */
    private function getStoredFormDefinition(): array|string|null
    {
        if ($this->sessionIsActive()) {
            $definitions = $this->session->getValue('_form_definitions') ?? [];

            return $definitions[$this->id] ?? null;
        }

        return null;
    }

    // MULTISTEP --------------------------------------------------------------

    /**
     * Multistep state is scoped to the form id so two multistep forms sharing
     * a session can't overwrite each other's progress.
     */
    private function multistepKey(string $key): string
    {
        return '_multistep_'.$this->id.'_'.$key;
    }

    private function addMultistepValue(string $key, mixed $value): void
    {
        $this->addSessionValue($this->multistepKey($key), $value);
    }

    private function getMultistepValue(string $key): mixed
    {
        return $this->getSessionValue($this->multistepKey($key));
    }

    private function hasMultistepValue(string $key): bool
    {
        return $this->hasSessionValue($this->multistepKey($key));
    }

    /**
     * Read a multistep list (steps, completedSteps, formFields) from the session.
     *
     * Stored as plain arrays. Sessions written before 1.0 carried JSON strings,
     * so a string is decoded once here; a corrupt or missing payload yields an
     * empty list rather than a TypeError.
     *
     * @return list<string>
     */
    private function multistepList(string $key): array
    {
        $value = $this->getMultistepValue($key);

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? array_values($value) : [];
    }

    /**
     * Create a multistep form.
     */
    public function createMultistep(array $form, array $options = []): array|string|null
    {
        // form has been submitted; no need to continue
        if ($this->multistepIsComplete()) {
            return null;
        }

        // make sure we have an active session
        if (! $this->sessionIsActive()) {
            throw FlickException::sessionIsRequired();
        }

        $auto = true;

        if (array_key_exists('auto', $options) && $options['auto'] === false) {
            $auto = false;
        }

        // persist this form's step data to the session; the flag is scoped to
        // the form id so an abandoned flow can't leak persistence into an
        // unrelated form
        $this->addSessionValue('_persist_'.$this->id, true);

        // get the name of each DATA step and add it to the session; the
        // synthetic 'Review' entry is presentation, appended by the public
        // getters, never stored (a data step may itself be named Review)
        $this->addMultistepValue('dataSteps', array_keys($form));

        // determine the current step
        $currentStep = $this->multistepResolveCurrentStep($form);

        // PHP already url-decoded the query string once when it built $_GET;
        // decoding again would corrupt step names containing '+' or literal
        // %XX sequences (the breadcrumb links encode exactly once)
        $requestedStep = $this->requestedStep();

        if ($requestedStep !== null && $this->multistepStepIsReachable($requestedStep, $form)) {
            if (array_key_exists($requestedStep, $form)) {
                // reopening a DATA step — even one named 'Review' — exits the
                // review phase; the local copy must follow the session write or
                // the step lookup below would still use the stale name
                $this->addMultistepValue('currentStep', $requestedStep);
                $this->addMultistepValue('inReview', false);
                $currentStep = $requestedStep;
            } else {
                // the synthetic Review breadcrumb: (re)enter the review phase
                $this->addMultistepValue('inReview', true);
            }

            if (! array_key_exists('testMode', $options)) {
                // Post/redirect/get when the step change followed a submission.
                // On a plain breadcrumb click this is a no-op — redirect() only
                // acts once the form is submitted and valid — and the request
                // goes on to render $requestedStep below.
                $this->redirect();
            } else {
                return [
                    'nextStep' => $requestedStep,
                    'completedSteps' => $this->multistepCompletedSteps(),
                    'sessionData' => $this->session->getAll(),
                ];
            }
        } else {
            // add the form's fields to the session
            $formFields = [];
            foreach ($form as $index => $step) {
                foreach ($step['fields'] as $key => $field) {
                    $name = array_key_exists('name', $field) ? $field['name'] : $key;
                    $formFields[] = $name;
                }

                // determine the button text for the 'next' button
                if (! array_key_exists('button', $step)) {
                    $nextText = $options['nextText'] ?? 'Next';

                    $form[$index]['button']['text'] = $nextText;
                }
            }
            $this->addMultistepValue('formFields', $formFields);
        }

        // accumulate auto-mode markup so it is returned (not just echoed); in echo
        // mode support->return() echoes and yields '', so this is correct either way
        $return = '';

        if ($auto) {
            if (! $this->multistepIsComplete()) {
                $return .= $this->multistepBreadcrumbs($form);
                if ($this->multistepIsInReview()) {
                    $reviewTitle = array_key_exists('reviewTitle', $options) ? $options['reviewTitle'] : 'Please Review the Information';
                    $return .= $this->support->return($this->renderMultistepView('multistep-heading', [
                        'title' => htmlspecialchars($reviewTitle),
                    ]));
                } else {
                    $return .= $this->support->return($this->renderMultistepView('multistep-heading', [
                        'title' => $this->multistepCurrentStep($form),
                    ]));
                }
            }
        }

        // all steps have been completed; let the user review the form.
        // multistepIsInReview() tests this same predicate and nothing between
        // here and there mutates currentStep, so the nested check it used to
        // carry could only ever be true.
        if ($this->multistepIsInReview()) {
            if ($auto) {
                $reviewText = array_key_exists('reviewText', $options) ? $options['reviewText'] : 'Review Your Information.';
                $submitText = array_key_exists('submitText', $options) ? $options['submitText'] : 'Submit Form';
                $return .= $this->support->return($this->renderMultistepView('multistep-review', [
                    'reviewText' => $reviewText,
                    'submitText' => $submitText,
                ]));
            }

            return $return;
        }

        // save the current step
        $this->addMultistepValue('currentStep', $currentStep);

        // get the form fields for the current step
        $stepData = $form[$currentStep];

        if ($this->submitted() && $this->requestedStep() === null) {
            // process and validate the form's step
            $this->request($stepData);

            if ($this->ok()) {
                $dataSteps = array_keys($form);
                $currentIndex = array_search($currentStep, $dataSteps);

                $completedSteps = $this->multistepList('completedSteps');
                $completedSteps[] = $currentStep;
                $this->addMultistepValue('completedSteps', array_values(array_unique($completedSteps)));

                $nextStep = $dataSteps[$currentIndex + 1] ?? null;

                if ($nextStep === null) {
                    // the last data step has been validated; the flow enters
                    // its review phase — a stored flag, not a magic step name,
                    // so a data step named 'Review' can't be mistaken for it
                    $this->addMultistepValue('inReview', true);
                } else {
                    $this->addMultistepValue('currentStep', $nextStep);
                }

                if (array_key_exists('testMode', $options)) {
                    return [
                        // the synthetic Review step is presentation only
                        'nextStep' => $nextStep ?? 'Review',
                        'completedSteps' => $this->multistepList('completedSteps'),
                        'sessionData' => $this->session->getAll(),
                    ];
                }

                $this->redirect();
            }
        }

        // only render the instructions paragraph when the step actually has one
        if (! empty($stepData['text'])) {
            $return .= $this->support->return('<p>'.htmlspecialchars($stepData['text']).'</p>');
        }

        // multistep options are configuration, not markup; strip them so they
        // don't render as attributes on the <form> tag
        $attributes = array_diff_key(
            $options,
            array_flip(['auto', 'nextText', 'reviewTitle', 'reviewText', 'submitText', 'testMode'])
        );

        $return .= $this->create($stepData, $attributes);

        return $return;
    }

    /**
     * Add breadcrumbs to a multistep form.
     */
    public function multistepBreadcrumbs(array $form): string
    {
        return $this->support->return($this->renderMultistepView('breadcrumbs', ['form' => $form]));
    }

    /**
     * Render a per-theme multistep view file ({views}/{view}.view.php).
     *
     * The view runs with $this bound to the form instance and the given vars
     * extracted into its scope, exactly like the field-element views.
     */
    private function renderMultistepView(string $view, array $vars = []): string
    {
        // resolve() is assets-first, so a multistep template can be overridden
        // the same way a field template already could. Rendering stays an
        // include with $this bound - only path resolution is shared.
        $path = $this->views->resolve($view);

        if (! is_file($path)) {
            throw FlickException::viewFileNotFound($path);
        }

        extract($vars);

        ob_start();

        include $path;

        return ob_get_clean();
    }

    /**
     * The data steps recorded as validated, exactly as stored.
     *
     * @return list<string>
     */
    private function rawCompletedSteps(): array
    {
        return $this->multistepList('completedSteps');
    }

    /**
     * The DATA step names, as stored — no synthetic 'Review' entry.
     *
     * Pre-1.0 sessions stored the list under 'steps' with the synthetic
     * 'Review' appended; strip that one trailing entry on read. (A data step
     * legitimately named Review sat BEFORE the synthetic tail in those
     * sessions, so at most one entry goes. createMultistep() rewrites the
     * list under the new key on its next run.)
     *
     * @return list<string>
     */
    private function multistepDataSteps(): array
    {
        if ($this->hasMultistepValue('dataSteps')) {
            return $this->multistepList('dataSteps');
        }

        $legacy = $this->multistepList('steps');

        if ($legacy !== [] && end($legacy) === 'Review') {
            array_pop($legacy);
        }

        return $legacy;
    }

    /**
     * Has every DATA step been validated?
     *
     * Derived, not stored. This used to be its own session flag written in two
     * places, which could disagree with the completed-step list it summarised -
     * and a session carrying the flag but no steps opened the ?step=submit
     * gate.
     *
     * The `!== []` guard is load-bearing: array_diff([], $x) === [] is
     * vacuously true, so an empty step list would otherwise read as complete.
     */
    private function allDataStepsCompleted(): bool
    {
        $dataSteps = $this->multistepDataSteps();

        return $dataSteps !== [] && array_diff($dataSteps, $this->rawCompletedSteps()) === [];
    }

    /**
     * Get the completed steps of a multistep form.
     */
    public function multistepCompletedSteps(): array
    {
        $steps = $this->rawCompletedSteps();

        if ($steps !== [] && $this->allDataStepsCompleted()) {
            $steps[] = 'Review';
        }

        return $steps;
    }

    /**
     * Get the current step of a multistep form.
     *
     * Presents 'Review' during the review phase — the breadcrumb views key
     * their active crumb off this — while the stored current step stays a
     * real $form key underneath.
     *
     * Returns the RAW step name. It used to return htmlspecialchars() of it,
     * which broke the comparison every breadcrumb view makes against
     * multistepSteps() (raw $form keys): a step named with &, <, >, " or ' never
     * matched, so the active crumb was never highlighted. All seven views escape
     * the name at output already, so escaping here was redundant for display and
     * harmful for comparison. Escaping is the renderer's job.
     *
     * Echoing this value directly into HTML is therefore the caller's to escape.
     */
    public function multistepCurrentStep(array $form): string
    {
        if ($this->multistepIsInReview()) {
            return 'Review';
        }

        return $this->multistepResolveCurrentStep($form);
    }

    /**
     * The data step the form is on: the stored name while the definition
     * still has it, else the first step.
     *
     * Three readers used to derive this on their own, none checking the
     * stored name against $form, so a session that outlived a renamed step
     * rendered the stale heading, warned on the undefined key, then threw a
     * TypeError out of create(). createMultistep() writes the resolved name
     * back to the session, which is how a stale one gets repaired.
     */
    private function multistepResolveCurrentStep(array $form): string
    {
        $stored = $this->getMultistepValue('currentStep');

        if (is_string($stored) && $stored !== '' && array_key_exists($stored, $form)) {
            return $stored;
        }

        return (string) array_key_first($form);
    }

    /**
     * Get the submitted form data from a multistep form.
     *
     * By default this also clears the entire Flick session — not just the
     * multistep data — so read anything you want to keep first. Pass
     * $clear = false to leave the session intact.
     */
    public function multistepFormData(bool $clear = true): array
    {
        $return = $this->multistepReviewData();

        if ($clear) {
            $this->destroySession(false);
        }

        return $return;
    }

    /**
     * The ?step= navigation target, as a string.
     *
     * A query value is mixed: `?step[]=x` arrives as an array, which would fail
     * the string functions and string-typed methods every caller feeds it to.
     * A non-string target names no step, so it reads as no step at all.
     */
    private function requestedStep(): ?string
    {
        $step = $this->request->query('step');

        return is_string($step) ? $step : null;
    }

    /**
     * Check if a multistep form has been completed.
     */
    public function multistepIsComplete(): bool
    {
        $step = $this->requestedStep();

        // the form is only complete when the submit action follows a run through
        // every step, so a bare ?step=submit on an untouched session can't fake
        // completion
        return $step !== null && strtolower($step) === 'submit' && $this->allDataStepsCompleted();
    }

    /**
     * Determine whether a requested ?step= jump target is legitimately reachable:
     * an already-completed step, the current step, or the submit action once every
     * step is complete. Prevents skipping ahead to Review/submit without validating.
     */
    private function multistepStepIsReachable(string $step, array $form): bool
    {
        if (strtolower($step) === 'submit') {
            return $this->allDataStepsCompleted();
        }

        $currentStep = $this->multistepResolveCurrentStep($form);
        $reachable = array_merge($this->multistepCompletedSteps(), [$currentStep]);

        return in_array($step, $reachable, true);
    }

    /**
     * Check if a multistep form is ready for, or is in, review.
     *
     * The phase is a stored flag, not the step name: nothing stops a
     * developer from naming one of their OWN data steps Review, and encoding
     * the phase as currentStep='Review' made that step's fields unreachable
     * once the form completed. Completion still gates the phase either way —
     * a seeded flag on an incomplete run must not open the review screen.
     */
    public function multistepIsInReview(): bool
    {
        if (! $this->allDataStepsCompleted()) {
            return false;
        }

        // The phase is a stored flag, never a magic step name - so a data step
        // genuinely named 'Review' cannot be mistaken for the review screen.
        return (bool) $this->getMultistepValue('inReview');
    }

    /**
     * Get the submitted form data from a multistep form.
     */
    public function multistepReviewData(): array
    {
        $sessionValues = [];

        $fields = $this->multistepList('formFields');

        foreach ($fields as $key) {
            $sessionValues[$key] = $this->getSessionValue($key);
        }

        return $sessionValues;
    }

    /**
     * Get the steps of a multistep form.
     *
     * The trailing 'Review' entry is presentation for breadcrumbs; only the
     * data steps are stored.
     */
    public function multistepSteps(array $form = []): array
    {
        if ($this->hasMultistepValue('dataSteps') || $this->hasMultistepValue('steps')) {
            $steps = $this->multistepDataSteps();

            // a corrupt or empty stored list presents no steps at all rather
            // than a lone synthetic Review crumb
            if ($steps !== []) {
                $steps[] = 'Review';
            }

            return $steps;
        } elseif (! empty($form)) {
            $return = [];
            foreach ($form as $index => $step) {
                $return[] = $index;
            }

            return $return;
        }

        return [];
    }

    /**
     * Generate the submit button for a multistep form.
     */
    public function submitMultistep(string $text = 'Submit Form', array|string $attributes = []): string
    {
        if (is_array($attributes)) {
            $string = '';
            foreach ($attributes as $key => $value) {
                // same attribute-name sanitizing the element builder applies
                $safeKey = preg_replace('/[^A-Za-z0-9_:.-]/', '', trim((string) $key));
                if ($safeKey === '') {
                    continue;
                }
                $string .= ($string === '' ? '' : ' ');
                $string .= $value === true ? $safeKey : $safeKey.'="'.htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8').'"';
            }
            $attributes = $string;
        }

        // developer-supplied attributes replace the theme's default styling
        // wholesale, so a custom class doesn't collide with a second class
        // attribute; the theme view supplies the default when this is empty
        return $this->support->return($this->renderMultistepView('multistep-submit', [
            'text' => $text,
            'attributesString' => trim($attributes),
        ]));
    }

    // FLICK ------------------------------------------------------------------

    /**
     * Resolve where this form's CSRF token comes from — one interpretation of
     * the csrf config × installed generator, shared by the emitter
     * (Build::addCsrfToken) and the checker (checkForAndValidateCsrfToken) so
     * the two cannot drift. They did: 'strict' plus a null-yielding generator
     * once rejected every POST while a valid native token sat in the payload,
     * because rendering keyed off "token came back" and validation keyed off
     * "closure exists" (CHANGELOG).
     *
     * 'framework': a generator is installed, native CSRF was not explicitly
     * requested (true or an integer timeout), and the generator yielded a
     * non-empty string — returned alongside. Null and '' both mean the
     * framework has no token, and the decision falls through to 'none'
     * (csrf disabled) or 'native' (Flick's own session token).
     *
     * Invoking the generator is part of resolving, so callers that must not
     * run it (a disabled check) have to decide before calling this.
     *
     * @internal shared with Build; not public API
     *
     * @return array{source: 'framework'|'native'|'none', token: ?string}
     */
    public function resolveCsrfTokenSource(): array
    {
        $csrf = $this->config('csrf');

        if (self::$defaultCsrfTokenGenerator !== null && $csrf !== true && ! is_int($csrf)) {
            $token = (self::$defaultCsrfTokenGenerator)();

            if (is_string($token) && $token !== '') {
                return ['source' => 'framework', 'token' => $token];
            }
        }

        if ($csrf === false) {
            return ['source' => 'none', 'token' => null];
        }

        return ['source' => 'native', 'token' => null];
    }

    private function checkForAndValidateCsrfToken(): bool
    {
        // If CSRF is disabled, always return true — decided before resolving
        // the token source, so a CSRF-disabled form never invokes the
        // generator at all. (Rendering may still emit the framework's token
        // for its middleware to use; Flick itself has nothing to check.)
        if ($this->config('csrf') === false) {
            return true;
        }

        $policy = $this->resolveCsrfTokenSource();

        if ($policy['source'] === 'framework') {
            // 'strict' asks Flick to compare the posted token against the
            // framework's own token rather than assume the framework's
            // middleware already did. That assumption holds for a route inside
            // Laravel's `web` group and not for one outside it, where nothing
            // checks the token at all.
            //
            // Opt-in on purpose: a client that sends the token only as a header
            // (Axios sends X-XSRF-TOKEN automatically) posts no _token field,
            // and rejecting that would fail the submission with nothing on
            // screen to explain why. With no framework token the source
            // resolves to 'native' instead, and the check below decides against
            // the native token that rendering actually issued.
            if ($this->config('csrf') === 'strict') {
                $posted = $this->request->post('_token');

                if (is_string($posted) && $posted !== '' && hash_equals($policy['token'], $posted)) {
                    return true;
                }

                $this->reportCsrfFailure($this->config['applicationMessages']['InvalidSecurityToken']);

                return false;
            }

            // The framework's own middleware already validated the posted
            // token, so Flick treats CSRF as satisfied instead of re-validating
            // against a native session token it never issued.
            return true;
        }

        // A token was posted and the session still holds one: this is the only
        // validation path. Return its result directly — falling through would let
        // the expired branch (which deletes _token) fire a second expired message.
        if ($this->request->hasPost('_token') && $this->session->hasValue('_token')) {
            if ($this->validateCsrfToken()) {
                return true;
            }

            // A mismatching token used to fail silently: submitted() returned
            // false, the error bag stayed empty, and the form simply re-rendered,
            // so the visitor clicked Submit and saw nothing happen.
            $this->reportCsrfFailure($this->config['applicationMessages']['InvalidSecurityToken']);

            return false;
        }

        // Token was posted but session token is missing (session expired)
        if ($this->request->hasPost('_token') && ! $this->session->hasValue('_token')) {
            $this->reportCsrfFailure($this->config['applicationMessages']['SessionHasExpired']);
        }

        return false;
    }

    private function checkForHoneypot(array $config): void
    {
        if (! empty($config['honeypot'])) {
            $honeypotField = $config['honeypot'];

            // presence check, not empty(): a honeypot filled with "0" is still spam
            if ($this->request->isMethod('POST')) {
                $value = $this->request->post($honeypotField);
                if ($value !== null && $value !== '') {
                    $this->handlers->handleHoneypot();

                    return;
                }
            }

            if ($this->request->isMethod('GET')) {
                $value = $this->request->query($honeypotField);
                if ($value !== null && $value !== '') {
                    $this->handlers->handleHoneypot();

                    return;
                }
            }
        }
    }

    /**
     * Install the class-owned exception dispatcher, at most once.
     *
     * The dispatcher forwards to the most recently constructed standalone
     * form. The active handler is read via the push/pop trick (PHP has no
     * read-only accessor), so a dispatcher popped externally — a test
     * unwinding the stack — is detected and reinstalled rather than stacked.
     */
    private static function installGlobalExceptionHandler(self $host): void
    {
        self::$exceptionHandlerHost = $host;

        self::$exceptionHandlerDispatcher ??= static function (\Throwable $exception): void {
            self::$exceptionHandlerHost?->globalExceptionHandler($exception);
        };

        $active = set_exception_handler(null);
        restore_exception_handler();

        if ($active === self::$exceptionHandlerDispatcher) {
            return;
        }

        self::$previousExceptionHandler = $active;
        set_exception_handler(self::$exceptionHandlerDispatcher);
    }

    public function globalExceptionHandler(\Throwable $exception): void
    {
        ExceptionRenderer::render($exception, $this->handlers, (bool) ($this->config['debug'] ?? false));

        if (self::$previousExceptionHandler !== null) {
            (self::$previousExceptionHandler)($exception);
        }
    }

    /**
     * Load a language file and return its messages.
     *
     * Language files must return a plain array. A file-scope `const` cannot
     * work here: constants live for the whole PHP process, so under
     * worker-mode runtimes (Octane, FrankenPHP) the first request's locale
     * would win for every later request served by that process. `require`
     * (not `require_once`) is load-bearing for the same reason — a second
     * instance must re-execute the file to get its own copy of the array.
     */
    private function loadLanguageFile(string $filePath): array
    {
        if (! file_exists($filePath)) {
            throw FlickException::languageFileNotFound($filePath);
        }

        $messages = require $filePath;

        if (! is_array($messages)) {
            throw FlickException::languageFileInvalid($filePath);
        }

        return $messages;
    }

    private function manageCache(): void
    {
        if (! $this->config('cache')) {
            return;
        }

        if (! $this->config('assets')) {
            throw FlickException::cachingIsDisabled();
        }

        // Caching is the only thing that writes into the assets directory, so
        // this is the only place writability is demanded.
        if (! is_writable($this->config['assets'])) {
            throw FlickException::assetsDirectoryNotWritable($this->config['assets']);
        }

        if ($this->config('cache') === 'flush') {
            $this->flushCache();
        }
    }

    /**
     * Merge the static default config beneath the given instance config.
     *
     * Instance config wins. String config (the views shorthand) is normalized to
     * ['views' => ...] only when a default actually exists, so the string+no-default
     * path is byte-for-byte unchanged.
     *
     * @param  array|string  $config  Instance config
     * @return array|string Merged config
     */
    private function mergeDefaultConfig(array|string $config): array
    {
        // Normalize once at the boundary: a string is the views shorthand
        // ('' carries no theme name). Everything after the constructor's
        // merge call works with an array.
        $instance = is_string($config)
            ? ($config !== '' ? ['views' => $config] : [])
            : $config;

        if (self::$defaultConfig === []) {
            return $instance;
        }

        return array_replace_recursive(self::$defaultConfig, $instance);
    }

    private function setApplicationConfig(array $config): void
    {
        // Live collaborators were already resolved onto typed properties
        // ($this->request, $this->session, $this->handlers); the settings bag
        // holds only scalars and plain arrays. Leaving the session adapter in
        // here is what once made a truthy config('session') read as "persist
        // everything" (#11).
        unset(
            $config['request'],
            $config['session'],
            $config['handlers'],
            $config['onRedirect'],
            $config['onJson'],
            $config['onException'],
            $config['onHoneypot'],
            $config['onCsrfExpired'],
        );

        $array = $config;

        $array['action'] = $config['action'] ?? $this->request->uri() ?? '/';
        $array['assets'] = $config['assets'] ?? null;
        $array['dateFormat'] = $config['dateFormat'] ?? 'Y-m-d';
        $array['debug'] = $config['debug'] ?? false;
        $array['echo'] = $config['echo'] ?? true;
        $array['id'] = $config['id'] ?? 'myForm';
        $array['lang'] = $config['lang'] ?? 'en';
        $array['trim'] = $config['trim'] ?? true;
        $array['views'] = ! empty($array['views']) ? $array['views'] : 'flick';

        $this->config = array_replace_recursive($this->config, $array);

        // The assets directory is the developer's; Flick reads from it on
        // every request, so a wrong path fails here rather than later as a
        // missing view or language file. Empty counts as unset, the same as
        // everywhere else that tests config('assets') for truthiness.
        if (! empty($this->config['assets']) && ! is_dir($this->config['assets'])) {
            throw FlickException::assetsDirectoryNotFound($this->config['assets']);
        }

        // Not adoptFormId(): Support does not exist yet at this point in the
        // constructor. It is built from $this->config below and reads the id
        // from there.
        $this->id = $this->config['id'];
    }

    /**
     * Load the form's language files.
     */
    private function setApplicationLanguage(array $config): void
    {
        $shipped = __DIR__.'/../lang/en';

        if (array_key_exists('lang', $config)) {
            $base = array_key_exists('assets', $config)
                ? rtrim($config['assets'], '/').'/lang/'.substr($config['lang'], 0, 2)
                : __DIR__.'/../lang/'.substr($config['lang'], 0, 2);
        } else {
            // the default language
            $base = $shipped;
        }

        $this->config['validationMessages'] = $this->loadLanguageFiles($shipped, $base, 'rules.php');
        $this->config['applicationMessages'] = $this->loadLanguageFiles($shipped, $base, 'messages.php');
    }

    /**
     * The shipped English file with the selected language's file laid over
     * it, key by key.
     *
     * A translation may be partial: a key it leaves out falls back to the
     * English text, and a Flick release that adds a rule does not break
     * every existing translation. Only the KEY is soft - a missing FILE still
     * throws from loadLanguageFile(), the loud dev-time failure that
     * AUDIT-FOLLOWUPS decision #7 kept. Before this, the first read of a
     * missing key was an undefined-index warning and then a TypeError.
     */
    private function loadLanguageFiles(string $shipped, string $base, string $file): array
    {
        $english = $this->loadLanguageFile($shipped.'/'.$file);

        if ($base === $shipped) {
            return $english;
        }

        return array_replace($english, $this->loadLanguageFile($base.'/'.$file));
    }

    /**
     * Initialize response handlers from config or use defaults.
     *
     * Supports both pre-configured ResponseHandlers instance or
     * individual callback overrides via config keys.
     */
    private function initializeHandlers(array $config): void
    {
        // Use provided handlers or create default
        if (isset($config['handlers']) && $config['handlers'] instanceof ResponseHandlers) {
            $this->handlers = $config['handlers'];
        } else {
            $this->handlers = new ResponseHandlers;
        }

        // Support individual callback overrides (Flick-style convenience)
        if (isset($config['onRedirect']) && is_callable($config['onRedirect'])) {
            $this->handlers->onRedirect($config['onRedirect']);
        }
        if (isset($config['onJson']) && is_callable($config['onJson'])) {
            $this->handlers->onJson($config['onJson']);
        }
        if (isset($config['onException']) && is_callable($config['onException'])) {
            $this->handlers->onException($config['onException']);
        }
        if (isset($config['onHoneypot']) && is_callable($config['onHoneypot'])) {
            $this->handlers->onHoneypot($config['onHoneypot']);
        }
        if (isset($config['onCsrfExpired']) && is_callable($config['onCsrfExpired'])) {
            $this->handlers->onCsrfExpired($config['onCsrfExpired']);
        }
    }

    /**
     * Surface a CSRF failure through both channels, once per instance.
     *
     * The error bag is the channel that matters: errorMessage() only echoes,
     * so with 'echo' => false (the Blade/Laravel mode) it produced nothing at
     * all and the failure was invisible. submitted() is called several times
     * during a single render, hence the guard.
     */
    private function reportCsrfFailure(string $message): void
    {
        if ($this->csrfFailureReported) {
            return;
        }

        $this->csrfFailureReported = true;

        $this->addError('_token', $message);

        $this->errorMessage($message);
    }

    private function validateCsrfToken(): bool
    {
        $token = $this->request->post('_token', '');

        // A non-string _token (e.g. an attacker posting _token[]=x) must be
        // treated as invalid, not handed to hash_equals(), which throws a
        // TypeError on a non-string argument.
        if (! is_string($token) || $token === '') {
            return false;
        }

        $sessionToken = $this->session->getValue('_token');

        // check if the token in SESSION equals the posted token value
        if ($sessionToken !== null && hash_equals($sessionToken, $token)) {
            // check expiration from server-side session value
            $expires = $this->session->getValue('_token_expires');
            if ($expires !== null && time() > (int) $expires) {
                $message = $this->config['applicationMessages']['SessionHasExpired'];
                $this->reportCsrfFailure($message);

                // reset the token and expiration
                $this->session->deleteValue('_token');
                $this->session->deleteValue('_token_expires');

                $this->handlers->handleCsrfExpired($message);

                return false;
            }

            return true;
        }

        return false;
    }
}
