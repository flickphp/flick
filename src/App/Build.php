<?php

declare(strict_types=1);

namespace Flick\App;

use Flick\Flick;
use Flick\Http\NativeRequest;
use Flick\Http\RequestInterface;
use Flick\Support\FlickException;
use Flick\Support\Support;
use Random\RandomException;

class Build
{
    /*
     * The escaping boundary, stated once so it is not re-litigated.
     *
     * Values and ids are ESCAPED. Request data reaches exactly three places in
     * this class and all three are a field's value: the repopulation in
     * buildFieldElementAttributes() and the two selected-option checks in
     * buildSelectMenuOptions(). Ids are escaped because array checkboxes and
     * radios derive theirs from the value.
     *
     * Labels are RAW. A field label, a submit button's text and a fieldset's
     * label are authored by the developer building the form — they are method
     * arguments, never request data. Leaving them raw is what lets
     * '<i class="fa-search"></i> Search' work as button text and
     * 'I agree to the <a href="/terms">Terms</a>' work as a checkbox label. A
     * developer who builds a label out of user data escapes it themselves, the
     * same as anywhere else in their own template.
     *
     * <option> text is the exception, and stays ESCAPED: markup is inert inside
     * an <option>, so raw HTML there can only corrupt the tag (a label containing
     * '</option>' would break the menu) and can never render anything. That is a
     * difference in rendering context, not a difference in who is trusted.
     */

    /**
     * HTTP request adapter for accessing POST, GET, FILES, etc.
     *
     * A RequestInterface since the legacy App\Request shim (a single input()
     * method) was removed; the Helpers trait that once reached for it through
     * the public Flick::$build no longer lives on this class at all.
     */
    protected RequestInterface $request;

    /**
     * True while buildBooleanGroup() renders its label and options, whose
     * per-element passes must not write $flick->fields rows of their own.
     */
    private bool $suppressFieldRegistration = false;

    public function __construct(
        public Flick $flick,
        public Support $support,
        ?RequestInterface $request = null
    ) {
        $this->request = $request ?? new NativeRequest;
    }

    /**
     * The value a field should re-render with, and whether it came from
     * untrusted input.
     *
     * One ordered source list for every field type: POST, then GET, then the
     * session, then the developer's default. Text inputs used to let the
     * session win over POST while checkboxes, radios and selects let POST win
     * - and the session holds the value AFTER validation modifiers have run,
     * so a bcrypt'd password was rendered straight back into the field.
     *
     * The RAW submitted value is returned deliberately: going through
     * request() would re-run validation and re-apply destructive string
     * modifiers at render time.
     *
     * $default is mixed because an array field definition can carry any value
     * under its 'value' key; a string type here would be a latent TypeError.
     *
     * @return array{0: mixed, 1: bool} [value, came from request or session]
     */
    private function resolveFieldValue(string $key, mixed $default): array
    {
        // colors[] is posted under colors. Character-list trim, deliberately -
        // it is correct for the trailing [] this supports, and nested names
        // (items[0][sku]) are not supported.
        $lookupKey = str_ends_with($key, '[]') ? rtrim($key, '[]') : $key;

        if ($this->request->hasPost($lookupKey)) {
            return [$this->request->post($lookupKey), true];
        }

        if ($this->request->hasQuery($lookupKey)) {
            return [$this->request->query($lookupKey), true];
        }

        if ($this->flick->persistingToSession() && $this->flick->hasSessionValue($lookupKey)) {
            return [$this->flick->getSessionValue($lookupKey), true];
        }

        return [$default, false];
    }

    // FORM INPUTS ------------------------------------------------------------

    // handles checkboxes and radios
    public function boolean(string $type, array|string $name, string $label, string $value, array|string $attributes): string
    {
        $data = $this->buildFieldElementAttributes($type, $name, $label, $value, $attributes);

        // escape the option value like buildSelectMenuOptions() does for select
        // options — a value can carry a repopulated request value, so it must not
        // inject markup. The id gets the same treatment because array checkboxes
        // and radios derive theirs from the value.
        //
        // The label deliberately stays raw: labels are developer-authored, never
        // request data (see the Labels note in Build's class docblock).
        $data['value'] = htmlspecialchars((string) $data['value']);
        $data['id'] = htmlspecialchars((string) $data['id']);

        return $this->support->return(View::make($data, $this->flick)->render());
    }

    public function close(): string
    {
        return $this->support->return(PHP_EOL.'</form>'.PHP_EOL);
    }

    // create a text input with a datalist for autocomplete suggestions
    public function datalist(array|string $name, string $label, string $value, array $options, array|string $attributes): string
    {
        // handle array configuration - merge datalist options into attributes
        if (is_array($name)) {
            if (! isset($name['attributes'])) {
                $name['attributes'] = [];
            }
            $name['attributes']['datalist'] = $options;

            return $this->input('text', $name, $label, $value, $attributes);
        }

        // handle standard parameters
        if (is_array($attributes)) {
            $attributes['datalist'] = $options;
        } else {
            $attributes = ['datalist' => $options];
        }

        return $this->input('text', $name, $label, $value, $attributes);
    }

    public function hidden(string $name, string $value): string
    {
        return $this->support->return(
            '<input type="hidden" name="'.htmlspecialchars($name).'" id="'.htmlspecialchars($name).'" value="'.htmlspecialchars($value).'">'.PHP_EOL
        );
    }

    // handles all input types except checkbox, hidden, select, radio, & textarea
    public function input(string $type, array|string $name, string $label, string $value, array|string $attributes): string
    {
        $data = $this->buildFieldElementAttributes($type, $name, $label, $value, $attributes);

        return $this->support->return(View::make($data, $this->flick)->render());
    }

    public function fieldsetClose(): string
    {
        return $this->support->return('</fieldset>'.PHP_EOL);
    }

    public function fieldsetOpen(string $legend = ''): string
    {
        $html = '<fieldset>'.PHP_EOL;

        if (! empty($legend)) {
            $html .= '<legend>'.htmlspecialchars($legend).'</legend>'.PHP_EOL;
        }

        return $this->support->return($html);
    }

    public function labelClose(): string
    {
        return $this->support->return('</label>'.PHP_EOL);
    }

    public function labelOpen(string $for, string $label): string
    {
        return $this->support->return('<label for="'.htmlspecialchars($for).'">'.htmlspecialchars($label));
    }

    public function open(string $action, string $method, array|string $attributes): string
    {
        // '/' is what every caller passes when no action was given -- Flick::open()
        // and openMultipart() default to it, and both the create() and form-file
        // paths fall back to it -- so it means "unspecified" rather than "the site
        // root", which is why it has always been replaced here.
        //
        // A configured action is the developer's own instruction and wins over the
        // request path, including any query string it carries. The derived default
        // still drops the query string: FormActionTest pins that, and carrying
        // ?step= forward would stall createMultistep().
        if ($action == '/' || $action == '') {
            $action = $this->flick->hasConfiguredAction()
                ? (string) $this->flick->config('action')
                : Support::requestPath($this->request);
        }

        // add the <form> tag
        $return = '<form action="'.htmlspecialchars($action).'" method="'.htmlspecialchars($method).'"';

        if (isset($attributes['id'])) {
            $id = (string) $attributes['id'];

            // services resolve the form id lazily through the shared Support
            // instance; adoptFormId syncs all three homes at once
            $this->flick->adoptFormId($id);
        } else {
            $id = $this->flick->config('id');
        }

        $return .= ' id="'.htmlspecialchars((string) $id).'"';

        $omittedAttributes = ['id', 'button', 'action', 'method'];

        if (! empty($attributes)) {
            if (is_array($attributes)) {
                // create a string from the attributes array
                foreach ($attributes as $key => $value) {
                    if (! in_array($key, $omittedAttributes)) {
                        if ($key == 'string') {
                            $return .= ' '.$value;
                        } elseif ($key == 'multipart') {
                            $return .= ' enctype="multipart/form-data"';
                        } elseif (is_bool($value)) {
                            if ($value === true) {
                                $return .= ' '.$key;
                            }
                        } else {
                            $return .= ' '.htmlspecialchars($key).'="'.htmlspecialchars((string) $value).'"';
                        }
                    }
                }
            } else {
                $return .= ' '.$attributes;
            }
        }

        $return .= '>'.PHP_EOL;

        // add a hidden element to track which form was submitted
        $return .= '<input type="hidden" name="_id" value="'.htmlspecialchars((string) $id).'">'.PHP_EOL;

        // add a csrf token if a session is active or a custom generator is set
        if ($this->flick->session->isActive() || Flick::getDefaultCsrfTokenGenerator() !== null) {
            $return .= $this->addCsrfToken();
        }

        // add a honeypot
        if (! empty($this->flick->config('honeypot'))) {
            $return .= '<input type="text" name="'.htmlspecialchars((string) $this->flick->config('honeypot')).'" value="" style="display:none">'.PHP_EOL;
        }

        return $this->support->return($return);
    }

    public function select(array|string $name, string $label, string $value, array|string $attributes): string
    {
        $attributes = $this->buildSelectMenuAttributesArray($attributes);

        $data = $this->buildFieldElementAttributes('select', $name, $label, $value, $attributes);

        return $this->support->return(View::make($data, $this->flick)->render());
    }

    public function selectMultiple(array|string $name, string $label, string $value, array|string $attributes): string
    {
        $attributes = $this->buildSelectMenuAttributesArray($attributes);

        $attributes['multiple'] = true;

        // $name may be an array-style field definition (from create()); append the
        // [] to the string name so multi-value POSTs arrive as an array
        $nameKey = is_array($name) ? ($name['name'] ?? '') : $name;

        if (! str_ends_with($nameKey, ']')) {
            $nameKey .= '[]';
        }

        if (is_array($name)) {
            // buildFieldElementAttributes reads attributes off the element array,
            // so the 'multiple' flag has to live there for the create() path
            $name['name'] = $nameKey;
            $name['attributes'] = ($name['attributes'] ?? []) + ['multiple' => true];
        } else {
            $name = $nameKey;
        }

        $data = $this->buildFieldElementAttributes('select', $name, $label, $value, $attributes);

        return $this->support->return(View::make($data, $this->flick)->render());
    }

    public function submit(string $text, array|string $attributes): string
    {
        $data = $this->buildFieldElementAttributes('submit', 'submit', $text, '', $attributes);

        return $this->support->return(View::make($data, $this->flick)->render());
    }

    public function textarea(array|string $name, string $label, string $value, array|string $attributes): string
    {
        $data = $this->buildFieldElementAttributes('textarea', $name, $label, $value, $attributes);

        return $this->support->return(View::make($data, $this->flick)->render());
    }

    // BIG DOG ----------------------------------------------------------------

    // build $data array & merge all attributes into a string for its view file
    /**
     * Read the attributes argument every field element accepts: an array as
     * is, or a string where 'id=...' and 'class=...' are that one attribute
     * and anything else is raw attribute text.
     *
     * @internal public so Flick's inline boolean methods can set their flag on
     * the array form before handing it back here; not part of the developer
     * API. They used to write the flag straight onto the parameter and fatal
     * on the string form that checkbox() and radio() accept.
     */
    public static function attributesToArray(array|string $attributes): array
    {
        if (is_array($attributes)) {
            return $attributes;
        }

        if (str_starts_with($attributes, 'id=')) {
            return ['id' => trim(str_replace('id=', '', $attributes), '"')];
        }

        if (str_starts_with($attributes, 'class=')) {
            return ['class' => trim(str_replace('class=', '', $attributes), '"')];
        }

        return ['string' => $attributes];
    }

    private function buildFieldElementAttributes(string $type, array|string $element, string $label, string $value, array|string $attributes): array
    {
        $attributes = self::attributesToArray($attributes);

        if (is_array($element)) {
            $key = str_replace(' ', '_', $element['name']);
            $label = $element['label'] ?? '';
            $value = $element['value'] ?? '';
            $attributes = $element['attr'] ?? $element['attributes'] ?? [];
            $id = $element['id'] ?? $attributes['id'] ?? str_replace('[]', '', $key);
            $required = $this->isRequired($attributes);

            if (isset($element['options'])) {
                $attributes['options'] = $element['options'];
            }
        } else {
            $key = str_replace(' ', '_', $element);
            $id = $attributes['id'] ?? str_replace('[]', '', $key);
            $required = $this->isRequired($attributes);
        }

        // global client-side validation rules supplied via $config
        if (isset($attributes['rules'])) {
            $rules = $attributes['rules'];
        } elseif (isset($this->flick->config['rules'][$key])) {
            $rules = $this->flick->config['rules'][$key];
        }

        // global client-side validation messages supplied via $config
        if (isset($attributes['messages'])) {
            $messages = $attributes['messages'];
        } elseif (isset($this->flick->config['messages'][$key])) {
            $messages = $this->flick->config['messages'][$key];
        }

        // housekeeping: check for missing options
        if ($type == 'select' && empty($attributes['options'])) {
            throw FlickException::missingOptions($key);
        }

        // housekeeping: convert the 'datetime' input alias to 'datetime-local'
        if ($type == 'datetime') {
            $type = 'datetime-local';
        }

        // put everything into a $data array for later
        $data = [
            'type' => $type,
            'name' => $key,
            'label' => $label,
            'id' => $id,
            'value' => $value,
            'required' => $required,
            'views' => $this->flick->config('views'),
            'attributes' => '',
            'rules' => $rules ?? null,
            'messages' => $messages ?? null,
        ];

        // radios and array checkboxes share the same name, so append value to make ID unique
        if ($type == 'radio' || ($type == 'checkbox' && str_ends_with($key, '[]'))) {
            $data['id'] .= ucwords($value);
        }

        // build the element's attributes
        if (! empty($attributes)) {
            foreach ($attributes as $attrKey => $attrValue) {
                if ($this->flick->inputIsNotEmpty($attrValue)) {
                    // 'type' has already been consumed to pick the element; writing
                    // it again here would put a second type= on the tag
                    if (in_array(trim($attrKey), ['required', 'options', 'rules', 'messages', 'id', 'datalist', 'type'])) {
                        continue;
                    } elseif (trim($attrKey) == 'help') {
                        $data['help'] = ' '.$attrValue;
                    } elseif (trim($attrKey) == 'class' || trim($attrKey) == 'classes' || trim($attrKey) == 'css') {
                        $data['classes'] = $attrValue;
                    } elseif (trim($attrKey) == 'string') {
                        $data['attributes'] .= ' '.$attrValue;
                    } elseif (trim($attrKey) == 'multiple') {
                        $data['attributes'] .= ' multiple';
                    } elseif (trim($attrKey) == 'inline') {
                        // 'inline' drives view selection only; it is not a real HTML
                        // attribute, so record the flag and keep it out of the markup
                        $data['inline'] = true;
                    } elseif ($this->flick->submitted() && in_array($type, ['radio', 'checkbox']) && trim($attrKey) == 'checked') {
                        // after submission we don't want a radio/checkbox to keep its default
                        // 'checked' attribute; the submitted value decides the checked state
                        continue;
                    } elseif (is_bool($attrValue)) {
                        // boolean attributes: true adds the attribute, false skips it
                        if ($attrValue === true) {
                            $safeKey = preg_replace('/[^A-Za-z0-9_:.-]/', '', trim((string) $attrKey));
                            if ($safeKey !== '') {
                                $data['attributes'] .= ' '.$safeKey;
                            }
                        }
                    } else {
                        // Strip characters that aren't valid in an attribute name so a
                        // crafted key (e.g. 'onmouseover=alert(1) x') can't inject extra
                        // attributes; the value is already escaped.
                        $safeKey = preg_replace('/[^A-Za-z0-9_:.-]/', '', trim((string) $attrKey));
                        if ($safeKey !== '') {
                            $data['attributes'] .= ' '.$safeKey.'="'.htmlspecialchars((string) $attrValue).'"';
                        }
                    }
                }
            }
        }

        $this->setViewFileType($data);
        $this->setViewFilePath($data);

        // Resolve the field's value once, from one ordered source list.
        // 'boolean-group-label' is a pseudo-type for a group's label; it has no
        // submitted value of its own.
        [$resolvedValue, $fromUntrustedInput] = $type === 'boolean-group-label'
            ? [$value, false]
            : $this->resolveFieldValue($key, $value);

        // Checkbox and radio keep the option value they were handed - the
        // resolved value only decides whether they are checked. For every other
        // type the resolved value IS the field's value, and is HTML-encoded
        // exactly once here when it came from request or session input.
        if (! in_array($type, ['checkbox', 'radio'])) {
            $data['value'] = $fromUntrustedInput && ! empty($resolvedValue)
                ? $this->flick->sanitizeRequest($resolvedValue)
                : $resolvedValue;
        }

        // handle datalist options for autocomplete suggestions
        if (isset($attributes['datalist']) && is_array($attributes['datalist'])) {
            $data['datalist'] = $this->buildDatalistOptions($attributes['datalist'], $data['id']);
            $data['datalistId'] = $data['id'].'-datalist';
            $data['attributes'] .= ' list="'.$data['datalistId'].'"';
        }

        if ($data['type'] == 'select') {
            // build up a select menu's <options>
            if (! empty($attributes['options'])) {
                $options = '';

                // build each <option>
                if (is_array($attributes['options'])) {
                    foreach ($attributes['options'] as $optionValue => $optionLabel) {
                        if (is_array($optionLabel)) {
                            $options .= '<optgroup label="'.htmlspecialchars((string) $optionValue).'">';
                            foreach ($optionLabel as $fsKey => $fsValue) {
                                $options .= $this->buildSelectMenuOptions($fsKey, $fsValue, $resolvedValue);
                            }
                            $options .= '</optgroup>';
                        } else {
                            $options .= $this->buildSelectMenuOptions($optionValue, $optionLabel, $resolvedValue);
                        }
                    }
                }

                $data['options'] = $options;
            }
        } elseif (in_array($type, ['checkbox', 'radio'])) {
            // The developer's default must not tick a box, so only a value that
            // came from request or session input counts here.
            if ($fromUntrustedInput) {
                $checked = is_array($resolvedValue)
                    ? in_array($value, $resolvedValue)
                    : $resolvedValue == $value;

                if ($checked) {
                    $data['attributes'] .= ' checked';
                }
            }

            if (isset($attributes['inline'])) {
                $data['inline'] = true;
            }
        }

        // get an error if available
        $key = str_replace('[]', '', $key);

        $data['error_display'] = 'none';

        if (array_key_exists($key, $this->flick->getErrors())) {
            $data['error'] = $this->flick->getError($key);
        }

        if (! empty($data['error'])) {
            $data['error_display'] = 'block';
            $errorId = 'has-error-'.$data['id'];

            if (! str_contains($data['attributes'], 'aria-invalid')) {
                $data['attributes'] .= ' aria-invalid="true"';
            }

            if (! str_contains($data['attributes'], 'aria-describedby')) {
                $data['attributes'] .= ' aria-describedby="'.$errorId.'"';
            }
        }

        // housekeeping: update the attributes string so the browser knows this is required
        if ($required) {
            $data['attributes'] .= ' required';
        }

        $this->addElementDataToFieldsArray($data, $attributes);

        return $data;
    }

    // FORM BUILDERS ----------------------------------------------------------

    // build checkbox or radio groups
    private function buildBooleanGroup(string $type, array $element): string
    {
        $return = '';
        $baseName = $element['name'];
        $isInline = str_ends_with($type, 'Inline');
        $baseType = $isInline ? rtrim($type, 'Inline') : $type; // 'checkbox' or 'radio'

        // One registry row for the whole group, keyed by the base name: the
        // group's HTML name, base id, submitted selection, and option list.
        // The label and per-option passes below render markup only — each used
        // to write its own row ('colors' from the label with no value,
        // 'colors[]' from whichever option happened to render last).
        $base = str_replace([' ', '[]'], ['_', ''], $baseName);
        [$selection] = $this->resolveFieldValue($base, $element['value'] ?? '');

        $row = [
            'name' => $baseType === 'checkbox' ? $base.'[]' : $base,
            'id' => $base,
            'value' => $selection,
            'attributes' => '',
            'options' => $element['attributes']['options'],
        ];

        if (! empty($element['attributes']['rules'])) {
            $row['rules'] = $element['attributes']['rules'];
        }

        if (! empty($element['attributes']['messages'])) {
            $row['messages'] = $element['attributes']['messages'];
        }

        $this->flick->fields[$base] = $row;

        $this->suppressFieldRegistration = true;

        try {
            // Render group label first
            $groupLabelData = $this->buildFieldElementAttributes('boolean-group-label', $element, '', '', []);
            $groupLabelData['type'] = $baseType; // For CSS class
            $return .= View::make($groupLabelData, $this->flick)->render();

            foreach ($element['attributes']['options'] as $optionValue => $optionLabel) {
                $booleanElement = [
                    'type' => $baseType,
                    'name' => $baseType === 'checkbox' ? $baseName.'[]' : $baseName,
                    'label' => $optionLabel,
                    'id' => $baseName,
                    'value' => (string) $optionValue,
                    'attributes' => $isInline ? ['inline' => true] : [],
                ];

                if (! empty($element['attributes']['rules'])) {
                    $booleanElement['attributes']['rules'] = $element['attributes']['rules'];
                }

                $return .= $this->boolean($baseType, $booleanElement, '', '', []);
            }
        } finally {
            $this->suppressFieldRegistration = false;
        }

        return $return;
    }

    // create a form with a string
    public function createFormFromString(string $string, array|string $options): string
    {
        if (is_string($options)) {
            if (strtolower($options) === 'get') {
                $options = [];
                $options['method'] = 'GET';
            } else {
                $optionsString = $options;
                $options = [];
                $options['string'] = $optionsString;
            }
        }

        $options['id'] = ! empty($options['id']) ? $options['id'] : $this->flick->config('id');
        $options['action'] = ! empty($options['action']) ? $options['action'] : '/';
        $options['method'] = ! empty($options['method']) ? $options['method'] : 'POST';
        $options['button'] = ! empty($options['button']) ? $options['button'] : 'Submit';

        $result = $this->explodeFormString($string);

        $elements = array_filter(array_map([$this, 'parseFormElement'], $result));

        $return = $this->open($options['action'], $options['method'], $options);

        foreach ($elements as $element) {
            $type = $element['type'];

            // Checkbox and radio groups with options
            if (in_array($type, ['checkbox', 'checkboxInline', 'radio', 'radioInline'])) {
                if (! empty($element['attributes']['options'])) {
                    $return .= $this->buildBooleanGroup($type, $element);
                } else {
                    // Single checkbox/radio
                    $baseType = str_contains($type, 'checkbox') ? 'checkbox' : 'radio';
                    $return .= $this->boolean($baseType, $element, '', '', []);
                }
            } elseif ($type === 'select' || $type === 'selectMultiple') {
                if ($element['type'] == 'selectMultiple') {
                    $return .= $this->selectMultiple($element, '', '', []);
                } else {
                    $return .= $this->select($element, '', '', []);
                }
            } elseif ($element['type'] == 'textarea') {
                $return .= $this->textarea($element, '', '', []);
            } else {
                $return .= $this->input($element['type'], $element, '', '', []);
            }
        }

        $return .= $this->submit($options['button'], []);
        $return .= $this->close();

        // Auto-include client-side validation if Pro package is available and validation rules exist
        if ($this->flick->hasProPackage() && $this->flick->hasValidationRules() && $this->flick->hasService('validation')) {
            $return .= $this->flick->validation->scripts($this->flick->getFields());
        }

        return $return;
    }

    // build a form with an array
    public function fastForm(mixed $data, array|string $overrides = []): string
    {
        if (is_array($data)) {
            $array = $data;
        } else {
            $array = $this->loadFormFromFile($data);
        }

        $array = $this->applyFormOverrides($array, $overrides);

        $action = $array['action'] ?? '/';
        $method = $array['method'] ?? 'POST';
        $attributes = $array['attributes'] ?? [];

        $return = $this->open($action, $method, $attributes);

        foreach ($array['fields'] as $key => $element) {
            if (empty($element['name'])) {
                if (is_string($key)) {
                    $element['name'] = $key;
                } else {
                    $string = $element['label'] ?? '';

                    throw FlickException::missingFieldKey($string);
                }
            }

            if (is_string($key) && str_starts_with($key, 'fieldset')) {
                $return .= $this->support->return('<fieldset>');
                if (isset($element['legend'])) {
                    $return .= $this->support->return('<legend>'.htmlspecialchars((string) $element['legend']).'</legend>');
                }
                if (isset($element['label'])) {
                    // raw, like every other label — see the Labels note above
                    $return .= $this->support->return('<label>'.$element['label'].'</label>');
                }
                foreach ($element['fields'] as $fsElement) {
                    $return .= $this->buildFastFormElement($fsElement);
                }
                $return .= $this->support->return('</fieldset>');
            } else {
                $return .= $this->buildFastFormElement($element);
            }
        }

        if (array_key_exists('button', $array)) {
            $label = $array['button']['text'] ?? 'Submit';
            $attributes = $array['button']['attributes'] ?? [];
            $return .= $this->submit($label, $attributes);
        } else {
            $return .= $this->flick->submit();
        }

        $return .= $this->close();

        // Auto-include client-side validation if Pro package is available and validation rules exist
        if ($this->flick->hasProPackage() && $this->flick->hasValidationRules() && $this->flick->hasService('validation')) {
            $return .= $this->flick->validation->scripts($this->flick->getFields());
        }

        return $return;
    }

    // process and validate a form with an array
    public function fastPost(mixed $value): array|string|null
    {
        $return = [];

        if ($this->flick->submitted()) {
            $array = $this->getFastFormValueOrLoadFastFormFileFromDisk($value);

            foreach ($array['fields'] as $key => $element) {
                // mirrors fastForm()'s fieldset detection, so 'fieldset-select'
                // and friends are unwrapped on the way back in too
                if (is_string($key) && str_starts_with($key, 'fieldset')) {
                    foreach ($element['fields'] as $fsKey => $fsElement) {
                        $parsed = $this->parseSubmittedFastFormElement($fsElement, $fsKey);
                        $return = array_replace($return, $parsed);
                    }
                } else {
                    $parsed = $this->parseSubmittedFastFormElement($element, $key);
                    $return = array_replace($return, $parsed);
                }
            }
        }

        return $return;
    }

    /**
     * Lift 'rules', 'options' and 'messages' out of an element's 'attributes'
     * bag to the top level, where the documented array syntax puts them and
     * where both the renderer and the submit parser look.
     *
     * buildFastFormElement() copies all three DOWN into attributes for the
     * field builders, so an element that nested them there rendered correctly
     * and then parsed as if it had declared nothing:
     *
     * - nested 'rules' reached the field registry, which drives the CLIENT
     *   side validator, while fastPost() applied no server rules at all - the
     *   validation failed OPEN.
     * - nested 'options' rendered a checkbox group named colors[] while the
     *   parser looked up the scalar key 'colors', so the array tripped the
     *   array-on-a-scalar-field guard instead of validating per element.
     *
     * A top-level key wins; the nested copy stays where it is for the
     * builders that read it from there (buildBooleanGroup).
     */
    private static function liftNestedFastFormKeys(array $element): array
    {
        foreach (['rules', 'options', 'messages'] as $key) {
            if (! isset($element[$key]) && isset($element['attributes'][$key])) {
                $element[$key] = $element['attributes'][$key];
            }
        }

        return $element;
    }

    // build field elements for FastForm
    private function buildFastFormElement($element)
    {
        $element = self::liftNestedFastFormKeys($element);

        if (empty($element['type'])) {
            $element['type'] = 'text';
        }

        $name = $element['name'] ?? '';
        $label = $element['label'] ?? '';
        $value = $element['value'] ?? '';
        $attributes = $element['attributes'] ?? [];

        if ($this->flick->submitted() && empty($this->request->files())) {
            $key = str_replace(' ', '_', $name);
            $requestValue = $this->request->post($key) ?? $this->request->query($key) ?? '';

            // a multi-value submission (checkbox group, selectMultiple) arrives
            // as an array; its checked/selected state is rebuilt downstream, so
            // only a scalar can repopulate the value here
            $value = is_string($requestValue) ? $requestValue : '';
        }

        if (isset($element['options'])) {
            $attributes['options'] = $element['options'];
        }

        if (isset($element['rules'])) {
            $attributes['rules'] = $element['rules'];
        }

        if (isset($element['messages'])) {
            $attributes['messages'] = $element['messages'];
        }

        // a checkbox/radio carrying an options list is a group; route it through
        // the group builder like the string syntax does — the single-element
        // method would silently drop the options
        if (! empty($attributes['options'])
            && in_array($element['type'], ['checkbox', 'checkboxInline', 'radio', 'radioInline'], true)) {
            return $this->buildBooleanGroup($element['type'], [
                'type' => $element['type'],
                'name' => $name,
                'label' => $label,
                'attributes' => $attributes,
            ]);
        }

        if ($element['type'] == 'hidden') {
            return $this->flick->hidden($name, $value);
        }

        // call the element's method dynamically
        return $this->flick->{$element['type']}($name, $label, $value, $attributes);
    }

    // get the value (array or string) for FastForm/FastPost
    private function getFastFormValueOrLoadFastFormFileFromDisk(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return $this->loadFormFromFile($value);
    }

    // handle each form field after it's been submitted
    private function parseSubmittedFastFormElement($element, int|string|null $key = null): array
    {
        // read rules/options/messages from the same place rendering does
        $element = self::liftNestedFastFormKeys($element);

        // resolve the name the same way fastForm() does when rendering: an explicit
        // 'name' wins, then the array key. The label is only a last resort, for a
        // fieldset child that declared neither.
        $name = $element['name']
            ?? (is_string($key) ? $key : null)
            ?? self::fieldNameFromLabel($element['label'] ?? '');
        $type = $element['type'] ?? 'text';
        $rules = $element['rules'] ?? [];
        $messages = $element['messages'] ?? [];
        $return = [];

        if (in_array($type, ['file', 'files'])) {
            if (! isset($this->flick->upload)) {
                throw FlickException::serviceIsNotAvailable('Upload');
            }

            $return[$name] = $this->flick->upload->file(rtrim($name, '[]'), $rules, $messages);
        } else {
            // a selectMultiple (renders as name[]) and a checkbox group submit
            // arrays; hand the [] marker to the validator so their rules apply
            // per element instead of treating the array as a scalar
            $isMultiValue = str_ends_with($name, '[]')
                || $type === 'selectMultiple'
                || (in_array($type, ['checkbox', 'checkboxInline'], true) && ! empty($element['options']));

            $lookupName = ! str_ends_with($name, '[]') && $isMultiValue ? $name.'[]' : $name;

            $return[$name] = $this->flick->validate->input($lookupName, $rules, $messages);
        }

        return $return;
    }

    // FORM BUILDER HELPERS ---------------------------------------------------

    /**
     * Merge create()'s second argument over a form definition array.
     *
     * The string syntax takes these overrides as a flat list ('action', 'method',
     * 'id', 'button', plus any raw attribute). A definition array nests them, so
     * each one is routed to the place the array keeps it. Anything not recognised
     * is treated as a form attribute, matching the string syntax.
     *
     * @param  array  $array  the form definition
     * @param  array|string  $overrides  create()'s $attributes argument
     */
    private function applyFormOverrides(array $array, array|string $overrides): array
    {
        if ($overrides === [] || $overrides === '') {
            return $array;
        }

        // the string syntax accepts 'GET' as a shorthand, or a raw attribute string
        if (is_string($overrides)) {
            if (strtolower($overrides) === 'get') {
                $array['method'] = 'GET';

                return $array;
            }

            $array['attributes']['string'] = $overrides;

            return $array;
        }

        foreach ($overrides as $key => $value) {
            match ($key) {
                'action', 'method' => $array[$key] = $value,
                'id' => $array['attributes']['id'] = $value,
                // a plain string is the button's label; an array is merged so a
                // caller can override the text and leave its attributes alone
                'button' => $array['button'] = is_array($value)
                    ? array_replace($array['button'] ?? [], $value)
                    : array_replace($array['button'] ?? [], ['text' => $value]),
                default => $array['attributes'][$key] = $value,
            };
        }

        return $array;
    }

    /**
     * Turn a field's label into the name it is rendered and submitted under.
     *
     * Shared by the render path (create()) and the read path (request()) so the
     * two can never disagree about what a field is called.
     */
    public static function fieldNameFromLabel(string $label): string
    {
        return strtolower(str_replace(['-', ' '], ['_', '_'], trim($label)));
    }

    /**
     * Resolve the field name from one element of a form-definition string, e.g.
     * 'Password Confirmation|password', 'Username{gern}' or 'State|select(states)'.
     *
     * The default value and the element type are presentation concerns; neither
     * is part of the name. Braces are stripped first so a default containing a
     * pipe (e.g. 'Name{a|b}') can't be mistaken for a type separator.
     */
    public static function fieldNameFromDefinition(string $part): string
    {
        // drop a {default value} wherever it appears
        $part = preg_replace('/\{[^}]*\}/', '', $part);

        // drop the |type and anything it carries, e.g. |select(states::Pick one...)
        $part = explode('|', $part, 2)[0];

        return self::fieldNameFromLabel($part);
    }

    // explode a string used to create a form and get the elements
    private function explodeFormString(string $string): array
    {
        // One field-boundary splitter, shared with the validator, so create()
        // and request() can never disagree about where a field ends. Build's
        // own regex (/,\s*(?![^(]*\)|[^[]*])/) could not see past a comma in a
        // regex rule's character class and rendered a field from its tail.
        $parts = $this->flick->validate->prepareAFormStringForValidation($string);

        // Validate keeps an empty segment (', ,'); there is nothing to render
        // from one.
        return array_values(array_filter($parts, fn (string $part): bool => $part !== ''));
    }

    // get the dropdown options from a string that was used to create a form
    private function extractSelectMenuOptionsFromFormString(string $string): array
    {
        $dropdownOptions = [];

        // find the dropdown menu name within parentheses: e.g.; (login) or ('/login)
        $pattern = '/\(\/?(.*?)\)/';

        if (preg_match($pattern, $string, $matches)) {
            $content = $matches[1];

            if (empty($content)) {
                throw FlickException::missingDropdownOptionsInsideParentheses();
            }

            if (preg_match('/\[([^]]+)]/', $content, $optionMatch)) {
                // inline options are the same [value:Label, value:Label] grammar
                // the select() string attribute uses; the rules extractor used
                // to parse them and turned an option keyed 'r' into 'required'
                $options = $this->buildSelectMenuFromArrayString($optionMatch[1]);
                $remainingContent = preg_replace('/\[([^]]+)]/', '', $content);

                if (empty($options)) {
                    throw FlickException::missingDropdownOptionsInsideParentheses();
                }

                if (str_contains($remainingContent, '::')) {
                    $default = explode('::', $remainingContent, 2);
                    $default = trim($default[1]);
                    if (! empty($default)) {
                        array_unshift($dropdownOptions, $default);
                    }
                }

                $dropdownOptions += $options;
            } elseif (str_contains($content, '::')) {
                [$options, $default] = explode('::', $content, 2);

                if (empty(trim($options))) {
                    throw FlickException::missingDropdownOptions();
                }

                $dropdownOptions = $this->flick->dropdowns->load(trim($options));
                $dropdownOptions = array_merge(['' => trim($default)], $dropdownOptions);
            } elseif ($this->flick->config('assets')) {
                $path = $this->flick->config('assets').'/dropdowns/'.trim($content).'.php';

                if (is_file($path)) {
                    $dropdownOptions = $this->flick->dropdowns->loadAsset($path);
                } elseif (is_string($content)) {
                    $dropdownOptions = $this->flick->dropdowns->load($content);
                }
            } else {
                $dropdownOptions = $this->flick->dropdowns->load(trim($content));
            }
        }

        return [$dropdownOptions, preg_replace($pattern, '', $string)];
    }

    // get the validation rules from a string that was used to create a form
    private function extractValidationRulesFromString(string $string): array
    {
        $validationRules = [];

        // The [ ]-block split is the validator's, tracking bracket depth, so a
        // regex character class ('regex:/^[A-Z]\d$/') does not end the block at
        // its first ']'. A local /\[([^]]+)]/ did exactly that: the rule was
        // recorded truncated and the rest of the pattern stayed in the leftover
        // string, which becomes the label and the field name — so the rendered
        // field could not even match its own POST key.
        //
        // Only the FIRST block is rules — a second block carries per-rule
        // messages, which belong to request()'s validation, not to rendering
        // (they used to be merged straight into the rules). Every block is
        // stripped from the remaining string either way.
        //
        // The block contents are split by the validator's own rules splitter
        // too, so the rules reach getFields() as the same list of strings
        // validation parses ('between:18,65' stays one rule) instead of a
        // third, map-shaped grammar that read it as ['between' => 18, '65' => true].
        [$remaining, $blocks] = $this->flick->validate->splitDefinitionBlocks($string);

        if (isset($blocks[0])) {
            foreach ($this->flick->validate->convertRulesToArray($blocks[0]) as $rule) {
                if ($rule === '') {
                    continue;
                }

                // Aliases resolved by core's map, not by a local rule for 'r'
                // alone. That local rule was half a copy of the map: 'r' came
                // out of getFields() as 'required' while 'int' came out as
                // 'int', from the same grammar. Parameters ride along untouched.
                [$name, $parameters] = array_pad(explode(':', $rule, 2), 2, null);

                $canonical = Validate::canonicalRuleName($name);

                $validationRules[] = $parameters === null ? $canonical : $canonical.':'.$parameters;
            }
        }

        return [$validationRules, $remaining];
    }

    // parse a form element that was created from a string
    private function parseFormElement(string $element): array
    {
        [$dropdownOptions, $remainingString] = $this->extractSelectMenuOptionsFromFormString($element);
        [$validationRules, $finalString] = $this->extractValidationRulesFromString($remainingString);

        // check for a default field value contained within curly braces
        $pattern = '/\{([^}]+)}/';
        if (preg_match($pattern, $finalString, $matches)) {
            $value = trim($matches[1]);
            $finalString = preg_replace($pattern, '', $finalString);
        } else {
            $value = '';
        }

        [$label, $type] = explode('|', $finalString, 2) + [1 => 'text'];
        $label = trim($label);
        $type = trim($type);

        // a pipe with nothing usable after it (e.g. 'State|(states)', where the
        // options have just been stripped out) leaves the type empty; fall back
        // to the same default a missing pipe gets rather than looking for a
        // view file called '.view.php'
        if ($type === '') {
            $type = 'text';
        }

        $element = [
            'type' => $type,
            'label' => $label,
            // both the name and the id go through the same helper, so the
            // rendered field can never disagree with the name the validator
            // derives from the very same label
            'name' => self::fieldNameFromLabel($label),
            'id' => self::fieldNameFromLabel($label),
            'value' => $value,
            'attributes' => [],
        ];

        if (! empty($validationRules)) {
            $element['attributes']['rules'] = $validationRules;
        }

        if (! empty($dropdownOptions)) {
            $element['attributes']['options'] = $dropdownOptions;
        }

        return $element;
    }

    // GENERAL HELPERS --------------------------------------------------------

    public function addCsrfToken(): string
    {
        // One shared interpretation of csrf × generator — the checker resolves
        // through the same method, so what renders is always what validation
        // expects (see Flick::resolveCsrfTokenSource()).
        $policy = $this->flick->resolveCsrfTokenSource();

        if ($policy['source'] === 'framework') {
            return '<input type="hidden" name="_token" value="'.htmlspecialchars($policy['token'], ENT_QUOTES, 'UTF-8').'">'.PHP_EOL;
        }

        if ($policy['source'] === 'none') {
            return '';
        }

        // Use Flick's native CSRF with timeout
        $csrfConfig = $this->flick->config('csrf');
        $timeout = is_int($csrfConfig) ? $csrfConfig : 3600;

        if (! $this->flick->session->isActive()) {
            throw FlickException::missingCsrfSessionStart();
        }

        // Create a token when none exists or the current one has expired, and set
        // its expiry once at creation. Setting the expiry on every render would
        // make the timeout slide forward indefinitely (the token never expires).
        $expires = $this->flick->session->getValue('_token_expires');
        $expired = $expires !== null && time() > (int) $expires;

        if (! $this->flick->session->hasValue('_token') || $expired) {
            $this->flick->session->setValue('_token', $this->generateCsrfToken());
            $this->flick->session->setValue('_token_expires', time() + $timeout);
        }

        $token = $this->flick->session->getValue('_token');

        return '<input type="hidden" name="_token" value="'.htmlspecialchars($token, ENT_QUOTES, 'UTF-8').'">'.PHP_EOL;
    }

    /**
     * Add the element's data to the Flick $fields array.
     *
     * The sole owner of $flick->fields. buildFieldElementAttributes() used to
     * write a second, wider record mid-build, which this call then overwrote -
     * except for the submit button, which returns early here, so its wide
     * record survived and reached the client-side rules the validation service
     * emits from getFields().
     *
     * $attributes is the normalised attribute array, not the raw $element:
     * reading options off the element only found them when the caller happened
     * to pass an array field definition, so select('color', …, ['options' =>
     * …]) recorded none.
     */
    private function addElementDataToFieldsArray(array $data, array $attributes): void
    {
        if ($data['type'] == 'submit') {
            return;
        }

        // buildBooleanGroup() writes its group's single row itself; the label
        // and per-option renders inside it must not add or replace rows
        if ($this->suppressFieldRegistration) {
            return;
        }

        $this->flick->fields[$data['name']] = [
            'name' => $data['name'],
            'id' => $data['id'],
            'value' => $data['value'],
            'attributes' => is_string($data['attributes']) ? $data['attributes'] : '',
        ];

        if (isset($attributes['options'])) {
            $this->flick->fields[$data['name']]['options'] = $attributes['options'];
        }

        if (isset($data['rules'])) {
            $this->flick->fields[$data['name']]['rules'] = $data['rules'];
        }

        if (isset($data['messages'])) {
            $this->flick->fields[$data['name']]['messages'] = $data['messages'];
        }
    }

    // there's more than one way to build a dropdown...
    private function buildSelectMenuAttributesArray($attributes): array
    {
        if (is_array($attributes)) {
            if (array_key_exists('options', $attributes) && is_string($attributes['options'])) {
                $array = $this->loadSelectMenuFromFile($attributes['options']);
                $attributes['options'] = $array;
            } elseif (! array_key_exists('options', $attributes)) {
                $options = $attributes;
                $attributes = [];
                $attributes['options'] = $options;
            }
        } elseif (is_string($attributes)) {
            if (str_contains($attributes, '::')) {
                // we passed a default option, e.g.; 'Select a Thing...'
                $parts = explode('::', $attributes);
                $array = $this->loadSelectMenuFromFile($parts[0]);
                array_unshift($array, $parts[1]);
                $attributes = [];
                $attributes['options'] = $array;
            } elseif (str_starts_with($attributes, '[') && str_ends_with($attributes, ']')) {
                // string looks like this: [value:Label, value:Label],
                $dropdownName = str_replace(['[', ']'], ['', ''], $attributes);
                $attributes = [];
                $attributes['options'] = $this->buildSelectMenuFromArrayString($dropdownName);
            } else {
                $dropdownName = $attributes;
                $attributes = [];
                $attributes['options'] = $this->loadSelectMenuFromFile($dropdownName);
            }
        } elseif (is_string($attributes['options'])) {
            $array = $this->loadSelectMenuFromFile($attributes['options']);
            $attributes['options'] = $array;
        }

        if (! empty($attributes['default'])) {
            array_unshift($attributes['options'], $attributes['default']);
        }

        return $attributes;
    }

    // create dropdown options from a string: [value:label]
    private function buildSelectMenuFromArrayString($string): array
    {
        $string = str_replace(', ', ',', $string);
        $pairs = explode(',', $string);
        $result = [];

        foreach ($pairs as $pair) {
            [$key, $value] = explode(':', $pair);

            $result[$key] = $value;
        }

        return $result;
    }

    // build datalist <option> elements for autocomplete suggestions
    private function buildDatalistOptions(array $options, string $inputId): string
    {
        $datalistId = $inputId.'-datalist';
        $html = '<datalist id="'.htmlspecialchars($datalistId).'">'.PHP_EOL;

        foreach ($options as $value => $label) {
            if (is_int($value)) {
                // simple array: ['USA', 'Canada'] - value and label are the same
                $html .= '<option value="'.htmlspecialchars((string) $label).'">'.PHP_EOL;
            } else {
                // associative array: ['us' => 'USA'] - value is key, label is displayed
                $html .= '<option value="'.htmlspecialchars((string) $value).'">'.htmlspecialchars((string) $label).'</option>'.PHP_EOL;
            }
        }

        $html .= '</datalist>';

        return $html;
    }

    /**
     * Build one select menu <option>.
     *
     * $selectedValue is the already-resolved field value, so this no longer
     * re-scans POST and GET for itself - three near-duplicate comparisons
     * collapse into one. A client is free to post a bare scalar to a field
     * rendered as name[], so the value can't be handed to in_array() unchecked.
     */
    private function buildSelectMenuOptions($optionValue, $optionLabel, mixed $selectedValue): string
    {
        $return = '<option value="'.htmlspecialchars((string) $optionValue).'"';

        $selected = is_array($selectedValue)
            ? in_array($optionValue, $selectedValue)
            : $selectedValue == $optionValue;

        if ($selected) {
            $return .= ' selected';
        }

        $return .= '>'.htmlspecialchars((string) $optionLabel).'</option>'.PHP_EOL;

        return $return;
    }

    /**
     * @throws RandomException
     */
    public function generateCsrfToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    // make sure a file ends with '.php'
    public function ensurePhpExtension(string $filename): string
    {
        if (! str_ends_with($filename, '.php')) {
            return $filename.'.php';
        }

        return $filename;
    }

    private function isRequired(array $attributes): bool
    {
        if (isset($attributes['rules'])) {
            // The 'rules' attribute takes any of the three documented shapes -
            // map, list, or comma-separated string. Only the two array shapes
            // were read here, so a string fell through to in_array() and threw
            // before the field rendered. Split through the shared grammar
            // rather than exploding on ',': that keeps an argument list
            // attached to its rule, so 'in:required,other' stays one rule and
            // its argument can't be mistaken for the required rule.
            $rules = $this->flick->validate->convertRulesToArray($attributes['rules']);

            if (isset($rules['required']) || isset($rules['r'])) {
                return true;
            }

            // Strict: rules given as a list ('required', 'email') carry the rule
            // name as the value, but rules given as a map ('email' => true) carry
            // a boolean. A loose compare comparison matched 'required' against
            // that true and marked every ruled field required.
            if (in_array('required', $rules, true) || in_array('r', $rules, true)) {
                return true;
            }
        }

        return isset($attributes['required']) && $attributes['required'] !== false;
    }

    private function loadFormFromFile(string $form): array
    {
        $formName = str_replace('.php', '', trim($form, '/'));

        // the name is interpolated into the assets directory below, so it gets
        // the same identifier check the lang/ loaders apply
        Support::assertSafeLoaderName($formName);

        // Assets first, shipped second - the same direction dropdowns already
        // use. Throwing when an assets/forms directory existed but did not hold
        // this form meant that merely having one custom form made every SHIPPED
        // form unreachable.
        if ($this->flick->config('assets') && is_dir($this->flick->config('assets').'/forms')) {
            $path = $this->flick->config('assets').'/forms/'.$this->ensurePhpExtension($formName);

            if (is_file($path)) {
                return $this->flick->forms->loadAsset($path);
            }
        }

        return $this->flick->forms->load($formName);
    }

    private function loadSelectMenuFromFile(array|string $attributes): array
    {
        if (is_string($attributes)) {
            Support::assertSafeLoaderName($attributes);
        }

        if ($this->flick->config('assets') && is_dir($this->flick->config('assets').'/dropdowns')) {
            $path = $this->flick->config('assets').'/dropdowns/'.$attributes.'.php';
            if (is_file($path)) {
                return $this->flick->dropdowns->loadAsset($path);
            } elseif (is_string($attributes)) {
                return $this->flick->dropdowns->load($attributes);
            }
        } elseif (is_string($attributes)) {
            return $this->flick->dropdowns->load($attributes);
        }

        return [];
    }

    private function setViewFilePath(&$data): void
    {
        $data['templatePath'] = $this->flick->views->resolve($data['viewType']);
    }

    private function setViewFileType(&$data): void
    {
        $data['viewType'] = 'input';

        if (in_array($data['type'], ['checkbox', 'radio'])) {
            if (! empty($data['inline'])) {
                $data['viewType'] = 'boolean-inline';
            } else {
                $data['viewType'] = 'boolean';
            }
        } elseif (! in_array(
            $data['type'],
            [
                'color',
                'date',
                'datetime-local',
                'email',
                'month',
                'number',
                'password',
                'range',
                'search',
                'tel',
                'text',
                'time',
                'url',
                'week',
            ]
        )) {
            $data['viewType'] = $data['type'];
        }
    }

    // MESSAGING --------------------------------------------------------------

    public function message(string $type, string $message, string $heading = '', bool $escape = true): string
    {
        if (! in_array($type, ['error', 'info', 'success', 'warning'])) {
            throw FlickException::alertTypeIsNotAvailable($type);
        }

        $view = $this->flick->views->read($this->flick->views->resolve('alerts/'.$type));

        // Callers that assemble their own safe markup (e.g. the errors() summary list,
        // which escapes each item itself) pass $escape = false so the wrapper tags survive.
        $safeMessage = $escape ? htmlspecialchars($message) : $message;

        return $this->support->return(str_replace(['{{ message }}', '{{ heading }}'], [$safeMessage, htmlspecialchars($heading)], $view));
    }
}
