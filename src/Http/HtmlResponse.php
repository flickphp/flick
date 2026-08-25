<?php

declare(strict_types=1);

namespace Flick\Http;

/**
 * Represents an HTML HTTP response.
 *
 * Used primarily for exception/error pages. Framework integrations
 * can use getContent() to render their own error views.
 */
class HtmlResponse extends Response
{
    public function __construct(
        private readonly string $content,
        private readonly int $statusCode = 200
    ) {}

    /**
     * Get the HTML content.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Get the HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Send the HTML response and terminate.
     */
    public function send(): never
    {
        http_response_code($this->statusCode);
        echo $this->content;
        exit;
    }

    /**
     * Convert to array representation.
     */
    public function toArray(): array
    {
        return [
            'type' => 'html',
            'content' => $this->content,
            'statusCode' => $this->statusCode,
        ];
    }
}
