<?php

use Flick\Flick;

/*
 * The file field types: file, files, image, images, pdf, pdfs.
 *
 * Decided 2026-10-09 (Tim): a form definition can say what kind of upload a
 * field takes, using the upload service's own names, and a form holding any
 * of them is multipart without being told. Written from that decision and
 * guide/creating-forms.md, "File Uploads"; nothing here reads the upload
 * service, which is Pro's.
 */

beforeEach(function () {
    $this->form = new Flick([
        'csrf' => false,
        'echo' => false,
    ]);
});

// The one <input type="file"> tag in a rendered fragment.
function fileInputTag(string $html): string
{
    preg_match_all('/<input type="file"[^>]*>/', $html, $matches);

    expect($matches[0])->toHaveCount(1, 'expected exactly one file input in: '.$html);

    return $matches[0][0];
}

function formTag(string $html): string
{
    preg_match('/<form[^>]*>/', $html, $match);

    return $match[0] ?? '';
}

// FIELD METHODS ----------------------------------------------------------

it('renders an image field as a file input limited to images', function () {
    $tag = fileInputTag($this->form->image('photo', 'Photo'));

    expect($tag)
        ->toContain('name="photo"')
        ->toContain('accept="image/*"')
        ->not->toContain('multiple');
});

it('renders a pdf field as a file input limited to PDFs', function () {
    $tag = fileInputTag($this->form->pdf('resume', 'Resume'));

    expect($tag)
        ->toContain('name="resume"')
        ->toContain('accept="application/pdf"')
        ->not->toContain('multiple');
});

it('renders an images field that takes several images', function () {
    $tag = fileInputTag($this->form->images('photos', 'Photos'));

    expect($tag)
        ->toContain('name="photos[]"')
        ->toContain('id="photos"')
        ->toContain('accept="image/*"')
        ->toContain(' multiple');
});

it('renders a pdfs field that takes several PDFs', function () {
    $tag = fileInputTag($this->form->pdfs('documents', 'Documents'));

    expect($tag)
        ->toContain('name="documents[]"')
        ->toContain('accept="application/pdf"')
        ->toContain(' multiple');
});

it('does not add a second [] to a name that already ends with one', function () {
    $tag = fileInputTag($this->form->images('photos[]', 'Photos'));

    expect($tag)->toContain('name="photos[]"');
});

it('leaves a plain file field without an accept hint', function () {
    expect(fileInputTag($this->form->file('attachment')))->not->toContain('accept=');
});

it('keeps the accept the developer set on an image field', function () {
    $tag = fileInputTag($this->form->image('photo', 'Photo', '', ['accept' => 'image/png']));

    expect($tag)
        ->toContain('accept="image/png"')
        ->not->toContain('image/*');
});

it('writes accept once when the developer set it in a raw attribute string', function () {
    $tag = fileInputTag($this->form->image('photo', 'Photo', '', 'accept=".png"'));

    expect(substr_count($tag, 'accept='))->toBe(1)
        ->and($tag)->toContain('accept=".png"');
});

it('takes string attributes on every file field method', function (string $method) {
    $tag = fileInputTag($this->form->{$method}('upload', 'Upload', '', 'data-max="3"'));

    expect($tag)->toContain('data-max="3"');
})->with(['file', 'files', 'image', 'images', 'pdf', 'pdfs']);

// STRING SYNTAX ----------------------------------------------------------

it('renders each file type from a form string', function (string $type, string $name, ?string $accept, bool $multiple) {
    $tag = fileInputTag($this->form->create('Upload|'.$type));

    expect($tag)->toContain('name="'.$name.'"');

    $accept === null
        ? expect($tag)->not->toContain('accept=')
        : expect($tag)->toContain('accept="'.$accept.'"');

    $multiple
        ? expect($tag)->toContain(' multiple')
        : expect($tag)->not->toContain('multiple');
})->with([
    'file' => ['file', 'upload', null, false],
    'files' => ['files', 'upload[]', null, true],
    'image' => ['image', 'upload', 'image/*', false],
    'images' => ['images', 'upload[]', 'image/*', true],
    'pdf' => ['pdf', 'upload', 'application/pdf', false],
    'pdfs' => ['pdfs', 'upload[]', 'application/pdf', true],
]);

it('marks a required image field from a form string as required', function () {
    $tag = fileInputTag($this->form->create('Photo|image[required,width:400]'));

    expect($tag)->toContain(' required');
});

it('records a file field\'s rules whole, for the client-side validator', function () {
    $this->form->create('Name[r], Doc|file[r,mime:application/pdf,text/plain,maxFileSize:5MB]');

    $fields = $this->form->getFields();

    expect($fields['name']['rules'])->toBe(['required'])
        ->and($fields['doc']['rules'])->toBe(['required', 'mime:application/pdf,text/plain', 'maxFileSize:5MB']);
});

it('records a file field\'s rules whole however the definition is spaced or defaulted', function (string $definition) {
    $this->form->create($definition);

    expect($this->form->getFields()['doc']['rules'])->toBe(['mime:application/pdf,text/plain']);
})->with([
    'spaces around the pipe' => ['Doc | file[mime:application/pdf,text/plain]'],
    'a default after the type' => ['Doc|file{x}[mime:application/pdf,text/plain]'],
]);

// ARRAY SYNTAX -----------------------------------------------------------

it('renders each file type from a form array', function (string $type, string $name, ?string $accept) {
    $html = $this->form->create(['fields' => ['upload' => ['label' => 'Upload', 'type' => $type]]]);
    $tag = fileInputTag($html);

    expect($tag)->toContain('name="'.$name.'"');

    $accept === null
        ? expect($tag)->not->toContain('accept=')
        : expect($tag)->toContain('accept="'.$accept.'"');
})->with([
    'file' => ['file', 'upload', null],
    'files' => ['files', 'upload[]', null],
    'image' => ['image', 'upload', 'image/*'],
    'images' => ['images', 'upload[]', 'image/*'],
    'pdf' => ['pdf', 'upload', 'application/pdf'],
    'pdfs' => ['pdfs', 'upload[]', 'application/pdf'],
]);

// ENCTYPE ----------------------------------------------------------------

it('makes a string form multipart when it holds a file field', function (string $type) {
    $html = $this->form->create('Name, Upload|'.$type);

    expect(formTag($html))->toContain('enctype="multipart/form-data"');
})->with(['file', 'files', 'image', 'images', 'pdf', 'pdfs']);

it('leaves a string form without file fields alone', function () {
    $html = $this->form->create('Name, Email|email');

    expect(formTag($html))->not->toContain('enctype');
});

it('makes an array form multipart when it holds a file field', function () {
    $html = $this->form->create(['fields' => [
        'name' => ['label' => 'Name'],
        'photo' => ['label' => 'Photo', 'type' => 'image'],
    ]]);

    expect(formTag($html))->toContain('enctype="multipart/form-data"');
});

it('makes an array form multipart when the file field sits in a fieldset', function () {
    $html = $this->form->create(['fields' => [
        'fieldset-documents' => [
            'legend' => 'Documents',
            'fields' => [
                ['name' => 'resume', 'label' => 'Resume', 'type' => 'pdf'],
            ],
        ],
    ]]);

    expect(formTag($html))->toContain('enctype="multipart/form-data"');
});

it('leaves an array form without file fields alone', function () {
    $html = $this->form->create(['fields' => ['name' => ['label' => 'Name']]]);

    expect(formTag($html))->not->toContain('enctype');
});

it('writes the enctype once when the developer also asked for it', function (array|string $attributes) {
    $html = $this->form->create('Name, Photo|image', $attributes);

    expect(substr_count(formTag($html), 'enctype='))->toBe(1);
})->with([
    'multipart flag' => [['multipart' => true]],
    'enctype attribute' => [['enctype' => 'multipart/form-data']],
    'raw attribute string' => ['enctype="multipart/form-data"'],
]);

it('does not make a form multipart when the flag is false', function () {
    $html = $this->form->create('Name, Email|email', ['multipart' => false]);

    expect(formTag($html))->not->toContain('enctype');
});

it('makes the createAndValidate form multipart when it holds a file field', function () {
    $this->form->createAndValidate('Name, Photo|image');

    expect(formTag($this->form->renderValidated('Name, Photo|image')))
        ->toContain('enctype="multipart/form-data"');
});
