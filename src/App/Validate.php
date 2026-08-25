<?php

declare(strict_types=1);

namespace Flick\App;

use DateTime;
use Flick\Flick;
use Flick\Http\NativeRequest;
use Flick\Http\RequestInterface;
use Flick\Support\FlickException;
use Flick\Validation\ValidationDelegateInterface;

class Validate
{
    protected RequestInterface $request;

    protected ?ValidationDelegateInterface $validationDelegate;

    public function __construct(
        public Flick $flick,
        ?RequestInterface $request = null,
        ?ValidationDelegateInterface $validationDelegate = null
    ) {
        $this->request = $request ?? new NativeRequest;
        $this->validationDelegate = $validationDelegate;
    }

    // process and validate an individual form field after submission
    public function input(string $key, string|array $rules, array $messages): string|array
    {
        // a trailing [] mirrors the rendered field name (checkbox groups,
        // selectMultiple) and marks the field as genuinely multi-value; the
        // request itself stores the values under the bare name
        $expectsArray = str_ends_with($key, '[]');

        if ($expectsArray) {
            $key = substr($key, 0, -2);
        }

        // use global validation rules if none were provided inline (a multi-value
        // field's rules may be registered under either spelling of its name)
        if (empty($rules) && isset($this->flick->config['rules'][$key])) {
            $rules = $this->flick->config['rules'][$key];
        } elseif (empty($rules) && $expectsArray && isset($this->flick->config['rules'][$key.'[]'])) {
            $rules = $this->flick->config['rules'][$key.'[]'];
        }

        // use global validation messages if none were provided inline
        if (empty($messages) && isset($this->flick->config['messages'][$key])) {
            $messages = $this->flick->config['messages'][$key];
        } elseif (empty($messages) && $expectsArray && isset($this->flick->config['messages'][$key.'[]'])) {
            $messages = $this->flick->config['messages'][$key.'[]'];
        }

        $rules = $this->convertRulesToArray($rules);
        $input = $this->applyTrimPolicy($key, $this->getHttpRequestValue($key));

        // Clear any prior error for this field so re-validating the same key on
        // one instance reflects the latest result instead of a stale failure.
        $this->flick->deleteError($key);

        if ($this->fieldIsRequiredAndElementIsUnchecked($key, $rules)) {
            $this->required('', 'required', $key, $messages);

            return '';
        }

        [$validationRules, $stringModifications] = $this->separateRulesAndModifications($rules);

        if (is_array($input)) {
            // a scalar field can never legitimately receive an array: rewriting
            // email= to email[]= must fail validation, not bypass it
            if (! $expectsArray && $validationRules !== []) {
                $this->flick->addError($key, $messages, 'single', $key);

                return '';
            }

            if ($expectsArray) {
                $this->applyValidationRulesToArray($validationRules, $input, $key, $messages);
            }
        } else {
            foreach ($validationRules as $rule) {
                $this->applyValidationRule($rule, $input ?? '', $key, $messages);
            }
        }

        $processedInput = $this->applyStringModifications($input ?? '', $stringModifications);

        // persist the value to the session if required
        if ($this->flick->persistingToSession()) {
            $this->flick->session->setValue($key, $processedInput);
        }

        return $processedInput;
    }

    // process and validate a full form after submission
    public function formInput(array|string $form, array|string $rules = [], array $messages = []): array|string|null
    {
        if (! $this->flick->submitted()) {
            return null;
        } elseif (is_array($form)) {
            return $this->flick->build->fastPost($form);
        } elseif (str_starts_with($form, '/')) {
            return $this->flick->build->fastPost($form);
        }

        // Classify by the actual field boundaries, not by str_contains(','):
        // a comma inside a rules block ('Age[between:18,65]') or an options
        // list is not a field separator, and treating it as one returned a
        // keyed array where the same call with 'Email[email]' returned a
        // scalar.
        if (count($this->prepareAFormStringForValidation($form)) > 1) {
            return $this->form($form);
        } elseif ($this->stringIsAMultiValueFieldName($form)) {
            // 'colors[]' is a plain field name whose [] marks it multi-value,
            // not definition syntax; the rules arrive through the arguments
            return $this->input($form, $rules, $messages);
        } elseif ($this->stringCarriesFieldDefinition($form)) {
            return $this->singleFieldInput($form);
        }

        return $this->input($form, $rules, $messages);
    }

    // a bare field name written with the [] suffix it renders with
    private function stringIsAMultiValueFieldName(string $form): bool
    {
        return preg_match('/^[^\[\]{|]+\[\]$/', $form) === 1;
    }

    /**
     * Does this string describe a field rather than just name one?
     *
     * Inline rules, an element type, or a default value all mean the string has
     * to go through the definition parser. A bare field name has none of them.
     */
    private function stringCarriesFieldDefinition(string $form): bool
    {
        return preg_match('/[\[|{]/', $form) === 1;
    }

    /**
     * Validate a lone field written in definition syntax, e.g. 'Email[email]'.
     *
     * Runs it through the same parser the comma-separated form uses, so the
     * rules apply and the name resolves the way create() named it, then unwraps
     * the single result so the caller still gets a plain value back.
     */
    private function singleFieldInput(string $form): array|string|null
    {
        $parsed = $this->form($form);

        if ($parsed === []) {
            return null;
        }

        $value = reset($parsed);

        // an upload returns a FileInfo/FileCollection, which has no place in this
        // method's string return; hand those back inside the array, keyed by name
        return is_string($value) || is_array($value) ? $value : $parsed;
    }

    // INPUT  -----------------------------------------------------------------

    // Never trimmed: whitespace can be a deliberate part of a password, and
    // Laravel's TrimStrings middleware excepts the same two fields.
    private const TRIM_EXEMPT_FIELDS = ['password', 'password_confirmation'];

    /**
     * Trim surrounding whitespace from string input before validation.
     *
     * On by default; `'trim' => false` in the config disables it. Applied to
     * every value read for validation — including the counterpart fields the
     * confirmed/matches/requiredWith rules compare against, so both sides of a
     * comparison see the same policy.
     */
    private function applyTrimPolicy(string $key, mixed $value): mixed
    {
        if ($this->flick->config('trim') === false || in_array($key, self::TRIM_EXEMPT_FIELDS, true)) {
            return $value;
        }

        if (is_string($value)) {
            return trim($value);
        }

        if (is_array($value)) {
            return array_map(static fn ($item) => is_string($item) ? trim($item) : $item, $value);
        }

        return $value;
    }

    private function applyStringModifications(string|array $input, array $modifications): string|array
    {
        // Arrays (e.g., from multiple select) are returned as-is
        if (is_array($input)) {
            return $input;
        }

        $modificationMap = $this->getStringModifiers();

        foreach ($modifications as $mod) {
            $modName = explode(':', $mod)[0];
            if (isset($modificationMap[$modName])) {
                $input = $modificationMap[$modName]($input);
            }
        }

        return $input;
    }

    /**
     * The canonical validation rule names (the distinct handler names, aliases
     * collapsed). Exposed so the client-side parity test can be driven from the
     * server rules rather than a hand-maintained list — a new PHP rule then
     * automatically requires a matching JS rule.
     *
     * @return list<string>
     */
    public static function getRuleNames(): array
    {
        return array_values(array_unique(array_values(self::getRuleMap())));
    }

    /**
     * Resolve one rule NAME to the handler it dispatches to, so an alias and the
     * rule it aliases are the same identity. Unknown names (delegated and custom
     * rules) come back untouched.
     *
     * Exposed for the same reason as getRuleNames(): the client side must not
     * keep a second copy of the alias map. It kept none at all, which is worse -
     * `int` and `r` reached the browser as literal rule names, no JS validator
     * answers to either, and every dispatcher treats an unknown rule as a pass.
     * The documented aliases therefore failed OPEN: valid in the browser,
     * rejected on submit.
     *
     * Matching is exact and case-sensitive, mirroring applyValidationRule().
     * Pass the name only - split any ':' parameters off first.
     */
    public static function canonicalRuleName(string $name): string
    {
        return self::getRuleMap()[$name] ?? $name;
    }

    /**
     * The rules that still run when the field is empty.
     *
     * Every other rule treats an empty value as "nothing to check" and leaves
     * it to `required`. These three are the exceptions: `required` and
     * `accepted` exist to reject an empty value, and `requiredWith` rejects one
     * when its counterpart is filled.
     *
     * Declared here, on the server, because the server is the source of truth
     * for validation behaviour - the client-side rules are generated from it.
     * The JS dispatchers skipped every rule on an empty value, so `accepted`
     * and `requiredWith` passed in the browser and failed on submit.
     *
     * The list is hand-written on purpose. Deriving it by asking "does this
     * rule error on an empty string?" would catch `requiredWith` for the wrong
     * reason: with no parameter it trips the missing-delimiter branch, which is
     * a malformed-rule error, not an empty-value one.
     *
     * @return list<string>
     */
    public static function getRulesRunOnEmptyInput(): array
    {
        return ['accepted', 'required', 'requiredWith'];
    }

    // maps rule NAMES (the part before any ':') to their validation methods.
    // Matching is exact on the name so a delegated/custom rule that merely shares a
    // prefix (e.g. Laravel's requiredIf, ipAddress, alphaNumeric) is not hijacked.
    private static function getRuleMap(): array
    {
        return [
            'afterOrEqual' => 'afterOrEqual',
            'after' => 'after',
            'beforeOrEqual' => 'beforeOrEqual',
            'before' => 'before',
            'between' => 'between',
            'confirmed' => 'confirmed',
            'greaterThanOrEqual' => 'greaterThanOrEqual',
            'greaterThan' => 'greaterThan',
            'lessThanOrEqual' => 'lessThanOrEqual',
            'lessThan' => 'lessThan',
            'notMatches' => 'notMatches',
            'matches' => 'matches',
            'notIn' => 'notIn',
            'notRegex' => 'notRegex',
            'regex' => 'regex',
            'requiredWith' => 'requiredWith',
            'strongPassword' => 'strongPassword',
            'accepted' => 'accepted',
            'boolean' => 'boolean',
            'creditCard' => 'creditCard',
            'date' => 'date',
            'digitsBetween' => 'digitsBetween',
            'digits' => 'digits',
            'endsWith' => 'endsWith',
            'startsWith' => 'startsWith',
            'phone' => 'phone',
            'r' => 'required',
            'required' => 'required',
            'equals' => 'equals',
            'exact' => 'exact',
            'in' => 'in',
            'max' => 'max',
            'min' => 'min',
            'alphaNumeric' => 'alphaNumeric',
            'alphaDash' => 'alphaDash',
            'alpha' => 'alpha',
            'email' => 'email',
            'int' => 'integer',
            'integer' => 'integer',
            'ipv4' => 'ipv4',
            'ipv6' => 'ipv6',
            'ip' => 'ip',
            'json' => 'json',
            'numeric' => 'numeric',
            'url' => 'url',
            'uuid' => 'uuid',
        ];
    }

    /**
     * Strip the leading "name:" prefix from a rule string to get its argument.
     *
     * str_replace() would remove EVERY occurrence of "name:", corrupting an
     * argument that legitimately contains it (e.g. in:domain:example, or a
     * regex pattern). A rule only reaches its method when it starts with the
     * prefix, so a single leading strip is both safe and correct.
     */
    private function ruleArgument(string $rule, string $name): string
    {
        $prefix = $name.':';

        return str_starts_with($rule, $prefix) ? substr($rule, strlen($prefix)) : '';
    }

    /**
     * Run each validation rule across every element of a multi-value
     * submission. An empty list runs the rules that are declared to run on
     * empty input - the per-element loop would never reach them - and an
     * element that is itself an array has no scalar representation, so it
     * fails like a scalar field receiving one.
     */
    private function applyValidationRulesToArray(array $rules, array $input, string $key, array $messages): void
    {
        if ($input === []) {
            // An absent key already runs every rule against '' (see input()).
            // Running only required here made 'accepted' and 'requiredWith'
            // pass on [] while failing when the key was missing entirely - and
            // the browser, which reads getRulesRunOnEmptyInput(), blocked both.
            // The rule string is passed through untouched so a custom message
            // keyed by the alias the developer typed ('r') still fires.
            $runOnEmpty = self::getRulesRunOnEmptyInput();

            foreach ($rules as $rule) {
                $name = self::canonicalRuleName(explode(':', $rule, 2)[0]);

                if (in_array($name, $runOnEmpty, true)) {
                    $this->applyValidationRule($rule, '', $key, $messages);
                }
            }

            return;
        }

        foreach ($input as $element) {
            if (! is_scalar($element)) {
                $this->flick->addError($key, $messages, 'single', $key);

                continue;
            }

            foreach ($rules as $rule) {
                $this->applyValidationRule($rule, (string) $element, $key, $messages);
            }
        }
    }

    private function rulesRequireAValue(array $rules): bool
    {
        return in_array('required', $rules, true) || in_array('r', $rules, true);
    }

    private function applyValidationRule(string $rule, string $input, string $key, array $messages): void
    {
        // The rule name is the token before the first ':' (params, incl. any ':' inside
        // a regex, follow it). Match the name exactly against the map.
        $name = explode(':', $rule, 2)[0];
        $map = self::getRuleMap();

        if (isset($map[$name])) {
            $method = $map[$name];
            $this->$method($input, $rule, $key, $messages);

            return;
        }

        // Try delegating to external validator (e.g., Laravel)
        if ($this->validationDelegate !== null && $this->validationDelegate->canHandle($rule)) {
            // POST takes precedence over GET on a key collision, matching
            // RequestInterface::all(); otherwise a ?key=... in the URL could
            // override the posted value a cross-field rule validates against.
            $allData = array_merge(
                $this->request->queryAll() ?? [],
                $this->request->postAll() ?? []
            );
            $errors = $this->validationDelegate->validate($key, $input, $rule, $allData);
            if (! empty($errors)) {
                // Take only the first error message
                $this->flick->addError($key, reset($errors));
            }

            return;
        }

        $this->flick->addError($key, '', 'invalidRule', $rule);
    }

    // string modifiers, which can be added when applying validation rules
    private function getStringModifiers(): array
    {
        return [
            'bcrypt' => fn ($val) => password_hash($val, PASSWORD_DEFAULT),
            'hash' => fn ($val) => password_hash($val, PASSWORD_DEFAULT),
            'sanitizeChars' => fn ($val) => filter_var($val, FILTER_SANITIZE_FULL_SPECIAL_CHARS),
            'sanitizeEmail' => fn ($val) => filter_var($val, FILTER_SANITIZE_EMAIL),
            'sanitizeInt' => fn ($val) => filter_var($val, FILTER_SANITIZE_NUMBER_INT),
            'sanitizeUrl' => fn ($val) => filter_var($val, FILTER_SANITIZE_URL),
            'slug' => fn ($val) => $this->flick->slug($val),
            'stripAlpha' => fn ($val) => preg_replace('/[^0-9]/', '', $val),
            'stripNumeric' => fn ($val) => preg_replace('/[^a-zA-Z]/', '', $val),
            'stripTags' => fn ($val) => strip_tags($val),
        ];
    }

    private function fieldIsRequiredAndElementIsUnchecked(string $key, array $rules): bool
    {
        return (! $this->request->hasPost($key) && ! $this->request->hasQuery($key)) && $this->rulesRequireAValue($rules);
    }

    /**
     * Split a rules string into a list of rule strings, keeping argument
     * commas ('between:18,65') attached to their rule.
     *
     * @internal public so Build's DSL parser shares this splitter instead of
     * keeping a third rules grammar; not part of the developer API
     */
    public function convertRulesToArray(string|array $rules): array
    {
        if (! is_string($rules)) {
            return $rules;
        }

        $result = [];

        foreach (array_map('trim', explode(',', $rules)) as $segment) {
            $lastIndex = count($result) - 1;

            // a comma inside a rule's argument list (e.g. 'between:18,65') is
            // part of the previous rule, not a new rule
            if ($lastIndex >= 0 && $this->segmentBelongsToPreviousRule($result[$lastIndex], $segment)) {
                $result[$lastIndex] .= ','.$segment;
            } else {
                $result[] = $segment;
            }
        }

        return $result;
    }

    // decide whether a comma-split segment is an argument of the previous rule
    private function segmentBelongsToPreviousRule(string $previousRule, string $segment): bool
    {
        // only these rules take argument lists that may contain commas; a rule
        // the delegate owns can take one too, e.g. Laravel's exists:table,column
        $takesArgumentList = preg_match(
            '/^(between|digitsBetween|in|notIn|regex|notRegex|startsWith|endsWith):/',
            $previousRule
        ) === 1;

        if (! $takesArgumentList && ! $this->segmentIsDelegatedRule($previousRule)) {
            return false;
        }

        // a regex may contain literally anything, including something that looks
        // like another rule, so it absorbs every following segment
        if (preg_match('/^(regex|notRegex):/', $previousRule)) {
            return true;
        }

        // anything the validator (or its delegate) recognises starts a new rule.
        // Everything else is a further argument - a url scheme in startsWith, a
        // value containing a colon in an in list, and so on. Matching on "looks
        // like name:value" would misread those as rules.
        return ! $this->segmentIsAKnownRuleToken($segment);
    }

    private function segmentIsAKnownRuleToken(string $segment): bool
    {
        $knownNames = array_merge(
            array_map(fn ($prefix) => rtrim($prefix, ':'), array_keys(self::getRuleMap())),
            array_keys($this->getStringModifiers())
        );

        $name = explode(':', $segment, 2)[0];

        if (in_array($name, $knownNames, true)) {
            return true;
        }

        return $this->segmentIsDelegatedRule($segment);
    }

    // is this token a rule the configured validation delegate handles itself?
    private function segmentIsDelegatedRule(string $segment): bool
    {
        return $this->validationDelegate !== null
            && $this->validationDelegate->canHandle($segment);
    }

    private function separateRulesAndModifications(array $rules): array
    {
        $stringModifiers = array_keys($this->getStringModifiers());
        $validationRules = [];
        $stringModifications = [];

        foreach ($rules as $rule) {
            $ruleName = explode(':', $rule)[0];
            if (in_array($ruleName, $stringModifiers)) {
                $stringModifications[] = $rule;
            } else {
                $validationRules[] = $rule;
            }
        }

        return [$validationRules, $stringModifications];
    }

    /**
     * Split a form-definition string into its field parts at the top-level
     * commas, ignoring commas inside rules/messages blocks and option parens:
     * 'zip[min:5, max:9][min:Zip must be at least 5 chars], email[email]'.
     *
     * @internal public so Build's DSL parser shares this splitter instead of
     * keeping a second field-boundary grammar; not part of the developer API.
     * Build used /,\s*(?![^(]*\)|[^[]*])/, whose lookahead could not see past
     * a comma inside a regex rule's character class - so
     * 'Code[regex:/^[A-Z],[0-9]$/]' rendered a phantom field named '$/' and
     * lost the rule, while this splitter read the same string correctly.
     *
     * @return list<string> trimmed parts; an empty segment (', ,') is kept
     */
    public function prepareAFormStringForValidation(string $string): array
    {
        $result = [];
        $buffer = '';
        $bracketDepth = 0;
        $parenDepth = 0;

        // collapses whitespace which is not inside brackets down to a single
        // space; it is not removed outright because a multi-word label needs its
        // internal spacing to survive as far as fieldNameFromDefinition(), which
        // turns it into the underscore the field is actually rendered with
        $string = preg_replace('/\s+(?![^[]*])/', ' ', $string);

        foreach (str_split($string) as $char) {
            // Depth, not a boolean: a regex rule's character class puts a ]
            // inside the rules block ('[regex:/^[A-Z],[0-9]$/]'), and a flat
            // toggle read that as the end of the block, splitting on the next
            // argument comma. Parens are tracked only at bracket depth zero —
            // they wrap an element's options (select(states)) there, while
            // inside a rules block they belong to a regex and may be
            // unbalanced.
            if ($char === '[') {
                $bracketDepth++;
            } elseif ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
            } elseif ($bracketDepth === 0 && $char === '(') {
                $parenDepth++;
            } elseif ($bracketDepth === 0 && $char === ')') {
                $parenDepth = max(0, $parenDepth - 1);
            }

            if ($bracketDepth === 0 && $parenDepth === 0 && $char === ',') {
                $result[] = trim($buffer);
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }

        if ($buffer !== '') {
            $result[] = trim($buffer);
        }

        return $result;
    }

    // VALIDATION METHODS -----------------------------------------------------

    // validate a form created with a string
    private function form(string $string): array
    {
        $return = [];

        $parts = $this->prepareAFormStringForValidation($string);

        foreach ($parts as $part) {
            [$name, $isMultiValue, $rules, $messages] = $this->parseValidationPart($part);

            // validate
            if ($this->request->hasFile($name)) {
                if (! isset($this->flick->upload)) {
                    throw FlickException::serviceIsNotAvailable('Upload');
                }
                $return[$name] = $this->flick->upload->file($name, $rules, $messages);
            } else {
                $return[$name] = $this->flick->validate->input(
                    $isMultiValue ? $name.'[]' : $name,
                    $rules,
                    $messages
                );
            }
        }

        return $return;
    }

    /**
     * Split one part of a validation form string into its name, multi-value
     * flag, rules, and messages.
     *
     * The element type's parenthesized options (checkbox([red:Red]),
     * select(states)) are stripped before the [rules][messages] blocks are
     * read — their brackets would otherwise be misread as rules — mirroring
     * the order Build::parseFormElement() uses when rendering.
     */
    private function parseValidationPart(string $part): array
    {
        $rules = [];
        $messages = [];

        // a {default} is presentation; drop it first so its content can't be
        // mistaken for a type or a rules block
        $cleaned = preg_replace('/\{[^}]*\}/', '', $part);

        $isMultiValue = $this->definitionRendersMultiValue($cleaned);

        $withoutOptions = $this->stripTypeOptions($cleaned);

        // an explicit [] right after the name also marks the field multi-value
        // (e.g. 'colors[][required]'); take the marker off so the pattern
        // below still sees name[rules][messages]
        if (preg_match('/^([^\[{|]+)\[\]/', $withoutOptions, $marker)) {
            $isMultiValue = true;
            $withoutOptions = $marker[1].substr($withoutOptions, strlen($marker[0]));
        }

        // split the part into name, rules, and messages — depth-aware, since a
        // regex rule's character class nests brackets inside the rules block
        // and a flat pattern truncated the rules at the class's closing ]
        [$name, $blocks] = $this->splitDefinitionBlocks($withoutOptions);

        if ($name !== '') {
            $rulesString = $blocks[0] ?? '';
            $messagesString = $blocks[1] ?? '';

            // process the rules
            if ($rulesString !== '') {
                $rules = $this->convertRulesToArray($rulesString);
            }

            // process the messages
            if ($messagesString !== '') {
                $messageArray = explode(',', $messagesString);
                foreach ($messageArray as $messageString) {
                    $messageParts = explode(':', $messageString, 2);
                    if (count($messageParts) === 2) {
                        $messages[trim($messageParts[0])] = trim($messageParts[1]);
                    }
                }
            }

            // resolve the name exactly as create() does, so a definition
            // string carrying a type or a default still lines up
            $name = Build::fieldNameFromDefinition($name);
        } else {
            // no match; use the part as the name
            $name = Build::fieldNameFromDefinition($part);
        }

        return [$name, $isMultiValue, $rules, $messages];
    }

    /**
     * Split one field part into its name portion and its top-level [ ... ]
     * block contents, tracking bracket depth so a regex character class
     * inside a rules block does not end the block early.
     *
     * @internal public so Build's DSL parser shares this splitter instead of
     * keeping a second [ ]-block grammar; not part of the developer API. Build
     * used /\[([^]]+)]/, which ends at the first ']' - so a bracketed pattern
     * was truncated in the fields registry and its tail leaked into the label
     * and field name, while Validate read the same string correctly.
     *
     * @return array{0: string, 1: list<string>} [name, block contents]
     */
    public function splitDefinitionBlocks(string $part): array
    {
        $name = '';
        $blocks = [];
        $buffer = '';
        $depth = 0;

        foreach (str_split($part) as $char) {
            if ($char === '[') {
                $depth++;
                if ($depth === 1) {
                    continue;
                }
            } elseif ($char === ']') {
                $depth = max(0, $depth - 1);
                if ($depth === 0) {
                    $blocks[] = $buffer;
                    $buffer = '';

                    continue;
                }
            }

            if ($depth === 0) {
                $name .= $char;
            } else {
                $buffer .= $char;
            }
        }

        return [$name, $blocks];
    }

    /**
     * Remove the element type's parenthesized options span, e.g. the
     * ([red:Red, green:Green]) of a checkbox group or the (states) of a
     * select. Only a parenthesis outside the [rules] blocks opens the span,
     * so a rule argument containing parentheses (a regex, say) is untouched.
     */
    private function stripTypeOptions(string $part): string
    {
        $bracketDepth = 0;

        for ($i = 0, $length = strlen($part); $i < $length; $i++) {
            $char = $part[$i];

            if ($char === '[') {
                $bracketDepth++;
            } elseif ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
            } elseif ($char === '(' && $bracketDepth === 0) {
                $close = strpos($part, ')', $i);

                if ($close === false) {
                    return $part;
                }

                return substr($part, 0, $i).substr($part, $close + 1);
            }
        }

        return $part;
    }

    // does this definition part describe an element that renders as name[]
    // (a selectMultiple, or a checkbox group carrying an options list)?
    private function definitionRendersMultiValue(string $cleaned): bool
    {
        $withoutOptions = $this->stripTypeOptions($cleaned);
        $typeSpec = explode('|', $withoutOptions, 2)[1] ?? '';

        if ($typeSpec === '') {
            return false;
        }

        $typeName = trim(explode('[', $typeSpec, 2)[0]);

        if ($typeName === 'selectMultiple') {
            return true;
        }

        return in_array($typeName, ['checkbox', 'checkboxInline'], true)
            && $withoutOptions !== $cleaned;
    }

    // get the raw value for the http request; validation runs against the
    // user's actual input, and encoding happens once at render time (Build)
    private function getHttpRequestValue($key): string|array|null
    {
        if ($this->request->hasPost($key)) {
            return $this->request->post($key);
        }

        if ($this->request->hasQuery($key)) {
            return $this->request->query($key);
        }

        return null;
    }

    // VALIDATE  --------------------------------------------------------------

    // make sure the given date has been properly formatted
    private function givenDateIsProperlyFormatted($match): bool
    {
        $d = DateTime::createFromFormat($this->flick->config('dateFormat'), $match);

        return $d && $d->format($this->flick->config('dateFormat')) == $match;
    }

    // check if a validation rule has been properly formatted
    private function ruleIsProperlyFormatted($rule, $key, $match, $messages): bool
    {
        if (! str_contains($rule, ':')) {
            $this->flick->addError($key, $messages, 'missingDelimiter', ':');

            return false;
        } elseif ($this->flick->inputIsEmpty($match)) {
            $this->flick->addError($key, $messages, 'missingArgument', ':');

            return false;
        }

        return true;
    }

    // VALIDATION RULES -------------------------------------------------------

    // ensures the value is accepted (checkbox/terms): "yes", "on", "1", 1, true, "true"
    private function accepted(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            $this->flick->addError($key, $messages, 'accepted');

            return;
        }

        $acceptedValues = ['yes', 'on', '1', 'true'];
        if (! in_array(strtolower($input), $acceptedValues, true)) {
            $this->flick->addError($key, $messages, 'accepted');
        }
    }

    // ensures the given date is after the matched date
    // uses Datetime dates and strings: after:today, after:tomorrow, after:2024-01-01
    private function after(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        $match = $this->ruleArgument($rule, 'after');

        $date = date($this->flick->config('dateFormat'), strtotime($match));

        if ($this->ruleIsProperlyFormatted($rule, $key, $match, $messages)) {
            if (! $this->givenDateIsProperlyFormatted($input)) {
                $this->flick->addError($key, $messages, 'date', $input);
            } elseif (strtotime($input) <= strtotime($date)) {
                $this->flick->addError($key, $messages, 'after', $date);
            }
        }
    }

    // ensures the given date is after, or same as, the matched date
    // uses Datetime dates and strings: after:today, after:tomorrow, after:2024-01-01
    private function afterOrEqual(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        $match = $this->ruleArgument($rule, 'afterOrEqual');

        $date = date($this->flick->config('dateFormat'), strtotime($match));

        if ($this->ruleIsProperlyFormatted($rule, $key, $match, $messages)) {
            if (! $this->givenDateIsProperlyFormatted($input)) {
                $this->flick->addError($key, $messages, 'date', $input);
            } elseif (strtotime($input) < strtotime($date)) {
                $this->flick->addError($key, $messages, 'afterOrEqual', $date);
            }
        }
    }

    // ensures the entered value contains only letters (spaces allowed)
    private function alpha(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        if (! ctype_alpha(str_replace(' ', '', $input))) {
            $this->flick->addError($key, $messages, 'alpha');
        }
    }

    // ensures the given value is alphanumeric and does not contain any other characters except - or _
    private function alphaDash(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        if (preg_match('/[^A-Za-z0-9_-]/', $input)) {
            $this->flick->addError($key, $messages, 'alphaDash');
        }
    }

    // ensures the entered value contains only letters and numbers (no spaces or symbols)
    private function alphaNumeric(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        if (! ctype_alnum($input)) {
            $this->flick->addError($key, $messages, 'alphaNumeric');
        }
    }

    // ensures the given date is before the matched date
    // uses Datetime dates and strings: after:today, after:tomorrow, after:2024-01-01
    private function before(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        $match = $this->ruleArgument($rule, 'before');

        $date = date($this->flick->config('dateFormat'), strtotime($match));

        if ($this->ruleIsProperlyFormatted($rule, $key, $match, $messages)) {
            if (! $this->givenDateIsProperlyFormatted($input)) {
                $this->flick->addError($key, $messages, 'date', $input);
            } elseif (strtotime($input) >= strtotime($date)) {
                $this->flick->addError($key, $messages, 'before', $date);
            }
        }
    }

    // ensures the given date is before, or same as, the matched date
    // uses Datetime dates and strings: after:today, after:tomorrow, after:2024-01-01
    private function beforeOrEqual(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        $match = $this->ruleArgument($rule, 'beforeOrEqual');

        $date = date($this->flick->config('dateFormat'), strtotime($match));

        if ($this->ruleIsProperlyFormatted($rule, $key, $match, $messages)) {
            if (! $this->givenDateIsProperlyFormatted($input)) {
                $this->flick->addError($key, $messages, 'date', $input);
            } elseif (strtotime($input) > strtotime($date)) {
                $this->flick->addError($key, $messages, 'beforeOrEqual', $date);
            }
        }
    }

    // ensures the entered integer is between two numbers
    private function between(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        $match = $this->ruleArgument($rule, 'between');

        if ($this->ruleIsProperlyFormatted($rule, $key, $match, $messages)) {
            $parts = explode(',', $match);

            if (count($parts) !== 2) {
                $this->flick->addError($key, $messages, 'missingArgument', ':');

                return;
            }

            // Guard against non-numeric input the same way the other comparison
            // rules do; without this, '(float) "5cats"' is 5.0 and slips through.
            if (! is_numeric($input)) {
                $this->flick->addError($key, $messages, 'numeric', $match);
            } elseif ((float) $input < (float) $parts[0] || (float) $input > (float) $parts[1]) {
                $this->flick->addError($key, $messages, 'between', $parts);
            }
        }
    }

    // ensures the value is boolean-like: true, false, 1, 0, "1", "0", "true", "false"
    private function boolean(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        $booleanValues = ['true', 'false', '1', '0'];
        if (! in_array(strtolower($input), $booleanValues, true)) {
            $this->flick->addError($key, $messages, 'boolean');
        }
    }

    // sugar for matches:{field}_confirmation - validates password confirmation fields
    private function confirmed(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        $confirmationField = $key.'_confirmation';
        $confirmationValue = $this->applyTrimPolicy($confirmationField, $this->request->input($confirmationField));

        if ($input !== $confirmationValue) {
            $this->flick->addError($key, $messages, 'confirmed', $confirmationField);
        }
    }

    // validates a credit card number using the Luhn algorithm
    private function creditCard(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        // strip spaces and dashes from the card number
        $cleanNumber = preg_replace('/[\s-]/', '', $input);

        // must be 13-19 digits
        if (! preg_match('/^\d{13,19}$/', $cleanNumber)) {
            $this->flick->addError($key, $messages, 'creditCard');

            return;
        }

        // Luhn algorithm
        $sum = 0;
        $isEven = false;

        for ($i = strlen($cleanNumber) - 1; $i >= 0; $i--) {
            $digit = (int) $cleanNumber[$i];

            if ($isEven) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $isEven = ! $isEven;
        }

        if ($sum % 10 !== 0) {
            $this->flick->addError($key, $messages, 'creditCard');
        }
    }

    // ensures the value is a valid date in the configured dateFormat (parity with the JS 'date' rule)
    private function date(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        if (! $this->givenDateIsProperlyFormatted($input)) {
            $this->flick->addError($key, $messages, 'date', $input);
        }
    }

    // ensures the entered value is exactly N digits (e.g. digits:5 for a ZIP code)
    private function digits(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        $match = $this->ruleArgument($rule, 'digits');

        if ($this->ruleIsProperlyFormatted($rule, $key, $match, $messages)) {
            if (! ctype_digit($input) || strlen($input) !== (int) $match) {
                $this->flick->addError($key, $messages, 'digits', $match);
            }
        }
    }

    // ensures the entered value is digits with a length between min and max (e.g. digitsBetween:4,6)
    private function digitsBetween(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        $match = $this->ruleArgument($rule, 'digitsBetween');

        if ($this->ruleIsProperlyFormatted($rule, $key, $match, $messages)) {
            $parts = explode(',', $match);

            if (count($parts) !== 2) {
                $this->flick->addError($key, $messages, 'missingArgument', ':');

                return;
            }

            $length = strlen($input);

            if (! ctype_digit($input) || $length < (int) $parts[0] || $length > (int) $parts[1]) {
                $this->flick->addError($key, $messages, 'digitsBetween', $parts);
            }
        }
    }

    // ensures the entered string is formatted as an email address
    private function email(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        if (! filter_var($input, FILTER_VALIDATE_EMAIL)) {
            $this->flick->addError($key, $messages, 'email');
        }
    }

    // ensures the entered value ends with one of the given suffixes (e.g. endsWith:.com,.net)
    private function endsWith(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        $match = $this->ruleArgument($rule, 'endsWith');

        if ($this->ruleIsProperlyFormatted($rule, $key, $match, $messages)) {
            $suffixes = array_map('trim', explode(',', $match));

            foreach ($suffixes as $suffix) {
                if ($suffix !== '' && str_ends_with($input, $suffix)) {
                    return;
                }
            }

            $this->flick->addError($key, $messages, 'endsWith', $match);
        }
    }

    // ensures the entered string equals `match`
    private function equals(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        $match = $this->ruleArgument($rule, 'equals');

        if ($this->ruleIsProperlyFormatted($rule, $key, $match, $messages)) {
            // strict comparison: '1e2', '+100' or '  100' must not satisfy
            // equals:100 through numeric type juggling
            if ($input !== $match) {
                $this->flick->addError($key, $messages, 'equals', $match);
            }
        }
    }

    // ensures the entered string has the same number of characters as `match`
    private function exact(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        $match = $this->ruleArgument($rule, 'exact');

        if ($this->ruleIsProperlyFormatted($rule, $key, $match, $messages)) {
            if (mb_strlen($input, 'UTF-8') != $match) {
                $this->flick->addError($key, $messages, 'exact', $match);
            }
        }
    }

    // ensures the entered value is greater than `match`
    private function greaterThan(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        $match = $this->ruleArgument($rule, 'greaterThan');

        if ($this->ruleIsProperlyFormatted($rule, $key, $match, $messages)) {
            if (! is_numeric($input)) {
                $this->flick->addError($key, $messages, 'numeric', $match);
            } elseif ((float) $input <= (float) $match) {
                $this->flick->addError($key, $messages, 'greaterThan', $match);
            }
        }
    }

    // ensures the entered value is greater than, or equal to, `match`
    private function greaterThanOrEqual(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        $match = $this->ruleArgument($rule, 'greaterThanOrEqual');

        if ($this->ruleIsProperlyFormatted($rule, $key, $match, $messages)) {
            if (! is_numeric($input)) {
                $this->flick->addError($key, $messages, 'numeric', $match);
            } elseif ((float) $input < (float) $match) {
                $this->flick->addError($key, $messages, 'greaterThanOrEqual', $match);
            }
        }
    }

    // ensures the entered value is in the comma-separated list
    private function in(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        $match = $this->ruleArgument($rule, 'in');

        if ($this->ruleIsProperlyFormatted($rule, $key, $match, $messages)) {
            $allowedValues = array_map('trim', explode(',', $match));
            if (! in_array($input, $allowedValues, true)) {
                $this->flick->addError($key, $messages, 'in', $match);
            }
        }
    }

    // ensures the entered value is an integer
    private function integer(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        if (filter_var($input, FILTER_VALIDATE_INT) === false) {
            $this->flick->addError($key, $messages, $rule);
        }
    }

    // ensures the entered value is a properly formatted ip address
    private function ip(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        if (! filter_var($input, FILTER_VALIDATE_IP)) {
            $this->flick->addError($key, $messages, 'ip');
        }
    }

    // ensures the entered value is a properly formatted IPv4 address
    private function ipv4(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        if (! filter_var($input, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $this->flick->addError($key, $messages, 'ipv4');
        }
    }

    // ensures the entered value is a properly formatted IPv6 address
    private function ipv6(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        if (! filter_var($input, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $this->flick->addError($key, $messages, 'ipv6');
        }
    }

    // ensures the entered value is valid JSON
    private function json(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        json_decode($input);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->flick->addError($key, $messages, 'json');
        }
    }

    // ensures the entered value is less than `match`
    private function lessThan(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        $match = $this->ruleArgument($rule, 'lessThan');

        if ($this->ruleIsProperlyFormatted($rule, $key, $match, $messages)) {
            if (! is_numeric($input)) {
                $this->flick->addError($key, $messages, 'numeric', $match);
            } elseif ((float) $input >= (float) $match) {
                $this->flick->addError($key, $messages, 'lessThan', $match);
            }
        }
    }

    // ensures the entered value is less than, or equal to, `match`
    private function lessThanOrEqual(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        $match = $this->ruleArgument($rule, 'lessThanOrEqual');

        if ($this->ruleIsProperlyFormatted($rule, $key, $match, $messages)) {
            if (! is_numeric($input)) {
                $this->flick->addError($key, $messages, 'numeric', $match);
            } elseif ((float) $input > (float) $match) {
                $this->flick->addError($key, $messages, 'lessThanOrEqual', $match);
            }
        }
    }

    // ensures the entered value exactly matches `match`
    private function matches(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        $match = $this->ruleArgument($rule, 'matches');

        if ($this->ruleIsProperlyFormatted($rule, $key, $match, $messages)) {
            $matchInput = $this->applyTrimPolicy($match, $this->request->input($match));
            if ($input !== $matchInput) {
                $this->flick->addError($key, $messages, 'matches', $match);
            }
        }
    }

    // ensures the entered value does not contain more characters than `match`
    private function max(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        $match = $this->ruleArgument($rule, 'max');

        if ($this->ruleIsProperlyFormatted($rule, $key, $match, $messages)) {
            if (mb_strlen($input, 'UTF-8') > $match) {
                $this->flick->addError($key, $messages, 'max', $match);
            }
        }
    }

    // ensures the entered value contains at least the number of characters as `match`
    private function min(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        $match = $this->ruleArgument($rule, 'min');

        if ($this->ruleIsProperlyFormatted($rule, $key, $match, $messages)) {
            if (mb_strlen($input, 'UTF-8') < $match) {
                $this->flick->addError($key, $messages, 'min', $match);
            }
        }
    }

    // ensures the entered value is NOT in the comma-separated list
    private function notIn(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        $match = $this->ruleArgument($rule, 'notIn');

        if ($this->ruleIsProperlyFormatted($rule, $key, $match, $messages)) {
            $disallowedValues = array_map('trim', explode(',', $match));
            if (in_array($input, $disallowedValues, true)) {
                $this->flick->addError($key, $messages, 'notIn', $match);
            }
        }
    }

    // ensures the entered value does NOT match the value of the `match` field
    private function notMatches(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        $match = $this->ruleArgument($rule, 'notMatches');

        if ($this->ruleIsProperlyFormatted($rule, $key, $match, $messages)) {
            // $input arrived trimmed, so the counterpart has to get the same
            // policy or two values differing only by surrounding whitespace
            // would pass a rule they should fail
            $matchInput = $this->applyTrimPolicy($match, $this->request->input($match));

            if ($input === $matchInput) {
                $this->flick->addError($key, $messages, 'notMatches', $match);
            }
        }
    }

    // ensures the entered value does not adhere to the supplied regex
    private function notRegex(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        $match = $this->ruleArgument($rule, 'notRegex');

        if ($this->ruleIsProperlyFormatted($rule, $key, $match, $messages)) {
            // A malformed pattern makes preg_match return false and emit a
            // warning. Capture that quietly and treat it as a failure (fail
            // closed) rather than letting the field pass silently.
            $patternValid = true;
            set_error_handler(function () use (&$patternValid): bool {
                $patternValid = false;

                return true;
            });
            $matched = preg_match($match, $input);
            restore_error_handler();

            if (! $patternValid || $matched === 1) {
                $this->flick->addError($key, $messages, 'notRegex', $key);
            }
        }
    }

    // ensures the entered value is numeric
    private function numeric(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        if ($this->flick->inputIsNotEmpty($input) && ! is_numeric($input)) {
            $this->flick->addError($key, $messages, 'numeric');
        }
    }

    // validates a phone number (10-16 digits after stripping formatting)
    private function phone(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        // strip common formatting characters
        $cleanPhone = preg_replace('/[\s\-\(\)\.]+/', '', $input);

        // must start with optional + and digit, then 9-15 more digits (10-16 total)
        if (! preg_match('/^[\+]?[1-9]\d{9,15}$/', $cleanPhone)) {
            $this->flick->addError($key, $messages, 'phone');

            return;
        }

        // verify length is between 10 and 16 digits
        $digitCount = strlen(preg_replace('/[^\d]/', '', $cleanPhone));
        if ($digitCount < 10 || $digitCount > 16) {
            $this->flick->addError($key, $messages, 'phone');
        }
    }

    // ensures the entered value adheres to the supplied regex
    private function regex(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        $match = $this->ruleArgument($rule, 'regex');

        if ($this->ruleIsProperlyFormatted($rule, $key, $match, $messages)) {
            if (! preg_match($match, $input)) {
                $this->flick->addError($key, $messages, 'regex', $key);
            }
        }
    }

    // ensures there is a value
    private function required(string $input, string $rule, string $key, array $messages): void
    {
        // Trim before the emptiness check so a whitespace-only value ("   ") fails
        // required, matching the client-side rule (which trims). '0' is preserved
        // by inputIsEmpty's special case.
        if ($this->flick->inputIsEmpty(trim($input))) {
            // Pass $rule through (not a hardcoded 'required') so a custom message
            // keyed by the 'r' alias still fires when that's what was typed.
            $this->flick->addError($key, $messages, $rule, $key);
        }
    }

    // ensures there is a value if the match field is present
    private function requiredWith(string $input, string $rule, string $key, array $messages): void
    {
        $match = $this->ruleArgument($rule, 'requiredWith');

        if ($this->ruleIsProperlyFormatted($rule, $key, $match, $messages)) {
            $matchInput = $this->applyTrimPolicy($match, $this->request->input($match));

            // Trim both values for the emptiness tests, exactly as required()
            // does and as the client rule does on both sides. Under the default
            // trim policy the pipeline has already stripped them and this is a
            // no-op; with 'trim' => false the raw values arrive here, and
            // without this the two runtimes disagreed in both directions - PHP
            // accepted a whitespace-only value the client rejected, and PHP
            // required a value when the match field held only whitespace, which
            // the client did not.
            //
            // Only the emptiness DECISION trims. applyTrimPolicy() is untouched,
            // so 'trim' => false still hands the raw value to every other rule
            // and to the modifiers.
            $matchIsPresent = $this->flick->inputIsNotEmpty(
                is_string($matchInput) ? trim($matchInput) : $matchInput
            );

            if ($matchIsPresent && $this->flick->inputIsEmpty(trim($input))) {
                $this->flick->addError($key, $messages, 'requiredWith', $match);
            }
        }
    }

    // ensures the entered value starts with one of the given prefixes (e.g. startsWith:http,https)
    private function startsWith(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        $match = $this->ruleArgument($rule, 'startsWith');

        if ($this->ruleIsProperlyFormatted($rule, $key, $match, $messages)) {
            $prefixes = array_map('trim', explode(',', $match));

            foreach ($prefixes as $prefix) {
                if ($prefix !== '' && str_starts_with($input, $prefix)) {
                    return;
                }
            }

            $this->flick->addError($key, $messages, 'startsWith', $match);
        }
    }

    // validates password strength (uppercase, lowercase, digit, special char)
    // usage: strongPassword (defaults to 8) or strongPassword:12 (custom minimum length)
    private function strongPassword(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        // parse the minimum length from the rule, default to 8
        $minLength = 8;
        if (str_contains($rule, ':')) {
            $minLength = (int) $this->ruleArgument($rule, 'strongPassword');
            if ($minLength < 1) {
                $minLength = 8;
            }
        }

        $hasMinLength = mb_strlen($input, 'UTF-8') >= $minLength;
        $hasUppercase = preg_match('/[A-Z]/', $input);
        $hasLowercase = preg_match('/[a-z]/', $input);
        $hasNumber = preg_match('/\d/', $input);
        $hasSpecialChar = preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"|,.<>\/?]/', $input);

        if (! ($hasMinLength && $hasUppercase && $hasLowercase && $hasNumber && $hasSpecialChar)) {
            $this->flick->addError($key, $messages, 'strongPassword', (string) $minLength);
        }
    }

    // ensures the entered value is a properly formatted url
    private function url(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        if (! filter_var($input, FILTER_VALIDATE_URL)) {
            $this->flick->addError($key, $messages, 'url');
        }
    }

    // ensures the entered value is a valid UUID (version 4)
    private function uuid(string $input, string $rule, string $key, array $messages): void
    {
        if ($this->flick->inputIsEmpty($input)) {
            return;
        }

        // UUID v4 format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
        // where y is 8, 9, a, or b
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        if (! preg_match($pattern, $input)) {
            $this->flick->addError($key, $messages, 'uuid');
        }
    }
}
