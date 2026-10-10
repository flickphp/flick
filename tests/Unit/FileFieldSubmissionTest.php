<?php

use Flick\Flick;
use Flick\Http\ArrayRequest;
use Flick\Service\Registry;
use Flick\Service\ServiceProvider;
use Flick\Support\FlickException;
use Flick\Support\Support;

/*
 * Where a submitted file field goes.
 *
 * Decided 2026-10-09 (Tim): a field typed file, image or pdf (or a plural)
 * is handed to the upload service method of the same name, with its rules,
 * because of the type it declared - not because a file happened to arrive.
 * Fields with no file type keep the older behaviour.
 *
 * The upload service is Pro's and core cannot load it, so a stand-in with
 * the same three methods takes its place. What core owns, and what these
 * tests pin, is the hand-off: which method, which field name, which rules,
 * and that the handler's result is what the form returns. Saving a file is
 * covered where the real service lives, in Pro's suite.
 */
class FileFieldTestUpload
{
    /** @var list<array{handler: string, name: string, rules: array, messages: array}> */
    public static array $calls = [];

    public function file(string $name, array $rules = [], array $messages = []): object
    {
        return self::handle('file', $name, $rules, $messages);
    }

    public function image(string $name, array $rules = [], array $messages = []): object
    {
        return self::handle('image', $name, $rules, $messages);
    }

    public function pdf(string $name, array $rules = [], array $messages = []): object
    {
        return self::handle('pdf', $name, $rules, $messages);
    }

    private static function handle(string $handler, string $name, array $rules, array $messages): object
    {
        self::$calls[] = compact('handler', 'name', 'rules', 'messages');

        return (object) ['savedBy' => $handler];
    }
}

class FileFieldTestUploadProvider implements ServiceProvider
{
    public function register(mixed $container): void
    {
        $container->set('upload', fn () => new FileFieldTestUpload);
    }

    public function setConfig(array $config): void {}

    public function setSupport(Support $support): void {}
}

// A submitted form: $post alongside the hidden _id, and $_FILES-shaped $files.
function submittedFileForm(array $post = [], array $files = []): Flick
{
    return new Flick([
        'csrf' => false,
        'echo' => false,
        'request' => ArrayRequest::createMultipart(['_id' => 'myForm'] + $post, $files),
    ]);
}

function postedFile(string $filename = 'photo.jpg', string $type = 'image/jpeg'): array
{
    return ['name' => $filename, 'type' => $type, 'tmp_name' => '/tmp/phpUPLOAD', 'error' => UPLOAD_ERR_OK, 'size' => 3780171];
}

function postedFiles(): array
{
    return [
        'name' => ['one.jpg', 'two.jpg'],
        'type' => ['image/jpeg', 'image/jpeg'],
        'tmp_name' => ['/tmp/phpONE', '/tmp/phpTWO'],
        'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
        'size' => [1200000, 2400000],
    ];
}

beforeEach(function () {
    FileFieldTestUpload::$calls = [];
    Registry::add('upload', FileFieldTestUploadProvider::class);
});

afterEach(function () {
    Registry::remove('upload');
    FileFieldTestUpload::$calls = [];
});

// STRING SYNTAX ----------------------------------------------------------

it('hands a typed file field to the upload method of the same name', function (string $type, string $handler, array $file) {
    $form = submittedFileForm(['name' => 'Tim'], ['upload' => $file]);

    $data = $form->request('Name, Upload|'.$type.'[required]');

    expect(FileFieldTestUpload::$calls)->toBe([
        ['handler' => $handler, 'name' => 'upload', 'rules' => ['required'], 'messages' => []],
    ])
        ->and($data['name'])->toBe('Tim')
        ->and($data['upload'])->toEqual((object) ['savedBy' => $handler]);
})->with([
    'file' => ['file', 'file', postedFile('notes.txt', 'text/plain')],
    'files' => ['files', 'file', postedFiles()],
    'image' => ['image', 'image', postedFile()],
    'images' => ['images', 'image', postedFiles()],
    'pdf' => ['pdf', 'pdf', postedFile('resume.pdf', 'application/pdf')],
    'pdfs' => ['pdfs', 'pdf', postedFiles()],
]);

it('passes each upload rule to the handler whole', function (string $block, array $expected) {
    $form = submittedFileForm([], ['photo' => postedFile()]);

    $form->request('Name, Photo|image['.$block.']');

    expect(FileFieldTestUpload::$calls[0]['rules'])->toBe($expected);
})->with([
    'plain rules' => ['required,width:400,name:avatar', ['required', 'width:400', 'name:avatar']],
    'a list of mime types' => ['mime:application/pdf,text/plain,maxFileSize:5MB', ['mime:application/pdf,text/plain', 'maxFileSize:5MB']],
    'a list of conversions' => ['conversions:thumbnail,medium,required', ['conversions:thumbnail,medium', 'required']],
    'a two-part name' => ['name:hash,16,grayscale', ['name:hash,16', 'grayscale']],
    'a list of thumbnail sizes' => ['thumbnails:150x150,300x300,width:800', ['thumbnails:150x150,300x300', 'width:800']],
    'the r alias' => ['r,mimeTypes:image/png,image/webp', ['required', 'mimeTypes:image/png,image/webp']],
    'flags in a row' => ['width:400,sepia,invert,preserveOriginal', ['width:400', 'sepia', 'invert', 'preserveOriginal']],
    'a word the service may add later, after a flag' => ['required,optimize,width:400', ['required', 'optimize', 'width:400']],
    'spaces after commas' => ['required, mime:image/png, image/webp, width:400', ['required', 'mime:image/png,image/webp', 'width:400']],
    'a stray comma' => ['required,,width:400,', ['required', 'width:400']],
]);

it('reads the type when there are spaces around the pipe', function () {
    $form = submittedFileForm([], ['photo' => postedFile()]);

    $form->request('Name, Photo | image[width:400]');

    expect(FileFieldTestUpload::$calls)->toBe([
        ['handler' => 'image', 'name' => 'photo', 'rules' => ['width:400'], 'messages' => []],
    ]);
});

it('passes a file field its messages', function () {
    $form = submittedFileForm([], ['photo' => postedFile()]);

    $form->request('Name, Photo|image[required][required:Please add a photo]');

    expect(FileFieldTestUpload::$calls[0]['messages'])->toBe(['required' => 'Please add a photo']);
});

it('sends an empty typed file field to the upload handler, not the text validator', function () {
    $form = submittedFileForm(['name' => 'Tim']);

    $form->request('Name, Photo|file[mime:image/jpeg]');

    expect(FileFieldTestUpload::$calls)->toBe([
        ['handler' => 'file', 'name' => 'photo', 'rules' => ['mime:image/jpeg'], 'messages' => []],
    ])
        ->and($form->getErrors())->toBe([])
        ->and($form->ok())->toBeTrue();
});

it('routes a single file field written on its own', function () {
    $form = submittedFileForm([], ['photo' => postedFile()]);

    $data = $form->request('Photo|image[width:400]');

    expect(FileFieldTestUpload::$calls)->toBe([
        ['handler' => 'image', 'name' => 'photo', 'rules' => ['width:400'], 'messages' => []],
    ])
        ->and($data)->toEqual(['photo' => (object) ['savedBy' => 'image']]);
});

it('gives createAndValidate the saved file in its data', function () {
    $form = submittedFileForm(['name' => 'Tim'], ['photo' => postedFile()]);
    $received = null;

    $data = $form->createAndValidate(
        'Name[required], Photo|image[required,width:400,name:avatar]',
        onSuccess: function ($data) use (&$received) {
            $received = $data;
        },
    );

    expect($data)->toEqual(['name' => 'Tim', 'photo' => (object) ['savedBy' => 'image']])
        ->and($received)->toEqual($data)
        ->and(FileFieldTestUpload::$calls[0]['rules'])->toBe(['required', 'width:400', 'name:avatar']);
});

// FIELDS WITH NO FILE TYPE -----------------------------------------------

it('still sends an untyped field to file() when a file arrives under its name', function () {
    $form = submittedFileForm(['name' => 'Tim'], ['attachment' => postedFile('notes.txt', 'text/plain')]);

    $form->request('Name, Attachment[mime:text/plain]');

    expect(FileFieldTestUpload::$calls)->toBe([
        ['handler' => 'file', 'name' => 'attachment', 'rules' => ['mime:text/plain'], 'messages' => []],
    ]);
});

it('never sends a text field to the upload service', function () {
    $form = submittedFileForm(['name' => 'Tim', 'email' => 'tim@example.com']);

    $data = $form->request('Name[required], Email|email[email]');

    expect(FileFieldTestUpload::$calls)->toBe([])
        ->and($data)->toBe(['name' => 'Tim', 'email' => 'tim@example.com']);
});

// ARRAY SYNTAX -----------------------------------------------------------

it('hands a typed file field in a form array to the upload method of the same name', function (string $type, string $handler, array $file) {
    $form = submittedFileForm([], ['upload' => $file]);

    $data = $form->request(['fields' => [
        'upload' => ['label' => 'Upload', 'type' => $type, 'rules' => ['required', 'mime:image/png,image/webp']],
    ]]);

    expect(FileFieldTestUpload::$calls)->toBe([
        ['handler' => $handler, 'name' => 'upload', 'rules' => ['required', 'mime:image/png,image/webp'], 'messages' => []],
    ])
        ->and($data['upload'])->toEqual((object) ['savedBy' => $handler]);
})->with([
    'file' => ['file', 'file', postedFile('notes.txt', 'text/plain')],
    'files' => ['files', 'file', postedFiles()],
    'image' => ['image', 'image', postedFile()],
    'images' => ['images', 'image', postedFiles()],
    'pdf' => ['pdf', 'pdf', postedFile('resume.pdf', 'application/pdf')],
    'pdfs' => ['pdfs', 'pdf', postedFiles()],
]);

it('gives the handler the bare name of a field keyed with []', function () {
    $form = submittedFileForm([], ['photos' => postedFiles()]);

    $data = $form->request(['fields' => [
        'photos[]' => ['label' => 'Photos', 'type' => 'images'],
    ]]);

    expect(FileFieldTestUpload::$calls[0]['name'])->toBe('photos')
        ->and($data)->toHaveKey('photos[]');
});

it('splits a rules string on a file field in a form array', function () {
    $form = submittedFileForm([], ['photo' => postedFile()]);

    $form->request(['fields' => [
        'photo' => ['label' => 'Photo', 'type' => 'image', 'rules' => 'required,conversions:thumbnail,medium,name:hash,16'],
    ]]);

    expect(FileFieldTestUpload::$calls[0]['rules'])->toBe(['required', 'conversions:thumbnail,medium', 'name:hash,16']);
});

// WITHOUT THE UPLOAD SERVICE ---------------------------------------------

it('still validates an empty file field as before when the upload service is missing', function () {
    Registry::remove('upload');
    $form = submittedFileForm(['name' => 'Tim']);

    $form->request('Name, Photo|file[required]');

    expect($form->getErrors())->toHaveKey('photo')
        ->and($form->ok())->toBeFalse();
});

it('says the upload service is missing when a file arrives without it', function () {
    Registry::remove('upload');
    $form = submittedFileForm([], ['photo' => postedFile()]);

    expect(fn () => $form->request('Name, Photo|image'))
        ->toThrow(FlickException::class, 'Upload');
});
