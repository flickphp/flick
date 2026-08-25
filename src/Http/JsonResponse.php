<?php

declare(strict_types=1);

namespace Flick\Http;

/**
 * Represents a JSON HTTP response.
 *
 * Framework integrations can use getData(), getBody() and getStatusCode() to
 * create their own JSON responses instead of calling send().
 */
class JsonResponse extends Response
{
    private readonly int $statusCode;

    private readonly string $body;

    public function __construct(
        private readonly array $data,
        int $statusCode = 200
    ) {
        // Encoded once, here, so every reader sees the same answer. send() used
        // to encode on its own and could emit a 500 while getStatusCode() still
        // reported the code the caller asked for.
        [$this->statusCode, $this->body] = $this->encodeBody($statusCode);
    }

    /**
     * Get the response data.
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get the HTTP status code. This is the code that will actually be sent,
     * which is 500 when the data could not be encoded.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get the encoded JSON body.
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Send the JSON response and terminate.
     */
    public function send(): never
    {
        header('Content-Type: application/json', true, $this->statusCode);
        echo $this->body;
        exit;
    }

    /**
     * Encode the response data, substituting invalid UTF-8 so a bad byte can't
     * produce an empty 200 body. If encoding still fails, return a 500 error body
     * instead of an unparseable empty response.
     *
     * @return array{0: int, 1: string} The status code and JSON body
     */
    private function encodeBody(int $requestedStatusCode): array
    {
        $json = json_encode($this->data, JSON_INVALID_UTF8_SUBSTITUTE);

        if ($json === false) {
            // written out rather than encoded: this is the path where encoding
            // has already failed once
            return [500, '{"error":true,"message":"Failed to encode response"}'];
        }

        return [$requestedStatusCode, $json];
    }

    /**
     * Convert to array representation.
     */
    public function toArray(): array
    {
        return [
            'type' => 'json',
            'data' => $this->data,
            'statusCode' => $this->statusCode,
        ];
    }
}
