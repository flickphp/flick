<?php

declare(strict_types=1);

namespace Flick\Validation;

/**
 * Interface for delegating validation to external validators.
 *
 * Allows Flick to use validation rules from other systems (e.g., Laravel)
 * without tight coupling. When Flick encounters an unrecognized rule,
 * it can delegate to a validation delegate that implements this interface.
 */
interface ValidationDelegateInterface
{
    /**
     * Determine if this delegate can handle the given rule.
     *
     * Only claim rules your validator actually knows. Flick's rule-string
     * parser also calls this with the bare comma fragments of a rule - the
     * "65" in between:18,65, the "id" in exists:users,id - to tell a rule
     * token from an argument, so a delegate that returns true for everything
     * makes each argument look like a separate rule, and comma-argument rules
     * stop working entirely.
     *
     * @param  string  $rule  A validation rule (e.g., 'exists:users,id', 'unique:posts,email'), or a bare argument fragment of one
     * @return bool True if this delegate can validate this rule
     */
    public function canHandle(string $rule): bool;

    /**
     * Validate a field value against a rule.
     *
     * @param  string  $field  The field name being validated
     * @param  mixed  $value  The field value to validate
     * @param  string  $rule  The validation rule to apply
     * @param  array  $allData  All form data (for cross-field validation rules like 'same:')
     * @return array Positional list of error messages (empty array = validation
     *               passed). Flick reports only the first message; any others
     *               are ignored.
     */
    public function validate(string $field, mixed $value, string $rule, array $allData = []): array;
}
