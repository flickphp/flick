<?php

declare(strict_types=1);

namespace Flick\Http;

/**
 * Abstract base class for HTTP responses.
 *
 * Response objects encapsulate HTTP response data without immediately
 * sending it. This allows frameworks to intercept and convert responses
 * to their own types, while standalone usage can call send() directly.
 */
abstract class Response
{
    /**
     * Send the response and terminate execution.
     *
     * In standalone mode, this outputs the response and exits.
     * Framework integrations should intercept before this is called.
     */
    abstract public function send(): never;

    /**
     * Convert the response to an array representation.
     *
     * Useful for debugging, logging, or serialization.
     */
    abstract public function toArray(): array;
}
