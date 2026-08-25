<?php

declare(strict_types=1);

namespace Flick\Support;

use RuntimeException;

/**
 * The exception Flick core throws for unrecoverable errors.
 *
 * The message is short plain text — inline backticks mark code-ish spans and
 * substituted values stay raw, so logs and catch blocks read cleanly. The
 * optional fields carry the presentation extras; ExceptionRenderer escapes
 * everything when the standalone error page is built.
 */
class FlickException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $heading = 'Whoops',
        public readonly string $help = '',
        public readonly string $codeSample = '',
        public readonly string $docsUrl = '',
    ) {
        parent::__construct($message);

        // a factory-built exception must report its caller as the origin —
        // frameworks read getFile()/getLine() directly, so fixing this only
        // at render time would not be enough
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5) as $frame) {
            if (isset($frame['file'], $frame['line']) && $frame['file'] !== __FILE__) {
                $this->file = $frame['file'];
                $this->line = $frame['line'];
                break;
            }
        }
    }

    /**
     * Collapse `.` and `..` segments without touching the filesystem — the
     * path being reported usually does not exist, so realpath() cannot help.
     */
    private static function normalizePath(string $path): string
    {
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '..' && $parts !== [] && end($parts) !== '..') {
                array_pop($parts);
            } elseif ($part !== '.' && $part !== '') {
                $parts[] = $part;
            }
        }

        return (str_starts_with($path, '/') ? '/' : '').implode('/', $parts);
    }

    // FACTORIES (alphabetical) -----------------------------------------------

    public static function addServiceError(string $message): self
    {
        return new self(
            "An error occurred while adding a service: {$message}",
            heading: 'Service error',
        );
    }

    public static function alertTypeIsNotAvailable(string $key): self
    {
        return new self(
            "The alert type `{$key}` is not available.",
            heading: 'Unknown alert type',
            help: 'Flick ships with these alert types: error, info, success, warning.',
            docsUrl: 'https://flickphp.com/guide/views',
        );
    }

    public static function assetsDirectoryNotFound(string $path): self
    {
        $path = static::normalizePath($path);

        return new self(
            "Your `assets` directory was not found: `{$path}`",
            heading: 'Path error',
        );
    }

    public static function assetsDirectoryNotWritable(string $path): self
    {
        $path = static::normalizePath($path);

        return new self(
            "Your `assets` directory is not writable: `{$path}`",
            heading: 'Permissions error',
        );
    }

    public static function cacheDirectoryCannotBeCreated(string $path): self
    {
        $path = static::normalizePath($path);

        return new self(
            "Your `cache` directory could not be created: `{$path}`",
            heading: 'Permissions error',
        );
    }

    public static function cachingIsDisabled(): self
    {
        return new self(
            'Add an `assets` directory to your configuration array to enable caching.',
            heading: 'Caching is disabled',
            codeSample: <<<'CODE'
            $config = [
                'assets' => __DIR__.'/myAssets',
                'cache' => true,
            ];

            $form = new Flick($config);
            CODE,
        );
    }

    public static function dropdownNotFound(string $path): self
    {
        $path = static::normalizePath($path);

        return new self(
            "The dropdown file `{$path}` was not found.",
            heading: 'File not found',
            docsUrl: 'https://flickphp.com/guide/prebuilt-dropdowns',
        );
    }

    public static function formNotFound(string $path): self
    {
        $path = static::normalizePath($path);

        return new self(
            "The form file `{$path}` was not found.",
            heading: 'File not found',
            docsUrl: 'https://flickphp.com/guide/prebuilt-forms',
        );
    }

    public static function invalidServiceProvider(string $name, string $class): self
    {
        return new self(
            "The `{$name}` service was registered with `{$class}`, which is not a service provider.",
            heading: 'Invalid service provider',
            help: 'The class must exist and implement Flick\Service\ServiceProvider. Check the '
                .'Registry::add() call in the package\'s autoload file, or in your own code.',
            docsUrl: 'https://flickphp.com/services',
        );
    }

    public static function languageFileNotFound(string $path): self
    {
        $path = static::normalizePath($path);

        return new self(
            "Flick tried to load a language file that does not exist: `{$path}`",
            heading: 'Language file not found',
            help: 'If you configured `assets` together with `lang`, copy the shipped '
                .'translations from `vendor/flickphp/flick/lang/` into a `lang/` folder '
                .'inside your assets directory, or remove the `lang` key to use the '
                .'shipped English defaults. Without `assets`, this usually means the '
                .'language code is not among the shipped translations.',
        );
    }

    public static function languageFileInvalid(string $path): self
    {
        $path = static::normalizePath($path);

        return new self(
            "Flick loaded a language file that does not return an array: `{$path}`",
            heading: 'Language file invalid',
            help: 'Language files must return an array of messages, e.g. '
                ."`return ['required' => 'The :key field is required'];`. "
                .'A file that defines a `const` instead must be updated to return the array.',
        );
    }

    public static function missingCsrfSessionStart(): self
    {
        return new self(
            'Add `session_start()` at the top of your PHP file to enable CSRF protection.',
            heading: 'Session required',
        );
    }

    public static function missingDropdownOptions(): self
    {
        return new self(
            'Add an `options` array to your dropdown menu.',
            heading: 'Missing dropdown options',
            docsUrl: 'https://flickphp.com/guide/customize#dropdowns',
        );
    }

    public static function missingDropdownOptionsInsideParentheses(): self
    {
        return new self(
            'Add an options array to your dropdown menu.',
            heading: 'Missing dropdown options',
            codeSample: <<<'CODE'
            $form->create('Foo|select([
                one:Option One,
                two:Option Two
            ])');
            CODE,
            docsUrl: 'https://flickphp.com/guide/creating-forms#create-your-form-with-a-string',
        );
    }

    public static function missingFieldKey(string $key): self
    {
        $code = str_replace(':key', $key, <<<'CODE'
        'fields' => [
            '' => [ // <-- key is missing
                'label' => ':key'
            ]
        ]
        CODE);

        return new self(
            "Your form is missing an array key for the field labeled `{$key}`.",
            heading: 'Missing array key',
            codeSample: $code,
            docsUrl: 'https://flickphp.com/guide/prebuilt-forms',
        );
    }

    public static function missingOptions(string $key): self
    {
        return new self(
            "The `{$key}` dropdown is missing its `options` array.",
            heading: 'Missing dropdown options',
            docsUrl: 'https://flickphp.com/guide/prebuilt-dropdowns',
        );
    }

    public static function serviceIsNotAvailable(string $name): self
    {
        return new self(
            "The `{$name}` service is not available.",
            heading: 'Service not available',
            help: 'Is the package that provides it installed, and is the name spelled correctly? '
                .'Flick Pro services need `composer require flickphp/pro`. Guard a service that '
                .'may not be installed with hasService().',
            codeSample: <<<CODE
            \$form = new Flick(\$config);

            if (\$form->hasService('{$name}')) {
                // ...
            }
            CODE,
            docsUrl: 'https://flickphp.com/services',
        );
    }

    public static function sessionIsRequired(): self
    {
        return new self(
            'Add `session_start()` at the top of your PHP file and refresh the form.',
            heading: 'Session required',
        );
    }

    public static function viewCacheDirectoryNotWritable(string $path): self
    {
        $path = static::normalizePath($path);

        return new self(
            "Flick could not create the views cache directory: `{$path}`",
            heading: 'Permissions error',
        );
    }

    public static function viewCacheFileNotWritable(string $path, string $view): self
    {
        $path = static::normalizePath($path);

        return new self(
            "The view file could not be cached: `{$path}/{$view}`",
            heading: 'Caching error',
        );
    }

    public static function viewFileNotFound(string $path): self
    {
        $path = static::normalizePath($path);

        return new self(
            "The view file was not found: `{$path}`",
            heading: 'View file not found',
        );
    }

    public static function viewPathIsNotDefined(string $path): self
    {
        $path = static::normalizePath($path);

        return new self(
            "The `views` directory was not found: `{$path}`",
            heading: 'Path error',
        );
    }
}
