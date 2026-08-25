<?php

declare(strict_types=1);

namespace Flick\Http;

use Closure;

/**
 * Configurable response handlers for HTTP responses and security events.
 *
 * By default, all handlers use standalone behavior (send response and exit).
 * Framework integrations can override handlers to intercept responses and
 * convert them to framework-specific types.
 *
 * @example Standalone usage (default)
 * ```php
 * $form = new Flick('bootstrap');
 * $form->redirect('/thank-you'); // Exits automatically
 * ```
 * @example Framework integration
 * ```php
 * $form = new Flick([
 *     'onRedirect' => fn($response) => redirect($response->getUrl()),
 *     'onHoneypot' => fn() => abort(403),
 * ]);
 * return $form->redirect('/thank-you'); // Returns framework redirect
 * ```
 */
class ResponseHandlers
{
    private Closure $redirectHandler;

    private Closure $jsonHandler;

    private Closure $exceptionHandler;

    private Closure $honeypotHandler;

    private Closure $csrfExpiredHandler;

    public function __construct()
    {
        // Default handlers: send and exit (standalone behavior)
        $this->redirectHandler = fn (RedirectResponse $response) => $response->send();
        $this->jsonHandler = fn (JsonResponse $response) => $response->send();
        $this->exceptionHandler = fn (HtmlResponse $response) => $response->send();
        $this->honeypotHandler = fn () => exit;
        $this->csrfExpiredHandler = fn (string $message) => exit;
    }

    // FLUENT SETTERS ---------------------------------------------------------

    /**
     * Set the redirect response handler.
     *
     * @param  Closure(RedirectResponse): mixed  $handler
     */
    public function onRedirect(Closure $handler): self
    {
        $this->redirectHandler = $handler;

        return $this;
    }

    /**
     * Set the JSON response handler.
     *
     * @param  Closure(JsonResponse): mixed  $handler
     */
    public function onJson(Closure $handler): self
    {
        $this->jsonHandler = $handler;

        return $this;
    }

    /**
     * Set the exception/error page handler.
     *
     * @param  Closure(HtmlResponse): mixed  $handler
     */
    public function onException(Closure $handler): self
    {
        $this->exceptionHandler = $handler;

        return $this;
    }

    /**
     * Set the honeypot detection handler.
     *
     * Called when a honeypot field is filled (bot detection).
     *
     * @param  Closure(): mixed  $handler
     */
    public function onHoneypot(Closure $handler): self
    {
        $this->honeypotHandler = $handler;

        return $this;
    }

    /**
     * Set the CSRF token expiration handler.
     *
     * Called when the CSRF token has expired.
     *
     * @param  Closure(string): mixed  $handler  Receives the expiration message
     */
    public function onCsrfExpired(Closure $handler): self
    {
        $this->csrfExpiredHandler = $handler;

        return $this;
    }

    // INVOKERS ---------------------------------------------------------------

    /**
     * Handle a redirect response.
     *
     * @return mixed Returns the handler's result (for framework integration)
     */
    public function handleRedirect(RedirectResponse $response): mixed
    {
        return ($this->redirectHandler)($response);
    }

    /**
     * Handle a JSON response.
     *
     * @return mixed Returns the handler's result (for framework integration)
     */
    public function handleJson(JsonResponse $response): mixed
    {
        return ($this->jsonHandler)($response);
    }

    /**
     * Handle an exception/error page response.
     *
     * @return mixed Returns the handler's result (for framework integration)
     */
    public function handleException(HtmlResponse $response): mixed
    {
        return ($this->exceptionHandler)($response);
    }

    /**
     * Handle honeypot detection (bot caught).
     *
     * @return mixed Returns the handler's result (for framework integration)
     */
    public function handleHoneypot(): mixed
    {
        return ($this->honeypotHandler)();
    }

    /**
     * Handle CSRF token expiration.
     *
     * @return mixed Returns the handler's result (for framework integration)
     */
    public function handleCsrfExpired(string $message): mixed
    {
        return ($this->csrfExpiredHandler)($message);
    }
}
