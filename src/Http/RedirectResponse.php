<?php

declare(strict_types=1);

namespace Flick\Http;

/**
 * Represents an HTTP redirect response.
 *
 * Framework integrations can use getUrl() and getStatusCode() to create
 * their own redirect responses instead of calling send().
 */
class RedirectResponse extends Response
{
    public function __construct(
        private readonly string $url,
        private readonly int $statusCode = 302
    ) {}

    /**
     * Get the redirect URL.
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Get the HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Send the redirect and terminate.
     */
    public function send(): never
    {
        header('Location: '.$this->url, true, $this->statusCode);
        exit;
    }

    /**
     * Convert to array representation.
     */
    public function toArray(): array
    {
        return [
            'type' => 'redirect',
            'url' => $this->url,
            'statusCode' => $this->statusCode,
        ];
    }
}
