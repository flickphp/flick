<?php

declare(strict_types=1);

use Flick\Flick;
use Flick\Http\ArrayRequest;
use Flick\Service\Registry;
use Flick\Service\ServiceProvider;
use Flick\Session\ArraySession;
use Flick\Support\Support;

/*
 * A file field in a multistep form.
 *
 * Decided 2026-10-10 (Tim): the file a step saves stays in the form's data,
 * the review page shows its name, and coming back to the step does not ask
 * for it again. Written from that decision; guide/multistep-forms.md had no
 * file field in it when these were written.
 *
 * Before this the step saved the file and dropped what the upload service
 * returned: the review row was blank, multistepFormData() gave null for the
 * field, and a return visit failed on "required".
 *
 * The upload service is Pro's, so a stand-in takes its place, as in
 * FileFieldSubmissionTest. It reads the request the way the real one does -
 * a file that did not arrive is null, with an error when the field is
 * required - and returns objects with the properties Pro's FileInfo has.
 */
class MultistepUploadStandIn
{
    /** @var list<string> the field names it was asked to save, in order */
    public static array $calls = [];

    /** an error to refuse the next arriving file with */
    public static ?string $refuseWith = null;

    public static bool $asCollection = false;

    public function __construct(private Support $support) {}

    public function file(string $name, array $rules = [], array $messages = []): mixed
    {
        return $this->handle($name, $rules);
    }

    public function image(string $name, array $rules = [], array $messages = []): mixed
    {
        return $this->handle($name, $rules);
    }

    public function pdf(string $name, array $rules = [], array $messages = []): mixed
    {
        return $this->handle($name, $rules);
    }

    private function handle(string $name, array $rules): mixed
    {
        self::$calls[] = $name;

        if (! $this->support->request()->hasFile($name)) {
            if (in_array('required', $rules, true)) {
                $this->support->addError($name, $name.' is required');
            }

            return null;
        }

        if (self::$refuseWith !== null) {
            $this->support->addError($name, self::$refuseWith);

            return null;
        }

        $chosen = $this->support->request()->file($name)['name'];

        if (is_array($chosen)) {
            return array_map(self::saved(...), $chosen);
        }

        return self::$asCollection
            ? (object) ['original' => self::saved($chosen), 'conversions' => ['thumbnail' => self::saved('thumb-'.$chosen)]]
            : self::saved($chosen);
    }

    private static function saved(string $chosen): object
    {
        return (object) [
            'name' => 'avatar-'.count(self::$calls).'.jpg',
            'originalFilename' => $chosen,
            'path' => '/uploads/avatar-'.count(self::$calls).'.jpg',
        ];
    }
}

class MultistepUploadStandInProvider implements ServiceProvider
{
    private Support $support;

    public function register(mixed $container): void
    {
        $container->set('upload', fn () => new MultistepUploadStandIn($this->support));
    }

    public function setConfig(array $config): void {}

    public function setSupport(Support $support): void
    {
        $this->support = $support;
    }
}

const UPLOAD_WIZARD = [
    'About' => [
        'fields' => [
            'name' => ['label' => 'Name', 'rules' => ['required']],
        ],
    ],
    'Photo' => [
        'fields' => [
            'photo' => ['label' => 'Photo', 'type' => 'image', 'rules' => ['required', 'width:400']],
        ],
    ],
    'Notes' => [
        'fields' => [
            'notes' => ['label' => 'Notes', 'type' => 'textarea'],
        ],
    ],
];

function uploadWizard(ArraySession $session, ArrayRequest $request, array $config = []): Flick
{
    return new Flick($config + [
        'request' => $request,
        'session' => $session,
        'echo' => false,
        'csrf' => false,
        'id' => 'wizard',
    ]);
}

// One step submitted: what createMultistep() returned, and the form it ran on.
function submitUploadStep(ArraySession $session, array $post = [], array $files = [], array $steps = UPLOAD_WIZARD): array
{
    $form = uploadWizard($session, ArrayRequest::createMultipart(['_id' => 'wizard'] + $post, $files));

    return [$form->createMultistep($steps, ['testMode' => true]), $form];
}

// The page a visitor gets: a breadcrumb click when $step is named, the
// wizard's current page when it is not.
function openUploadWizard(ArraySession $session, ?string $step = null, array $steps = UPLOAD_WIZARD, array $config = []): array
{
    $form = uploadWizard($session, new ArrayRequest([
        'server' => ['REQUEST_URI' => '/apply'.($step === null ? '' : '?step='.urlencode($step)), 'REQUEST_METHOD' => 'GET'],
        'get' => $step === null ? [] : ['step' => $step],
    ]), $config);

    return [$form->createMultistep($steps), $form];
}

function chosenFile(string $filename = 'holiday.jpg'): array
{
    return ['name' => $filename, 'type' => 'image/jpeg', 'tmp_name' => '/tmp/phpUPLOAD', 'error' => UPLOAD_ERR_OK, 'size' => 2400000];
}

function noFileChosen(): array
{
    return ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0];
}

// A wizard with every step done, sitting on its review page.
function finishedUploadWizard(array $photo = ['photo' => null], array $steps = UPLOAD_WIZARD): ArraySession
{
    $session = new ArraySession;

    submitUploadStep($session, ['name' => 'Tim'], [], $steps);
    submitUploadStep($session, [], $photo === ['photo' => null] ? ['photo' => chosenFile()] : $photo, $steps);
    submitUploadStep($session, ['notes' => 'Hello'], [], $steps);

    return $session;
}

function fileInputIn(string $html): string
{
    preg_match('/<input type="file"[^>]*>/', $html, $match);

    return $match[0] ?? '';
}

beforeEach(function () {
    MultistepUploadStandIn::$calls = [];
    MultistepUploadStandIn::$refuseWith = null;
    MultistepUploadStandIn::$asCollection = false;
    Registry::add('upload', MultistepUploadStandInProvider::class);
});

afterEach(function () {
    Registry::remove('upload');
});

// THE FORM'S DATA --------------------------------------------------------

it('keeps the file a step saved in the form\'s data', function () {
    $session = finishedUploadWizard();

    [, $form] = openUploadWizard($session);
    $data = $form->multistepFormData(false);

    expect($data['name'])->toBe('Tim')
        ->and($data['notes'])->toBe('Hello')
        ->and($data['photo'])->toBeObject()
        ->and($data['photo']->name)->toBe('avatar-1.jpg')
        ->and($data['photo']->path)->toBe('/uploads/avatar-1.jpg');
});

it('keeps every file a step saved from one field', function () {
    $steps = UPLOAD_WIZARD;
    $steps['Photo']['fields'] = ['photos' => ['label' => 'Photos', 'type' => 'images']];

    $session = finishedUploadWizard(['photos' => [
        'name' => ['one.jpg', 'two.jpg'],
        'type' => ['image/jpeg', 'image/jpeg'],
        'tmp_name' => ['/tmp/phpONE', '/tmp/phpTWO'],
        'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
        'size' => [1200000, 2400000],
    ]], $steps);

    [$html, $form] = openUploadWizard($session, null, $steps);
    $data = $form->multistepFormData(false);

    expect($data['photos'])->toHaveCount(2)
        ->and($data['photos'][1]->originalFilename)->toBe('two.jpg')
        ->and($html)->toContain('one.jpg, two.jpg');
});

it('keeps the files of a field keyed with []', function () {
    $steps = UPLOAD_WIZARD;
    $steps['Photo']['fields'] = ['photos[]' => ['label' => 'Photos', 'type' => 'images']];

    $session = finishedUploadWizard(['photos' => [
        'name' => ['one.jpg', 'two.jpg'],
        'type' => ['image/jpeg', 'image/jpeg'],
        'tmp_name' => ['/tmp/phpONE', '/tmp/phpTWO'],
        'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
        'size' => [1200000, 2400000],
    ]], $steps);

    [, $form] = openUploadWizard($session, null, $steps);

    expect($form->multistepFormData(false)['photos[]'])->toHaveCount(2)
        ->and($form->multistepReviewData()['photos[]'])->toBe(['one.jpg', 'two.jpg']);
});

it('keeps the choices of a checkbox group keyed with []', function () {
    $steps = UPLOAD_WIZARD;
    $steps['Notes']['fields'] = ['colors[]' => [
        'label' => 'Colors',
        'type' => 'checkbox',
        'options' => ['red' => 'Red', 'blue' => 'Blue'],
    ]];
    $session = new ArraySession;

    submitUploadStep($session, ['name' => 'Tim'], [], $steps);
    submitUploadStep($session, [], ['photo' => chosenFile()], $steps);
    submitUploadStep($session, ['colors' => ['red', 'blue']], [], $steps);

    [, $form] = openUploadWizard($session, null, $steps);

    expect($form->multistepFormData(false)['colors[]'])->toBe(['red', 'blue']);
});

it('leaves an optional file that was never chosen as null', function () {
    $steps = UPLOAD_WIZARD;
    $steps['Photo']['fields']['photo']['rules'] = ['width:400'];
    $session = new ArraySession;

    submitUploadStep($session, ['name' => 'Tim'], [], $steps);
    [$result] = submitUploadStep($session, [], ['photo' => noFileChosen()], $steps);
    submitUploadStep($session, ['notes' => 'Hello'], [], $steps);

    [, $form] = openUploadWizard($session, null, $steps);

    expect($result['nextStep'])->toBe('Notes')
        ->and($form->multistepFormData(false)['photo'])->toBeNull();
});

// A class that does not exist until this is called, standing in for the
// upload service's file class on a site that starts its session before
// Composer's autoloader.
function loadLateUploadedFileClass(): void
{
    if (! class_exists('LateUploadedFile', false)) {
        class LateUploadedFile
        {
            public string $name;

            public string $originalFilename;

            public string $path;
        }
    }
}

it('gives back the saved file when the session was started before the autoloader', function () {
    // what PHP puts in the session for an object whose class it could not
    // load at session_start(): a placeholder with no readable properties
    $placeholder = unserialize(str_replace(
        'O:8:"stdClass"',
        'O:16:"LateUploadedFile"',
        serialize((object) ['name' => 'avatar-1.jpg', 'originalFilename' => 'holiday.jpg', 'path' => '/uploads/avatar-1.jpg'])
    ));

    expect($placeholder)->toBeInstanceOf(__PHP_Incomplete_Class::class);

    $session = finishedUploadWizard();
    $session->setValue('photo', $placeholder);

    // by the time Flick reads the value, the class can be loaded
    loadLateUploadedFileClass();

    [$review, $form] = openUploadWizard($session);
    $photo = $form->multistepFormData(false)['photo'];
    [$step] = openUploadWizard($session, 'Photo');

    expect($photo)->toBeInstanceOf(LateUploadedFile::class)
        ->and($photo->name)->toBe('avatar-1.jpg')
        ->and($review)->toContain('holiday.jpg')
        ->and($step)->toContain('holiday.jpg');
});

// THE REVIEW PAGE --------------------------------------------------------

it('shows the name of the file on the review page', function () {
    [$html] = openUploadWizard(finishedUploadWizard());

    expect($html)->toContain('<strong>Photo</strong>')
        ->and($html)->toContain('holiday.jpg');
});

it('gives a custom review page the name of the file to print', function () {
    [, $form] = openUploadWizard(finishedUploadWizard());

    expect($form->multistepReviewData())->toBe(['name' => 'Tim', 'photo' => 'holiday.jpg', 'notes' => 'Hello']);
});

it('prints a file that came back with conversions by its own name', function () {
    MultistepUploadStandIn::$asCollection = true;

    [, $form] = openUploadWizard(finishedUploadWizard());

    expect($form->multistepReviewData()['photo'])->toBe('holiday.jpg');
});

it('escapes the name of the file on the review page', function () {
    [$html] = openUploadWizard(finishedUploadWizard(['photo' => chosenFile('<b>holiday</b>.jpg')]));

    expect($html)->toContain('&lt;b&gt;holiday&lt;/b&gt;.jpg')
        ->and($html)->not->toContain('<b>holiday</b>');
});

it('joins the choices of a checkbox group on the review page', function (string $views) {
    $steps = UPLOAD_WIZARD;
    $steps['Notes']['fields'] = ['colors' => [
        'label' => 'Colors',
        'type' => 'checkbox',
        'options' => ['red' => 'Red', 'blue' => 'Blue'],
    ]];
    $session = new ArraySession;

    submitUploadStep($session, ['name' => 'Tim'], [], $steps);
    submitUploadStep($session, [], ['photo' => chosenFile()], $steps);
    submitUploadStep($session, ['colors' => ['red', 'blue']], [], $steps);

    [$html] = openUploadWizard($session, null, $steps, ['views' => $views]);

    expect($html)->toContain('red, blue')
        ->and($html)->toContain('holiday.jpg');
})->with(['flick', 'bootstrap', 'bootstrap4', 'bulma', 'foundation', 'materialize', 'tailwind']);

// COMING BACK TO THE STEP ------------------------------------------------

it('names the saved file under the field when the visitor comes back', function () {
    [$html] = openUploadWizard(finishedUploadWizard(), 'Photo');

    expect($html)->toContain('name="photo"')
        ->and($html)->toContain('holiday.jpg');
});

it('does not mark the field required once it holds a file', function () {
    [$html] = openUploadWizard(finishedUploadWizard(), 'Photo');

    expect(fileInputIn($html))->toContain('name="photo"')
        ->and(fileInputIn($html))->not->toContain('required');
});

it('does not mark the field required when required was written as an attribute', function () {
    $steps = UPLOAD_WIZARD;
    $steps['Photo']['fields']['photo'] = ['label' => 'Photo', 'type' => 'image', 'attributes' => ['required' => true]];

    [$before] = openUploadWizard(new ArraySession, null, ['Photo' => $steps['Photo']]);
    [$after] = openUploadWizard(finishedUploadWizard(['photo' => null], $steps), 'Photo', $steps);

    expect(fileInputIn($before))->toContain(' required')
        ->and(fileInputIn($after))->not->toContain('required');
});

it('still marks the field required before a file is saved', function () {
    $session = new ArraySession;
    submitUploadStep($session, ['name' => 'Tim']);

    [$html] = openUploadWizard($session);

    expect(fileInputIn($html))->toContain(' required');
});

it('passes the step with the file it already holds', function () {
    $session = finishedUploadWizard();
    openUploadWizard($session, 'Photo');

    [$result, $form] = submitUploadStep($session, [], ['photo' => noFileChosen()]);

    expect($form->getErrors())->toBe([])
        ->and($result['nextStep'])->toBe('Notes')
        ->and(MultistepUploadStandIn::$calls)->toBe(['photo'])
        ->and($form->multistepFormData(false)['photo']->name)->toBe('avatar-1.jpg');
});

it('takes a new file chosen on a return visit in place of the first', function () {
    $session = finishedUploadWizard();
    openUploadWizard($session, 'Photo');

    [, $form] = submitUploadStep($session, [], ['photo' => chosenFile('beach.jpg')]);
    $data = $form->multistepFormData(false);

    expect($data['photo']->originalFilename)->toBe('beach.jpg')
        ->and($data['photo']->name)->toBe('avatar-2.jpg');
});

it('keeps the first file when its replacement is refused', function () {
    $session = finishedUploadWizard();
    openUploadWizard($session, 'Photo');
    MultistepUploadStandIn::$refuseWith = 'That file is too large.';

    [$html, $form] = submitUploadStep($session, [], ['photo' => chosenFile('huge.jpg')]);

    expect($form->getErrors())->toHaveKey('photo')
        ->and($html)->toBeString()
        ->and($form->multistepFormData(false)['photo']->originalFilename)->toBe('holiday.jpg');
});

it('still asks for a required file that was never saved', function () {
    $session = new ArraySession;
    submitUploadStep($session, ['name' => 'Tim']);

    [$html, $form] = submitUploadStep($session, [], ['photo' => noFileChosen()]);

    expect($form->getErrors())->toHaveKey('photo')
        ->and($html)->toBeString()
        ->and($form->multistepFormData(false)['photo'])->toBeNull();
});

it('escapes the name of the file under the field', function () {
    [$html] = openUploadWizard(finishedUploadWizard(['photo' => chosenFile('<b>holiday</b>.jpg')]), 'Photo');

    expect($html)->toContain('&lt;b&gt;holiday&lt;/b&gt;.jpg')
        ->and($html)->not->toContain('<b>holiday</b>');
});

it('keeps the developer\'s own help text beside the name of the file', function () {
    $steps = UPLOAD_WIZARD;
    $steps['Photo']['fields']['photo']['attributes'] = ['help' => 'JPG or PNG, up to 5MB.'];

    [$html] = openUploadWizard(finishedUploadWizard(['photo' => null], $steps), 'Photo', $steps);

    expect($html)->toContain('holiday.jpg')
        ->and($html)->toContain('JPG or PNG, up to 5MB.');
});

it('says so in the form\'s language', function () {
    $assets = sys_get_temp_dir().'/flick-kept-upload-lang-'.uniqid();
    mkdir($assets.'/lang/es', 0755, true);
    file_put_contents($assets.'/lang/es/rules.php', '<?php return [];');
    file_put_contents($assets.'/lang/es/messages.php', "<?php return ['UploadedFile' => 'Subido: :file.'];");

    try {
        [$html] = openUploadWizard(finishedUploadWizard(), 'Photo', UPLOAD_WIZARD, ['assets' => $assets, 'lang' => 'es']);

        expect($html)->toContain('Subido: holiday.jpg.');
    } finally {
        unlink($assets.'/lang/es/rules.php');
        unlink($assets.'/lang/es/messages.php');
        rmdir($assets.'/lang/es');
        rmdir($assets.'/lang');
        rmdir($assets);
    }
});

// WHAT THE PAGE CARRIES --------------------------------------------------

it('keeps the saved file out of the field\'s recorded value', function () {
    [, $form] = openUploadWizard(finishedUploadWizard(), 'Photo');

    expect($form->getFields()['photo']['value'])->toBe('');
});

it('drops required from the rules the page records once the field holds a file', function () {
    [, $form] = openUploadWizard(finishedUploadWizard(), 'Photo');

    expect($form->getFields()['photo']['rules'])->toBe(['width:400']);
});

it('writes no cached view carrying a visitor\'s file name', function () {
    $assets = sys_get_temp_dir().'/flick-kept-upload-'.uniqid();
    mkdir($assets, 0755, true);

    try {
        [$html] = openUploadWizard(finishedUploadWizard(), 'Photo', UPLOAD_WIZARD, ['assets' => $assets, 'cache' => true]);

        $cached = implode('', array_map('file_get_contents', glob($assets.'/cache/views/*.html') ?: []));

        expect($html)->toContain('holiday.jpg')
            ->and($cached)->not->toContain('holiday.jpg');
    } finally {
        array_map('unlink', glob($assets.'/cache/views/*') ?: []);
        @unlink($assets.'/cache/.htaccess');
        @rmdir($assets.'/cache/views');
        @rmdir($assets.'/cache');
        @rmdir($assets);
    }
});

// A ONE-PAGE FORM THAT PERSISTS ------------------------------------------

it('keeps a file for a one-page form that persists to the session', function () {
    $session = new ArraySession;
    $config = ['session' => $session, 'persistToSession' => true, 'echo' => false, 'csrf' => false];

    $first = new Flick($config + ['request' => ArrayRequest::createMultipart(['_id' => 'myForm', 'name' => 'Tim'], ['photo' => chosenFile()])]);
    $first->request('Name, Photo|image[required]');

    $second = new Flick($config + ['request' => ArrayRequest::createMultipart(['_id' => 'myForm', 'name' => 'Tim'], ['photo' => noFileChosen()])]);
    $data = $second->request('Name, Photo|image[required]');

    expect($second->getErrors())->toBe([])
        ->and($data['photo']->originalFilename)->toBe('holiday.jpg')
        ->and(MultistepUploadStandIn::$calls)->toBe(['photo']);
});

it('does not read a file out of the session for a form that is not persisting', function () {
    $session = new ArraySession;
    $session->setValue('photo', (object) ['name' => 'avatar-1.jpg', 'originalFilename' => 'holiday.jpg']);

    $form = new Flick(['session' => $session, 'echo' => false, 'csrf' => false]);
    $html = $form->create('Name, Photo|image[required]');

    expect($html)->not->toContain('holiday.jpg')
        ->and(fileInputIn($html))->toContain(' required');
});

it('does not keep a file for a form that is not persisting', function () {
    $session = new ArraySession;
    $config = ['session' => $session, 'echo' => false, 'csrf' => false];

    $first = new Flick($config + ['request' => ArrayRequest::createMultipart(['_id' => 'myForm'], ['photo' => chosenFile()])]);
    $first->request('Name, Photo|image[required]');

    $second = new Flick($config + ['request' => ArrayRequest::createMultipart(['_id' => 'myForm'], ['photo' => noFileChosen()])]);
    $second->request('Name, Photo|image[required]');

    expect($session->hasValue('photo'))->toBeFalse()
        ->and($second->getErrors())->toHaveKey('photo');
});
