<?php

declare(strict_types=1);

namespace Flick\Mailer\Message;

/**
 * Formats form submission data as readable email content.
 */
class FormDataFormatter
{
    /**
     * Format form data as plain text.
     *
     * @param  array  $formData  Key-value pairs
     * @param  array  $exclude  Keys to exclude from output
     */
    public function toText(array $formData, array $exclude = []): string
    {
        $lines = [];

        foreach ($formData as $key => $value) {
            if (in_array($key, $exclude, true) || str_starts_with($key, '_')) {
                continue;
            }

            $label = $this->formatLabel($key);
            $formatted = $this->formatValue($value);
            $lines[] = "{$label}: {$formatted}";
        }

        return implode("\n", $lines);
    }

    /**
     * Format form data as HTML table.
     *
     * @param  array  $formData  Key-value pairs
     * @param  array  $exclude  Keys to exclude from output
     */
    public function toHtml(array $formData, array $exclude = []): string
    {
        $rows = [];

        foreach ($formData as $key => $value) {
            if (in_array($key, $exclude, true) || str_starts_with($key, '_')) {
                continue;
            }

            $label = htmlspecialchars($this->formatLabel($key), ENT_QUOTES, 'UTF-8');
            $formatted = htmlspecialchars($this->formatValue($value), ENT_QUOTES, 'UTF-8');
            $formatted = nl2br($formatted);

            $rows[] = "<tr><td style=\"padding: 8px; border: 1px solid #ddd; font-weight: bold; vertical-align: top;\">{$label}</td><td style=\"padding: 8px; border: 1px solid #ddd;\">{$formatted}</td></tr>";
        }

        return '<table style="border-collapse: collapse; width: 100%; max-width: 600px;">'
            .implode('', $rows)
            .'</table>';
    }

    protected function formatLabel(string $key): string
    {
        // Convert snake_case or camelCase to Title Case
        $label = preg_replace('/[_-]/', ' ', $key);
        $label = preg_replace('/([a-z])([A-Z])/', '$1 $2', $label);

        return ucwords(strtolower($label));
    }

    protected function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map(fn ($v) => $this->formatValue($v), $value));
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return (string) $value;
    }
}
