<?php

declare(strict_types=1);

namespace Flick\Support;

use Flick\Http\RedirectResponse;
use JetBrains\PhpStorm\NoReturn;

trait Helpers
{
    /**
     * Clear request data after the form is submitted.
     */
    public function clear(): void
    {
        if ($this->submitted() && $this->ok()) {
            $this->request->clear();
        }
    }

    /**
     * Retrieve Flick's config values using a dot-notated string.
     *
     * @return array|mixed|null
     */
    public function config(string $value): mixed
    {
        $keys = explode('.', $value);
        $configValue = $this->config;

        foreach ($keys as $key) {
            // Require an array before indexing: isset($string[$key]) is true for
            // numeric string offsets, so without this config('id.0') would return
            // the first character of a string value instead of null.
            if (is_array($configValue) && isset($configValue[$key])) {
                $configValue = $configValue[$key];
            } else {
                return null;
            }
        }

        return $configValue;
    }

    /**
     * The url a multistep breadcrumb or submit link points at: the form's
     * action with ?step= set to the given target.
     *
     * Called from the breadcrumbs and multistep-submit views, which run with
     * $this bound to the form instance. See Support::stepUrl() for why the
     * composition can't be a plain string concatenation.
     */
    public function stepUrl(string $step): string
    {
        return Support::stepUrl((string) $this->config('action'), $step);
    }

    /**
     * Dump and die the $value.
     */
    #[NoReturn]
    public function dd($value): void
    {
        if (! empty($value)) {
            echo '<pre>';
            print_r($value);
            echo '</pre>';
        }

        exit;
    }

    /**
     * Dump $value to the screen.
     */
    public function dump($value): void
    {
        if (! empty($value)) {
            echo '<pre>';
            print_r($value);
            echo '</pre>';
        }
    }

    /**
     * Returns the visitor's IP address.
     */
    public function getIp(): string
    {
        return $this->request->ip();
    }

    /**
     * Checks if the input is empty.
     */
    public function inputIsEmpty($value): bool
    {
        if ($value === '0' || $value === 0) {
            return false;
        }

        return empty($value);
    }

    /**
     * Checks if the input is NOT empty.
     */
    public function inputIsNotEmpty($value): bool
    {
        if (! empty($value) || (isset($value) && $value === '0') || (isset($value) && $value === 0)) {
            return true;
        }

        return false;
    }

    /**
     * Returns TRUE if the errors bag is empty.
     */
    public function ok(): bool
    {
        return empty($this->getErrors());
    }

    /**
     * Redirect to a given URL after the form has been submitted.
     *
     * In standalone mode, this exits immediately. With custom handlers,
     * returns the handler's result (e.g., a framework redirect response).
     *
     * @return mixed|null Returns handler result for framework integration, null if not submitted/ok
     */
    public function redirect(string $url = ''): mixed
    {
        if ($this->submitted() && $this->ok()) {
            if (empty($url) || $url == 'self') {
                $url = Support::requestPath($this->request);
            }

            $response = new RedirectResponse($url);

            return $this->handlers->handleRedirect($response);
        }

        return null;
    }

    /**
     * Sanitizes HTTP request values and helps prevent XSS attacks.
     * Handles both scalar values and arrays recursively.
     *
     * @param  mixed  $value  The input value or array to sanitize
     * @return mixed Sanitized value or array
     */
    public function sanitizeRequest(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map([$this, 'sanitizeRequest'], $value);
        }

        if (is_scalar($value) && ! is_bool($value)) {
            return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        }

        return $value;
    }

    /**
     * Slug a string to letters, numbers, and underscores.
     */
    public function slug($string): string
    {
        $original = (string) $string;

        // transliterate accented/non-ASCII letters to their closest ASCII form
        // (e.g. é -> e) so they survive slugging instead of being stripped away
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $original);
        if ($transliterated !== false) {
            $string = $transliterated;
        }

        $return = str_replace(['-', ' '], ['_', '_'], (string) $string);
        $ascii = preg_replace('/[^A-Za-z0-9_]/', '', $return);

        // Non-Latin scripts (Cyrillic/CJK/Arabic) have no ASCII transliteration.
        // If transliteration dropped any letters that were in the original (i.e.
        // the ASCII result has fewer letters than the original had), fall back to
        // a Unicode-aware slug that preserves the native characters instead of
        // silently losing them — even when some ASCII (e.g. digits) survived.
        $originalLetters = preg_match_all('/\p{L}/u', $original);
        $asciiLetters = preg_match_all('/[A-Za-z]/', $ascii);
        if ($asciiLetters < $originalLetters) {
            $unicode = str_replace(['-', ' '], ['_', '_'], $original);

            return preg_replace('/[^\p{L}\p{N}_]/u', '', $unicode);
        }

        return $ascii;
    }

    /**
     * Returns TRUE if the form has been submitted.
     */
    public function submitted(): bool
    {
        if ($this->request->isMethod('POST')) {
            // make sure CSRF validation passes or fail
            if (! $this->checkForAndValidateCsrfToken()) {
                return false;
            }

            // Check for a form id to see which form was submitted. A keyed read
            // with ===, matching the GET branch below: the old scan compared
            // with ==, so PHP's numeric-string juggling let a form with id
            // '100' accept a posted _id of '1e2'. Same question, same answer,
            // whichever method the form used.
            if (isset($this->id)) {
                return $this->request->post('_id') === $this->id;
            }

            // No id at all: a POST can only be this form's submission.
            return true;
        }

        // A GET submission is identified the same way a POST one is: by the
        // hidden _id every Flick form renders. Treating any query string as a
        // submission would make a visitor arriving on '?utm_source=...' see a
        // fully-errored form before typing anything.
        if ($this->request->isMethod('GET')) {
            if (! isset($this->id)) {
                return ! empty($this->request->queryAll());
            }

            return $this->request->query('_id') === $this->id;
        }

        return false;
    }

    /**
     * Verifies a hashed (bcrypt) password.
     */
    public function verifyPassword(string $userSuppliedPassword, string $existingHashedPassword): bool
    {
        return password_verify($userSuppliedPassword, $existingHashedPassword);
    }

    // SESSION ----------------------------------------------------------------

    /**
     * Set a value in the session.
     */
    public function addSessionValue(string $key, mixed $value): void
    {
        $this->session->setValue($key, $value);
    }

    /**
     * Delete a value from the session.
     */
    public function deleteSessionValue(string $key): void
    {
        $this->session->deleteValue($key);
    }

    /**
     * Deletes the Flick session.
     */
    public function destroySession(bool $message = true): void
    {
        $this->session->destroy();

        if ($message) {
            $this->successMessage($this->config['applicationMessages']['SessionWasDestroyed']);
        }
    }

    /**
     * Deletes the compiled views under <assets>/cache/views.
     */
    public function flushCache(): void
    {
        $error = false;

        // temporarily disable the cache while the cache files are removed, then
        // restore the caller's setting: flushing the cache must not silently
        // leave caching off for the rest of the request.
        $originalCache = $this->config['cache'] ?? null;
        $this->config['cache'] = false;

        if ($this->config('assets')) {
            // glob() returns false on error; never let a later success clear an earlier failure
            $files = glob($this->config('assets').'/cache/views/*') ?: [];
            foreach ($files as $file) {
                if (is_file($file) && ! unlink($file)) {
                    $error = true;
                }
            }
        }

        // restore the caller's cache setting now that the flush is done
        if ($originalCache === null) {
            unset($this->config['cache']);
        } else {
            $this->config['cache'] = $originalCache;
        }

        if ($error) {
            $this->warningMessage($this->config['applicationMessages']['SomeFilesCouldNotBeDeleted']);
        } else {
            $this->successMessage($this->config['applicationMessages']['AllCachedViewFilesWereDeleted']);
        }
    }

    /**
     * Get a value from the session.
     */
    public function getSessionValue(string $key): mixed
    {
        return $this->session->getValue($key);
    }

    /**
     * Check if a value exists in the session.
     */
    public function hasSessionValue(string $key): bool
    {
        return $this->session->hasValue($key);
    }

    /**
     * Should validated field values be persisted to (and repopulated from)
     * the session? Enabled by the persistToSession config key, or by the
     * per-form flag a multistep flow sets for its own form id. The session
     * adapter itself (the 'session' config key) never switches this on.
     */
    public function persistingToSession(): bool
    {
        return (bool) $this->config('persistToSession')
            || $this->session->hasValue('_persist_'.$this->id);
    }

    /**
     * Check if the session is active.
     */
    public function sessionIsActive(): bool
    {
        return $this->session->isActive();
    }

    // ERROR BAG --------------------------------------------------------------

    /**
     * Adds a key to the error bag.
     */
    public function addError(string $key, string|array $message, string $rule = '', array|string $matches = ''): void
    {
        $this->errors->add($key, $message, $rule, $matches);
    }

    /**
     * Checks if the error bag is empty.
     */
    public function errorsIsEmpty(): bool
    {
        return $this->errors->isEmpty();
    }

    /**
     * Checks if the error bag is NOT empty.
     */
    public function errorsIsNotEmpty(): bool
    {
        return $this->errors->isNotEmpty();
    }

    /**
     * Returns the error message from the error bag.
     */
    public function getError(string $key): string
    {
        return $this->errors->get($key);
    }

    /**
     * Returns the contents of the error bag.
     */
    public function getErrors(): array
    {
        return $this->errors->getErrors();
    }

    /**
     * Checks the error bag for the supplied key.
     */
    public function hasError(string $key): bool
    {
        return $this->errors->has($key);
    }

    /**
     * Removes a key from the error bag.
     */
    public function deleteError(string $key): bool
    {
        return $this->errors->remove($key);
    }

    // MESSAGING --------------------------------------------------------------

    /**
     * Returns an unordered list of all errors bag messages.
     *
     * In echo mode the list is printed and '' is returned; with 'echo' => false
     * (the Blade/Laravel mode) the markup is returned instead, the same way the
     * sibling *Message() helpers behave.
     */
    public function errors(string $heading = ''): string
    {
        if (! empty($this->getErrors()) && $this->config('showErrorsAlert')) {
            if (! $heading) {
                $heading = $this->config['applicationMessages']['MessagesHeader'];
            }

            // Escape each message individually, then wrap in list markup. Escaping the
            // assembled markup (as message() does by default) would render the tags literally.
            $items = array_map(
                static fn ($error): string => htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'),
                $this->getErrors()
            );
            $message = '<ul><li>'.implode('</li><li>', $items).'</li></ul>';

            return $this->build->message('error', $message, $heading, false);
        }

        return '';
    }

    /**
     * Returns a formatted error message based upon the supplied views.
     */
    public function errorMessage(string $message, string $heading = ''): string
    {
        return $this->build->message('error', $message, $heading);
    }

    /**
     * Returns a formatted info message based upon the supplied views.
     */
    public function infoMessage(string $message, string $heading = ''): string
    {
        return $this->build->message('info', $message, $heading);
    }

    /**
     * Returns a formatted success message based upon the supplied views.
     */
    public function successMessage(string $message, string $heading = ''): string
    {
        return $this->build->message('success', $message, $heading);
    }

    /**
     * Returns a formatted warning message based upon the supplied views.
     */
    public function warningMessage(string $message, string $heading = ''): string
    {
        return $this->build->message('warning', $message, $heading);
    }
}
