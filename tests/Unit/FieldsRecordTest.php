<?php

use Flick\Flick;
use Flick\Http\ArrayRequest;

/*
|--------------------------------------------------------------------------
| The shape of Flick::getFields()
|--------------------------------------------------------------------------
|
| getFields() is a published API - the validation service is documented as
| `$form->validation->scripts($form->getFields())` - so every entry has to
| have one shape, and the submit button must not be in there at all. It was
| written twice: once mid-build with a wide record, then again at the end with
| a narrow one. The submit button returned early from the second write, so its
| wide record survived and reached the emitted client-side rules.
|
*/

function fieldsForm(array $post = []): Flick
{
    return new Flick([
        'request' => new ArrayRequest([
            'post' => $post,
            'server' => $post === []
                ? ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']
                : ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'],
        ]),
        'echo' => false,
        'csrf' => false,
    ]);
}

it('gives every field the same record shape and leaves the submit button out', function () {
    $form = fieldsForm();
    $form->create('Name, Email|email[r]');

    $fields = $form->getFields();

    expect($fields)->not->toHaveKey('submit')
        ->and(array_keys($fields))->toBe(['name', 'email']);

    foreach ($fields as $key => $field) {
        // the same four keys first, every time, then only the optional extras
        expect(array_slice(array_keys($field), 0, 4))->toBe(['name', 'id', 'value', 'attributes'], "field {$key}")
            ->and(array_diff(array_keys($field), ['name', 'id', 'value', 'attributes', 'options', 'rules', 'messages']))
            ->toBe([], "field {$key} carries an unexpected key");
    }
});

it('keeps a select menu options entry in the record', function () {
    $form = fieldsForm();
    $form->select('color', 'Color', '', ['options' => ['r' => 'Red', 'b' => 'Blue']]);

    expect($form->getFields()['color'])->toHaveKey('options')
        ->and($form->getFields()['color']['options'])->toBe(['r' => 'Red', 'b' => 'Blue']);
});

it('records one row for a checkbox group, keyed by base name with the selection as value', function () {
    $form = fieldsForm(['_id' => 'myForm', 'colors' => ['green']]);
    $form->create('Colors|checkbox([red:Red, green:Green])[required]');

    $fields = $form->getFields();

    expect($fields)->toHaveKey('colors')
        ->and($fields)->not->toHaveKey('colors[]')
        ->and($fields['colors']['name'])->toBe('colors[]')
        ->and($fields['colors']['id'])->toBe('colors')
        ->and($fields['colors']['value'])->toBe(['green'])
        ->and($fields['colors']['options'])->toBe(['red' => 'Red', 'green' => 'Green'])
        ->and($fields['colors']['rules'])->toBe(['required']);
});

it('records one row for a radio group and keeps its options', function () {
    $form = fieldsForm();
    $form->create('Gender|radio([m:Male, f:Female])');

    $fields = $form->getFields();

    expect($fields['gender']['name'])->toBe('gender')
        ->and($fields['gender']['id'])->toBe('gender')
        ->and($fields['gender']['value'])->toBe('')
        ->and($fields['gender']['options'])->toBe(['m' => 'Male', 'f' => 'Female']);
});

it('records DSL rules as the same list of strings the validator parses', function () {
    $form = fieldsForm();
    $form->create('Age[between:18,65], Name[r, min:2]');

    $fields = $form->getFields();

    // argument commas stay attached to their rule; 'r' is spelled out
    expect($fields['age']['rules'])->toBe(['between:18,65'])
        ->and($fields['name']['rules'])->toBe(['required', 'min:2']);
});

/*
 * Audit-081702 P14-A leftover. Build spelled out the 'r' alias with its own
 * preg_match, and left every other alias alone - half a copy of core's rule map
 * living next to the real one. 'r' therefore came out of getFields() as
 * 'required' while 'int' came out as 'int', from one grammar, for no reason.
 * Both now go through Validate::canonicalRuleName(), the same resolver the
 * client-side adapters use.
 */
it('records every rule alias under its canonical name, not just r', function () {
    $form = fieldsForm();
    $form->create('Zip[int], Name[r], Age[int, min:2]');

    $fields = $form->getFields();

    expect($fields['zip']['rules'])->toBe(['integer'])
        ->and($fields['name']['rules'])->toBe(['required'])
        // the parameter has to survive the rename
        ->and($fields['age']['rules'])->toBe(['integer', 'min:2']);
});

it('leaves a rule core does not know alone in the record', function () {
    $form = fieldsForm();
    $form->create('Thing[somethingCustom, requiredIf:other]');

    expect($form->getFields()['thing']['rules'])->toBe(['somethingCustom', 'requiredIf:other']);
});

it('keeps a second bracket block (messages) out of the recorded rules', function () {
    $form = fieldsForm();
    $form->create('Name[required][required: Please enter your name]');

    expect($form->getFields()['name']['rules'])->toBe(['required']);
});

/*
 * Audit-081702 F02-A. Build extracted the rules block with /\[([^]]+)]/, which
 * stops at the FIRST ']'. Validate has tracked bracket depth for this exact
 * case since splitDefinitionBlocks() was written - its comment says so - and the
 * creating-forms FAQ lists regex among the rules create() accepts.
 *
 * So a regex character class truncated the rule AND left the rest of the
 * pattern in the leftover string, which is what becomes the label and the field
 * name. Validate, reading the same string, kept the whole rule: the fields
 * registry and the validator disagreed about what the developer wrote.
 */
it('records a rule containing a bracket character class in full', function () {
    $form = fieldsForm();
    $html = $form->create('Postal Code[regex:/^[A-Z]\d[A-Z]\s\d[A-Z]\d$/]');

    $fields = $form->getFields();

    // The rule survives whole, and none of the pattern leaks into the name or
    // the rendered label. The name mattered most: it used to come out as
    // 'postal_code\d\s\d\d$/]', which no POST key can ever match.
    expect($fields['postal_code']['rules'])->toBe(['regex:/^[A-Z]\d[A-Z]\s\d[A-Z]\d$/'])
        ->and($fields['postal_code']['name'])->toBe('postal_code')
        ->and($fields['postal_code']['id'])->toBe('postal_code')
        ->and($html)->toContain('Postal Code')
        ->and($html)->not->toContain('[A-Z]');
});

it('records the same rules the validator parses from a bracketed pattern', function () {
    $definition = 'Postal Code[regex:/^[A-Z]\d[A-Z]$/]';

    $form = fieldsForm();
    $form->create($definition);

    // Validate reads the identical string through its own depth-aware splitter.
    $viaValidate = (new ReflectionMethod($form->validate, 'parseValidationPart'))
        ->invoke($form->validate, $definition);

    expect($form->getFields()['postal_code']['rules'])->toBe($viaValidate[2]);
});

it('still keeps a bracketed pattern out of a following messages block', function () {
    $form = fieldsForm();
    $html = $form->create('Postal Code[regex:/^[A-Z]\d$/][regex: Bad postcode]');

    expect($form->getFields()['postal_code']['rules'])->toBe(['regex:/^[A-Z]\d$/'])
        ->and($form->getFields()['postal_code']['name'])->toBe('postal_code')
        ->and($html)->toContain('Postal Code')
        ->and($html)->not->toContain('Bad postcode');
});

it('keeps inline select options intact, including an option keyed r', function () {
    $form = fieldsForm();
    $form->create('Color|select([r:Red, g:Green])');

    expect($form->getFields()['color']['options'])->toBe(['r' => 'Red', 'g' => 'Green']);
});

it('keeps the group record shape for array-defined checkbox groups', function () {
    $form = fieldsForm();
    $form->create([
        'fields' => [
            'colors' => [
                'type' => 'checkbox',
                'label' => 'Colors',
                'options' => ['red' => 'Red', 'green' => 'Green'],
            ],
        ],
    ]);

    $fields = $form->getFields();

    expect(array_keys($fields))->toBe(['colors'])
        ->and($fields['colors']['name'])->toBe('colors[]')
        ->and($fields['colors']['options'])->toBe(['red' => 'Red', 'green' => 'Green']);
});
