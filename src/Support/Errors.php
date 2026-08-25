<?php

declare(strict_types=1);

namespace Flick\Support;

class Errors
{
    protected array $errors = [];

    public function __construct(protected array $validationMessages = []) {}

    public function add(string $key, string|array $message, string $rule = '', string|array $matches = ''): void
    {
        if (empty($rule)) {
            // No rule: this is a custom message. Coerce arrays to a string so the
            // string return contract of get()/getErrors() always holds.
            $this->errors[$key] = is_array($message) ? implode(' ', $message) : $message;

            return;
        }

        // A plain, non-empty string message is an explicit override and wins over
        // the canned rule text; only treat $message as a per-rule map when it's an array.
        if (is_string($message) && $message !== '') {
            $messageString = $message;
        } else {
            // Rule methods may pass the spec through as typed ('r', 'integer:5'),
            // so try the exact string first and then the bare rule name. There is
            // deliberately no '' fallback after that: a name with no message
            // anywhere is a typo and must fail loudly, not blank the error.
            $name = explode(':', $rule, 2)[0];
            $messageString = $message[$rule]
                ?? $message[$name]
                ?? $this->validationMessages[$rule]
                ?? $this->validationMessages[$name];
        }

        if (is_array($matches)) {
            $placeholders = [':key'];
            foreach ($matches as $index => $match) {
                $placeholders[] = ':match'.($index + 1);
            }

            $replacements = [$key];
            foreach ($matches as $match) {
                $replacements[] = $match;
            }

            $this->errors[$key] = str_replace($placeholders, $replacements, $messageString);
        } elseif (is_string($matches)) {
            $this->errors[$key] = str_replace([':key', ':match'], [$key, $matches], $messageString);
        } else {
            $this->errors[$key] = str_replace(':key', $key, $messageString);
        }
    }

    public function get($key): string
    {
        $key = trim($key, '[]');

        if (array_key_exists($key, $this->errors)) {
            return $this->errors[$key];
        }

        return '';
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function has($key): bool
    {
        if (array_key_exists($key, $this->errors)) {
            return true;
        }

        return false;
    }

    public function isEmpty(): bool
    {
        return empty($this->getErrors());
    }

    public function isNotEmpty(): bool
    {
        return ! empty($this->getErrors());
    }

    public function remove(string $key): bool
    {
        if ($this->has($key)) {
            unset($this->errors[$key]);

            return true;
        }

        return false;
    }
}
