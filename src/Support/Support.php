<?php

declare(strict_types=1);

namespace Flick\Support;

use Flick\Http\NativeRequest;
use Flick\Http\RequestInterface;
use Flick\Session\SessionInterface;

/**
 * Shared utilities available to all Flick services.
 *
 * Support provides centralized access to:
 * - Error handling: Add and retrieve validation/runtime errors
 * - Session management: Store and retrieve session values
 * - Configuration: Access Flick config with dot notation
 * - Request adapter: Access HTTP request data
 *
 * Support shares the Helpers trait with Flick, but a handful of the inherited
 * methods only work on a full Flick instance — they reach for $build,
 * $services, $id, or Flick-private CSRF plumbing that Support does not have.
 * Those methods are overridden below to throw a LogicException naming the
 * real owner, instead of dying on an undefined property mid-call (or, for
 * persistingToSession(), silently checking the wrong session key).
 *
 * Services receive Support via dependency injection and use it
 * to communicate errors back to the main Flick instance.
 *
 * @example
 * // In a service:
 * if ($operationFailed) {
 *     $this->support->addError('servicename', 'What went wrong');
 *     return false;
 * }
 *
 * // Access request data:
 * $email = $this->support->request()->post('email');
 *
 * // Access session:
 * $session = $this->support->session();
 */
class Support
{
    use Helpers;

    /**
     * Reject a loader name that could escape the directory it is looked up in.
     *
     * Every name Flick interpolates into a file path — a dropdown list, a form
     * definition, a view — is a plain identifier the developer chose, like
     * 'states' or 'registration'. Nothing shipped passes anything else. Without
     * this check, string-driven configuration is one
     * `$form->select('x', 'X', '', $_GET['list'])` away from local file
     * inclusion in someone else's application.
     *
     * @throws \InvalidArgumentException when the name is empty or not a plain identifier
     */
    public static function assertSafeLoaderName(string $name): string
    {
        if ($name === '' || preg_match('/^[A-Za-z0-9_-]+$/', $name) !== 1) {
            throw new \InvalidArgumentException(
                'Flick loader names may only contain letters, numbers, underscores and hyphens; got "'.$name.'".'
            );
        }

        return $name;
    }

    /**
     * The path a request arrived on, with any query string removed.
     *
     * Fills in wherever Flick has to name the current URL itself: an omitted
     * form action, and redirect()'s default target. SCRIPT_NAME and PHP_SELF
     * used to do that job, but they only name the current URL when that URL
     * maps to a PHP file. Behind a front controller — Laravel, or any rewrite
     * to index.php — they point at the front controller instead, so a form
     * rendered at /contact posted to /index.php and a multistep step redirected
     * there too.
     *
     * The query string is dropped deliberately. createMultistep() only
     * validates and advances a step while no ?step= is present, so a target
     * that carried one forward would stall the flow on the same step.
     */
    public static function requestPath(RequestInterface $request): string
    {
        $path = parse_url($request->uri(), PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }

    /**
     * Build the ?step= navigation target for a multistep breadcrumb or submit
     * link, keeping whatever query string the url already carries.
     *
     * The multistep views used to glue this together by hand — '<action>/?step='
     * — which produced a second '?' on any page that already had a query string
     * (/signup?ref=abc/?step=Contact). PHP reads that as ref="abc/?step=Contact"
     * and never sets $_GET['step'], so back-navigation and the final submit
     * silently did nothing.
     *
     * An existing 'step' is replaced rather than appended: a breadcrumb rendered
     * on a page that already carries one must target its own step, not inherit
     * the current one.
     */
    public static function stepUrl(string $url, string $step): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';

        $existing = parse_url($url, PHP_URL_QUERY);
        $params = [];

        if (is_string($existing) && $existing !== '') {
            parse_str($existing, $params);
        }

        $params['step'] = $step;

        return $path.'?'.http_build_query($params);
    }

    protected RequestInterface $request;

    protected ?SessionInterface $session = null;

    public function __construct(
        protected array $config,
        protected Errors $errors,
        ?RequestInterface $request = null
    ) {
        $this->request = $request ?? new NativeRequest;
    }

    /**
     * Update a single config value after construction.
     *
     * The form id can change at render time (open() may take it from the
     * form's attributes), and services resolve config through this shared
     * instance — the change must land here for them to see it.
     */
    public function setConfigValue(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }

    /**
     * Get the request adapter for accessing HTTP request data.
     *
     * Pro services use this to access POST, GET, SERVER, etc.
     */
    public function request(): RequestInterface
    {
        return $this->request;
    }

    /**
     * Get the session adapter for accessing session data.
     *
     * Pro services use this to access session values.
     *
     * @return SessionInterface|null The session adapter or null if not set
     */
    public function session(): ?SessionInterface
    {
        return $this->session;
    }

    /**
     * Set the session adapter.
     *
     * Called by Flick after session resolution to make
     * the session available to services.
     *
     * @param  SessionInterface  $session  The session adapter
     */
    public function setSession(SessionInterface $session): void
    {
        $this->session = $session;
    }

    public function return(string $value): string
    {
        if (! isset($this->config['echo']) || $this->config['echo'] === false) {
            return $value;
        }

        echo $value;

        return '';
    }

    /**
     * One of Flick's own end-user texts, in the form's language.
     *
     * Reads the merged map Flick built in setApplicationLanguage() - the
     * shipped lang/en/messages.php with the selected translation laid over it
     * key by key - so a service's message translates through the same file
     * core's do, and a translation that leaves the key out still reads the
     * English. Placeholders use the :name style of rules.php:
     *
     *     $this->support->message('UploadFileTooLarge', ['size' => '3 MB', 'max' => '2 MB']);
     *
     * There is no English default at the call site: the shipped file is the
     * single source of truth, and a key it lacks is a Flick bug (pinned by
     * MessageKeysContractTest in each suite), so it throws rather than
     * returning the key. A Support built by hand with no map - a test, or a
     * developer exercising their own service - reads the shipped English
     * directly, the same text Flick uses when no `lang` is configured.
     *
     * Lives on Support rather than the shared Helpers trait: services are the
     * only caller, and Flick's own reads of the map need no placeholders.
     *
     * @param  array<string, scalar>  $replacements  placeholder name => value, without the leading colon
     *
     * @throws \LogicException when the key is not in the language map
     */
    public function message(string $key, array $replacements = []): string
    {
        $messages = $this->config['applicationMessages'] ?? self::shippedMessages();

        if (! array_key_exists($key, $messages)) {
            throw new \LogicException(
                "No '{$key}' entry in Flick's lang/en/messages.php - add the key to the shipped file."
            );
        }

        $placeholders = array_map(static fn (string $name): string => ':'.$name, array_keys($replacements));

        return str_replace($placeholders, array_map('strval', $replacements), $messages[$key]);
    }

    private static ?array $shippedMessages = null;

    private static function shippedMessages(): array
    {
        return self::$shippedMessages ??= require __DIR__.'/../../lang/en/messages.php';
    }

    /*
    |--------------------------------------------------------------------------
    | Flick-only trait methods (see the class docblock)
    |--------------------------------------------------------------------------
    | clear() and redirect() need no override of their own: both call
    | submitted() first, so they fail through the same guard.
    */

    public function submitted(): bool
    {
        throw self::flickOnly(__FUNCTION__);
    }

    public function flushCache(): void
    {
        throw self::flickOnly(__FUNCTION__);
    }

    public function persistingToSession(): bool
    {
        throw self::flickOnly(__FUNCTION__);
    }

    public function errors(string $heading = ''): string
    {
        throw self::flickOnly(__FUNCTION__);
    }

    public function errorMessage(string $message, string $heading = ''): string
    {
        throw self::flickOnly(__FUNCTION__);
    }

    public function infoMessage(string $message, string $heading = ''): string
    {
        throw self::flickOnly(__FUNCTION__);
    }

    public function successMessage(string $message, string $heading = ''): string
    {
        throw self::flickOnly(__FUNCTION__);
    }

    public function warningMessage(string $message, string $heading = ''): string
    {
        throw self::flickOnly(__FUNCTION__);
    }

    private static function flickOnly(string $method): \LogicException
    {
        return new \LogicException(
            "{$method}() needs a full Flick instance — call it as \$form->{$method}(), not on the Support object handed to a service."
        );
    }
}
